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

    public function index(Request $request)
{
    $user = Auth::user();

    // Authenticated ইউজারের KYC রেকর্ড ডাটাবেজ থেকে খুঁজে বের করা
    $kyc = UserKyc::where('user_id', $user->id)->first();

    if (! $kyc) {
        return response()->json([
            'success' => false,
            'message' => 'No KYC record found for this user.',
            'data'    => null,
        ], 404);
    }

    return response()->json([
        'success' => true,
        'message' => 'KYC profile fetched successfully.',
        'data'    => [
            'id'                   => $kyc->id,
            'status'               => $kyc->status,
            'name'                 => $kyc->name,
            'email'                => $kyc->email,
            'phone'                => $kyc->phone,
            'gender'               => $kyc->gender,
            'date_of_birth'        => $kyc->date_of_birth?->format('Y-m-d'),
            'address'              => $kyc->address,
            'country'              => $kyc->country,
            'father_name'          => $kyc->father_name,
            'mother_name'          => $kyc->mother_name,
            'document_type'        => $kyc->document_type,
            'document_number'      => $kyc->document_number,
            'nid_number'           => $kyc->nid_number,
            'didit_verification_data' => $kyc->didit_verification_data,
            'passport_number'      => $kyc->passport_number,
            'document_expiry_date' => $kyc->document_expiry_date?->format('Y-m-d'),
            'front_image'          => $kyc->front_image,
            'back_image'           => $kyc->back_image,
            'rejection_reason'     => $kyc->rejection_reason,
            'verified_at'          => $kyc->verified_at,
            'created_at'           => $kyc->created_at,
        ],
    ], 200);
}
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
            'callback' => 'https://teracash.pixelstack.cloud/api/webhooks/didit',
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
            'verification_url' => $verificationUrl,
        ], 200);
    }

    public function initiateVerification(Request $request)
    {

        if ($request->isMethod('get')) {
            $sessionId = $request->query('verificationSessionId')
                      ?? $request->query('session_id')
                      ?? $request->query('vendor_data');

            $status = $request->query('status', 'Completed');

            if ($sessionId) {
                $kyc = UserKyc::where('didit_session_id', $sessionId)->first();

                if ($kyc && $kyc->status === 'pending') {
                    $apiKey = config('services.didit.api_key');
                    $apiUrl = "https://apx.didit.me/v1/session/{$sessionId}/decision/";

                    $response = Http::withHeaders(['x-api-key' => $apiKey])->get($apiUrl);

                    if ($response->successful()) {
                        $payload = $response->json();

                        $fakeRequest = new Request([], [], [], [], [], [], json_encode($payload));
                        $fakeRequest->setMethod('POST');
                        $fakeRequest->headers->set('X-Timestamp', (string) time());

                        return $this->initiateVerification($fakeRequest);
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'KYC verification process finished.',
                'status' => $status,
            ], 200);
        }

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

        $sessionId = $payload['session_id'] ?? $payload['session']['id'] ?? null;
        $eventId = $payload['event_id'] ?? null;

        $status = $payload['decision']['status']
                    ?? $payload['session']['status']
                    ?? $payload['status']
                    ?? '';

        $kyc = UserKyc::where('didit_session_id', $sessionId)->first();

        if (! $kyc) {
            Log::warning("UserKyc record not found for Session ID: {$sessionId}");

            return response()->json(['error' => 'Session not found'], 404);
        }

        $existingPayload = is_array($kyc->didit_webhook_payload)
            ? $kyc->didit_webhook_payload
            : json_decode($kyc->didit_webhook_payload ?? '[]', true);

        if ($eventId && isset($existingPayload['event_id']) && $existingPayload['event_id'] === $eventId) {
            return response()->json(['status' => 'duplicate ignored'], 200);
        }

        $kyc->didit_webhook_payload = $payload;
        $kyc->didit_webhook_received_at = now();
        $kyc->didit_workflow_id = $payload['workflow_id'] ?? $kyc->didit_workflow_id;
        $kyc->didit_attempt_id = $payload['attempt_id'] ?? $kyc->didit_attempt_id;
        $kyc->didit_verification_id = $payload['verification_id'] ?? $payload['id'] ?? $kyc->didit_verification_id;

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

                    $holder = $idData['holder_fields']
                            ?? $idData['fields']
                            ?? $idData['extracted_data']
                            ?? [];

                    $getValue = function ($key) use ($holder) {
                        if (! isset($holder[$key])) {
                            return null;
                        }

                        return is_array($holder[$key]) ? ($holder[$key]['value'] ?? null) : $holder[$key];
                    };

                    $fullName = $getValue('full_name') ?? $getValue('name');
                    $firstName = $getValue('first_name') ?? '';
                    $lastName = $getValue('last_name') ?? '';

                    if (! empty($fullName)) {
                        $kyc->name = trim($fullName);
                    } elseif (! empty($firstName) || ! empty($lastName)) {
                        $kyc->name = trim("{$firstName} {$lastName}");
                    }

                    $kyc->email = $getValue('email') ?? $kyc->email;
                    $kyc->phone = $getValue('phone') ?? $getValue('phone_number') ?? $kyc->phone;

                    $gender = strtolower($getValue('gender') ?? $getValue('sex') ?? '');
                    $kyc->gender = in_array($gender, ['male', 'female', 'other']) ? $gender : $kyc->gender;
                    $kyc->date_of_birth = $getValue('date_of_birth') ?? $getValue('dob');

                    $kyc->address = $getValue('address') ?? $getValue('full_address');
                    $kyc->country = $idData['issuing_country'] ?? $getValue('country') ?? 'Bangladesh';

                    $kyc->father_name = $getValue('father_name')
                                     ?? $getValue('fathers_name')
                                     ?? $getValue('father_name_en')
                                     ?? $getValue('father');

                    $kyc->mother_name = $getValue('mother_name')
                                     ?? $getValue('mothers_name')
                                     ?? $getValue('mother_name_en')
                                     ?? $getValue('mother');

                    $docNumber = $getValue('document_number')
                              ?? $getValue('id_number')
                              ?? $getValue('nid_number')
                              ?? $getValue('nid');

                    $kyc->document_number = $docNumber;
                    $kyc->document_expiry_date = $getValue('expiry_date') ?? $getValue('expiration_date');

                    $docTypeRaw = strtolower($idData['document_type'] ?? '');
                    if (str_contains($docTypeRaw, 'passport')) {
                        $kyc->document_type = 'passport';
                        $kyc->passport_number = $docNumber;
                    } elseif (str_contains($docTypeRaw, 'driving')) {
                        $kyc->document_type = 'driving_license';
                    } elseif (str_contains($docTypeRaw, 'nid') || str_contains($docTypeRaw, 'national_id')) {
                        $kyc->document_type = 'nid';
                        $kyc->nid_number = $docNumber;
                    } else {
                        $kyc->document_type = 'id_card';
                        $kyc->nid_number = $docNumber;
                    }

                    $kyc->front_image = $idData['front_document_url'] ?? $idData['front_image_url'] ?? null;
                    $kyc->back_image = $idData['back_document_url'] ?? $idData['back_image_url'] ?? null;

                    $kyc->didit_verification_data = $idData;
                }
                break;

            case 'declined':
            case 'rejected':
                $kyc->status = 'rejected';
                $kyc->rejection_reason = $payload['decision']['decline_reason']
                                        ?? $payload['decline_reason']
                                        ?? 'Verification declined by provider.';
                break;

            case 'in review':
            case 'in_review':
                $kyc->status = 'review';
                break;

            default:
                if (empty($kyc->status) || $kyc->status === 'pending') {
                    $kyc->status = 'pending';
                }
                break;
        }

        $kyc->save();

        return response()->json([
            'success' => true,
            'message' => 'Data updated successfully.',
            'kyc' => $kyc,
        ], 200);
    }

    public function checkAndSyncKycStatus(Request $request)
    {
        $user = Auth::user();
        $kyc = UserKyc::where('user_id', $user->id)->first();

        if (! $kyc || ! $kyc->didit_session_id) {
            return response()->json(['error' => 'No active KYC session found for this user.'], 404);
        }

        $apiKey = config('services.didit.api_key');
        $apiUrl = "https://apx.didit.me/v1/session/{$kyc->didit_session_id}/decision/";

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
        ])->get($apiUrl);

        if ($response->successful()) {
            $payload = $response->json();

            $fakeRequest = new Request([], [], [], [], [], [], json_encode($payload));
            $fakeRequest->setMethod('POST');
            $fakeRequest->headers->set('X-Timestamp', (string) time());

            return $this->initiateVerification($fakeRequest);
        }

        Log::error('Manual Sync Failed', [
            'session_id' => $kyc->didit_session_id,
            'response' => $response->body(),
        ]);

        return response()->json(['error' => 'Could not fetch decision from Didit API.'], 400);
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
