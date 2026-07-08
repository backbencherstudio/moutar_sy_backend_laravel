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
        $user = Auth::user(); // কারেন্ট লগইন করা ইউজার

        $apiKey = config('services.didit.api_key');
        $workflowId = config('services.didit.workflow_id');
        $apiUrl = rtrim(config('services.didit.url'), '/').'/session/'; // v3 Session Endpoint, trailing slash সহ

        // Didit API-তে সেশন ক্রিয়েট করার রিকোয়েস্ট পাঠানো
        $response = Http::withHeaders([
            'x-api-key' => $apiKey,      // Bearer না, x-api-key
            'Content-Type' => 'application/json',
        ])->post($apiUrl, [
            'workflow_id' => $workflowId,
            'vendor_data' => (string) $user->id, // ট্র্যাকিং এর জন্য ইউজার আইডি পাঠানো
            'callback' => 'http://teracash.pixelstack.cloud/api/webhooks/didit', // redirect_url না, callback ফিল্ড
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

        // আপনার 'user_kycs' টেবিলে সেশন আইডি স্টোর করা
        UserKyc::updateOrCreate(
            ['user_id' => $user->id],
            [
                'didit_session_id' => $sessionId,
                'status' => 'pending',
                'didit_response' => $data,
                'last_attempt_at' => now(),
                'attempt_count' => DB::raw('attempt_count + 1'),
            ]
        );

    

        return response()->json([
            'success' => true,
            'verification_url' => $verificationUrl, // এই লিংকে ইউজার আইডি কার্ড আপলোড করবে
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

        // Payload লগ করে নিশ্চিত হন ডাটা কি ফরম্যাটে আসছে
        Log::info('Didit Payload:', $payload);

        $webhookType = $payload['webhook_type'] ?? '';
        $sessionId = $payload['session_id'] ?? null;
        $eventId = $payload['event_id'] ?? null;
        $status = $payload['status'] ?? '';

        // ১. সঠিক কলাম নাম 'didit_user_id' দিয়ে খুঁজুন
        $kyc = UserKyc::where('didit_user_id', $sessionId)->first();

        if (! $kyc) {
            Log::warning("UserKyc record not found for Session ID: {$sessionId}");

            return response()->json(['error' => 'Session not found'], 404);
        }

        // ২. Idempotency Check
        if (isset($kyc->didit_webhook_payload['event_id']) && $kyc->didit_webhook_payload['event_id'] === $eventId) {
            return response()->json(['status' => 'duplicate ignored'], 200);
        }

        // ডাটাবেজের সঠিক কলামে মেটাডাটা সেভ
        $kyc->didit_webhook_payload = $payload;
        $kyc->didit_webhook_received_at = now();
        $kyc->didit_workflow_id = $payload['workflow_id'] ?? $kyc->didit_workflow_id;
        $kyc->didit_attemp_id = $payload['attempt_id'] ?? $kyc->didit_attemp_id; // DB Column: didit_attemp_id

        // ৩. স্ট্যাটাস প্রসেসিং
        if (in_array($webhookType, ['status.updated', 'data.updated']) || ! empty($status)) {

            switch (strtolower($status)) {
                case 'approved':
                    $kyc->status = 'approved';
                    $kyc->verified_at = now();
                    $kyc->rejection_reason = null;

                    $decision = $payload['decision'] ?? [];
                    $idVerifications = $decision['id_verifications'] ?? [];

                    if (! empty($idVerifications)) {
                        $idData = $idVerifications[0];
                        $holder = $idData['holder_fields'] ?? [];

                        // পার্সোনাল ডাটা
                        $kyc->name = trim(($holder['first_name'] ?? '').' '.($holder['last_name'] ?? ''));
                        $kyc->date_of_birth = $holder['date_of_birth'] ?? null;
                        $kyc->document_number = $holder['document_number'] ?? null;

                        // ডকুমেন্ট টাইপ
                        $docType = strtolower($idData['document_type'] ?? '');
                        if (str_contains($docType, 'passport')) {
                            $kyc->document_type = 'passport';
                            $kyc->passport_number = $kyc->document_number;
                        } else {
                            $kyc->document_type = 'nid';
                            $kyc->nid_number = $kyc->document_number;
                        }

                        // ছবি
                        $kyc->front_image = $idData['front_image_url'] ?? null;
                        $kyc->back_image = $idData['back_image_url'] ?? null;

                        $kyc->didit_verification_data = $idData;
                    }
                    break;

                case 'declined':
                case 'rejected':
                    $kyc->status = 'rejected';
                    $kyc->rejection_reason = 'Verification declined by provider.';
                    break;

                case 'in review':
                    $kyc->status = 'review';
                    break;

                default:
                    $kyc->status = 'pending';
                    break;
            }
        }

        $kyc->save();

        return response()->json([
            'success' => true,
            'message' => 'Data updated successfully.',
        ], 200);
    }

    private function verifyDiditSignature(Request $request, string $rawBody): bool
    {
        // টেস্টিং এর জন্য এটি true করা আছে, লাইভে যাওয়ার সময় নিচের লাইনটি কমেন্ট/ডিলিট করে দেবেন।
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
