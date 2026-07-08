<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\UserKyc;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KycController extends Controller
{
    public function createSession(Request $request)
    {
        $user = Auth::user();

        $apiKey = config('services.didit.api_key');
        $workflowId = config('services.didit.workflow_id');
        $apiUrl = rtrim(config('services.didit.url'), '/').'/session/';

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'Content-Type' => 'application/json',
        ])->post($apiUrl, [
            'workflow_id' => $workflowId,
            'vendor_data' => (string) $user->id,
            'callback' => 'http://teracash.pixelstack.cloud/api/webhooks/didit',
        ]);

        if ($response->failed()) {
            Log::error('Didit Session Creation Failed', [
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return response()->json(['error' => 'Could not initialize verification session.'], 500);
        }

        $data = $response->json();
        $sessionId = $data['session_id'] ?? $data['id'] ?? null;
        $verificationUrl = $data['url'] ?? null;

        if (! $sessionId || ! $verificationUrl) {
            Log::error('Didit Invalid Session Data', ['response' => $data]);

            return response()->json(['error' => 'Invalid data from verification provider.'], 500);
        }

        // Migration Schema অনুযায়ী কলামের সঠিক নাম ব্যবহার করা হয়েছে
        UserKyc::updateOrCreate(
            ['user_id' => $user->id],
            [
                'didit_session_id' => $sessionId, // Schema অনুযায়ী সঠিক
                'status' => 'pending',
                'didit_response' => $data,      // Schema অনুযায়ী সঠিক
                'last_attempt_at' => now(),
                'attempt_count' => DB::raw('attempt_count + 1'),
            ]
        );

        return response()->json([
            'success' => true,
            'verification_url' => $verificationUrl,
        ], 200);
    }

    public function initiateVerification(Request $request)
    {
        $rawBody = $request->getContent();
        $timestamp = $request->header('X-Timestamp');

        if (! $timestamp) {
            return response()->json(['error' => 'Timestamp is missing'], 400);
        }

        if (! $this->verifyDiditSignature($request, $rawBody)) {
            return response()->json(['error' => 'Invalid webhook signature'], 401);
        }

        $payload = json_decode($rawBody, true);
        if (! $payload) {
            return response()->json(['error' => 'Invalid JSON payload'], 400);
        }

        Log::info('Didit Raw Payload Received:', $payload);

        // Dynamic extraction (Session ID root বা session অবজেক্টেও থাকতে পারে)
        $sessionId = $payload['session_id'] ?? $payload['session']['id'] ?? null;
        $eventId = $payload['event_id'] ?? null;

        // Fix 1: Status Extract (Didit decision.status অথবা status দুইটাই চেক করবে)
        $status = $payload['decision']['status']
                    ?? $payload['session']['status']
                    ?? $payload['status']
                    ?? '';

        $kyc = UserKyc::where('didit_session_id', $sessionId)->first();

        if (! $kyc) {
            Log::warning("UserKyc record not found for Session ID: {$sessionId}");

            return response()->json(['error' => 'Session not found'], 404);
        }

        // Idempotency Check
        if ($eventId && isset($kyc->didit_webhook_payload['event_id']) && $kyc->didit_webhook_payload['event_id'] === $eventId) {
            return response()->json(['status' => 'duplicate ignored'], 200);
        }

        // Metadata Store
        $kyc->didit_webhook_payload = $payload;
        $kyc->didit_webhook_received_at = now();
        $kyc->didit_workflow_id = $payload['workflow_id'] ?? $kyc->didit_workflow_id;
        $kyc->didit_attempt_id = $payload['attempt_id'] ?? $kyc->didit_attempt_id;

        $normalizedStatus = strtolower(trim($status));

        switch ($normalizedStatus) {
            case 'approved':
                $kyc->status = 'approved';
                $kyc->verified_at = now();
                $kyc->rejection_reason = null;

                $decision = $payload['decision'] ?? $payload;
                $idVerifications = $decision['id_verifications'] ?? [];

                if (! empty($idVerifications)) {
                    $idData = $idVerifications[0];

                    // Fix 2: Holder Info Check (ফ্লেক্সিবল চেক)
                    $holder = $idData['holder_fields'] ?? $idData['fields'] ?? [];

                    // পার্সোনাল ডাটা
                    $firstName = $holder['first_name']['value'] ?? $holder['first_name'] ?? '';
                    $lastName = $holder['last_name']['value'] ?? $holder['last_name'] ?? '';
                    $kyc->name = trim("{$firstName} {$lastName}");

                    $kyc->date_of_birth = $holder['date_of_birth']['value'] ?? $holder['date_of_birth'] ?? null;
                    $kyc->document_number = $holder['document_number']['value'] ?? $holder['document_number'] ?? null;
                    $kyc->document_expiry_date = $holder['expiry_date']['value'] ?? $holder['expiry_date'] ?? null;

                    // Document Type mapping
                    $docType = strtolower($idData['document_type'] ?? '');
                    if (str_contains($docType, 'passport')) {
                        $kyc->document_type = 'passport';
                        $kyc->passport_number = $kyc->document_number;
                    } elseif (str_contains($docType, 'driving')) {
                        $kyc->document_type = 'driving_license';
                    } else {
                        $kyc->document_type = 'id_card';
                        $kyc->nid_number = $kyc->document_number;
                    }

                    // Fix 3: Images URL mapping
                    $kyc->front_image = $idData['front_document_url'] ?? $idData['front_image_url'] ?? null;
                    $kyc->back_image = $idData['back_document_url'] ?? $idData['back_image_url'] ?? null;

                    $kyc->didit_verification_data = $idData;
                }
                break;

            case 'declined':
            case 'rejected':
                $kyc->status = 'rejected';
                $kyc->rejection_reason = $payload['decision']['decline_reason'] ?? 'Verification declined by provider.';
                break;

            case 'in review':
            case 'in_review':
                $kyc->status = 'review';
                break;

            default:
                // Status না পেলেও অন্ততঃ Webhook পেয়েছ সেটি কনফার্ম করবে
                if (empty($kyc->status) || $kyc->status === 'pending') {
                    $kyc->status = 'pending';
                }
                break;
        }

        $kyc->save();

        return response()->json([
            'success' => true,
            'message' => 'Data updated successfully.',
        ], 200);
    }

    private function verifyDiditSignature(Request $request, string $rawBody): bool
    {
        return true;

        $secret = config('services.didit.webhook_secret');
        if (! $secret) {
            return false;
        }

        $signatureV2 = $request->header('X-Signature-V2');
        $signatureV1 = $request->header('X-Signature');

        if ($signatureV2 && hash_equals(hash_hmac('sha256', $rawBody, $secret), $signatureV2)) {
            return true;
        }

        if ($signatureV1 && hash_equals(hash_hmac('sha256', $rawBody, $secret), $signatureV1)) {
            return true;
        }

        return false;
    }
}
