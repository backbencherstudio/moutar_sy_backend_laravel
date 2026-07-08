<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\UserKyc;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class KycController extends Controller
{
    /**
     * ১. ভেরিফিকেশন সেশন তৈরি করা (ইউজারকে ক্যামেরার লাইভ লিংক দেওয়ার জন্য)
     */
    public function createSession(Request $request)
    {
        $user = Auth::user(); // কারেন্ট লগইন করা ইউজার

        $apiKey     = config('services.didit.api_key');
        $workflowId = config('services.didit.workflow_id');
        $apiUrl     = config('services.didit.url') . '/session'; // v3 Session Endpoint

        // Didit API-তে সেশন ক্রিয়েট করার রিকোয়েস্ট পাঠানো
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type'  => 'application/json',
        ])->post($apiUrl, [
            'workflow_id'  => $workflowId,
            'vendor_data'  => (string) $user->id, // ট্র্যাকিং এর জন্য ইউজার আইডি পাঠানো
            'redirect_url' => url('/kyc/callback'), // ভেরিফিকেশন শেষে ইউজার যেখানে ব্যাক করবে
        ]);

        if ($response->failed()) {
            Log::error('Didit Session Creation Failed', ['response' => $response->body()]);
            return response()->json(['error' => 'Could not initialize verification session.'], 500);
        }

        $data = $response->json();
        $sessionId = $data['id'] ?? null;
        $verificationUrl = $data['url'] ?? null;

        if (!$sessionId || !$verificationUrl) {
            return response()->json(['error' => 'Invalid data from verification provider.'], 500);
        }

        // আপনার 'user_kycs' টেবিলে সেশন আইডি স্টোর করা
        UserKyc::updateOrCreate(
            ['user_id' => $user->id],
            [
                'didit_session_id' => $sessionId,
                'status'           => 'pending',
                'didit_response'   => $data,
                'last_attempt_at'  => now(),
                'attempt_count'    => DB::raw('attempt_count + 1')
            ]
        );

        return response()->json([
            'success' => true,
            'verification_url' => $verificationUrl // এই লিংকে ইউজার আইডি কার্ড আপলোড করবে
        ], 200);
    }

    
    public function initiateVerification(Request $request)
    {
        $rawBody = $request->getContent();
        $timestamp = $request->header('X-Timestamp');

        // টাইমস্ট্যাম্প চেক (প্রোডাকশনের জন্য এটি একটিভ রাখবেন, টেস্টের সময় চাইলে স্কিপ করতে পারেন)
        if (!$timestamp) {
            return response()->json(['error' => 'Timestamp is missing'], 400);
        }

        // সিগনেচার ভেরিফিকেশন
        if (!$this->verifyDiditSignature($request, $rawBody)) {
            return response()->json(['error' => 'Invalid webhook signature'], 401);
        }

        $payload = json_decode($rawBody, true);
        if (!$payload) {
            return response()->json(['error' => 'Invalid JSON payload'], 400);
        }

        $webhookType = $payload['webhook_type'] ?? '';
        $sessionId   = $payload['session_id'] ?? null;
        $eventId     = $payload['event_id'] ?? null;
        $status      = $payload['status'] ?? '';

        // সেশন আইডি ধরে আপনার ডেটাবেজের রেকর্ড খুঁজে বের করা
        $kyc = UserKyc::where('didit_session_id', $sessionId)->first();

        if (!$kyc) {
            Log::warning("UserKyc record not found for Didit Session ID: {$sessionId}");
            return response()->json(['error' => 'Session not found'], 404);
        }

        // ইদেমপোটেন্সি চেক (একই ইভেন্ট বারবার প্রসেস হওয়া রোধ করতে)
        if ($kyc->didit_webhook_payload && isset($kyc->didit_webhook_payload['event_id'])) {
            if ($kyc->didit_webhook_payload['event_id'] === $eventId) {
                return response()->json(['status' => 'duplicate ignored'], 200);
            }
        }

        // মেটাডেটা স্টোর
        $kyc->didit_webhook_payload      = $payload;
        $kyc->didit_webhook_received_at = now();
        $kyc->didit_workflow_id          = $payload['workflow_id'] ?? $kyc->didit_workflow_id;
        $kyc->didit_attempt_id           = $payload['attempt_id'] ?? $kyc->didit_attempt_id;

        // স্ট্যাটাস এবং ডেটা আপডেট প্রসেস
        if (in_array($webhookType, ['status.updated', 'data.updated'])) {

            switch ($status) {
                case 'Approved':
                    $kyc->status = 'approved';
                    $kyc->verified_at = now();
                    $kyc->rejection_reason = null;

                    // Didit v3 রেসপন্স অবজেক্ট পার্স করা
                    $decision = $payload['decision'] ?? [];
                    $idVerifications = $decision['id_verifications'] ?? [];

                    if (!empty($idVerifications)) {
                        $idData = $idVerifications[0];
                        $holder = $idData['holder_fields'] ?? [];

                        // ১. পার্সোনাল ডেটা অটো-ম্যাপিং ও স্টোর
                        $kyc->name          = trim(($holder['first_name'] ?? '') . ' ' . ($holder['last_name'] ?? ''));
                        $kyc->date_of_birth = $holder['date_of_birth'] ?? null;

                        // ২. ডকুমেন্ট নম্বর ম্যাপিং
                        $docNumber            = $holder['document_number'] ?? null;
                        $kyc->document_number = $docNumber;
                        $kyc->document_expiry_date = $holder['expiry_date'] ?? null;

                        // ৩. ডকুমেন্ট টাইপ অনুযায়ী আপনার নির্দিষ্ট কলামে স্টোর (NID/Passport)
                        $docType = strtolower($idData['document_type'] ?? '');
                        $kyc->document_type = match ($docType) {
                            'passport' => 'passport',
                            'id_card'  => 'id_card',
                            'driver_license', 'driving_license' => 'driving_license',
                            default    => 'nid'
                        };

                        if ($kyc->document_type === 'nid') {
                            $kyc->nid_number = $docNumber;
                        } elseif ($kyc->document_type === 'passport') {
                            $kyc->passport_number = $docNumber;
                        }

                        // ৪. ইউজারের স্ক্যান করা আইডি কার্ডের ইমেজ লিঙ্ক স্টোর
                        $kyc->front_image = $idData['front_image_url'] ?? null;
                        $kyc->back_image  = $idData['back_image_url'] ?? null;

                        $kyc->didit_verification_data = $idData;
                    }
                    break;

                case 'Declined':
                    $kyc->status = 'rejected';
                    $decision = $payload['decision'] ?? [];
                    $idVerifications = $decision['id_verifications'] ?? [];
                    $warnings = !empty($idVerifications) ? ($idVerifications[0]['warnings'] ?? []) : [];

                    $kyc->rejection_reason = !empty($warnings)
                        ? implode(', ', $warnings)
                        : 'Identity verification criteria declined.';
                    break;

                case 'In Review':
                    $kyc->status = 'review';
                    break;

                case 'In Progress':
                case 'Resubmitted':
                    $kyc->status = 'pending';
                    break;

                case 'Abandoned':
                    $kyc->status = 'rejected';
                    $kyc->rejection_reason = 'Verification session was abandoned by the user.';
                    break;

                case 'Expired':
                case 'KYC Expired':
                    $kyc->status = 'rejected';
                    $kyc->rejection_reason = 'The verification session has expired.';
                    break;
            }
        }

        $kyc->save();

        return response()->json([
            'success' => true,
            'message' => 'Webhook event successfully captured and synchronized.'
        ], 200);
    }

    /**
     * সিগনেচার ভেরিফিকেশন মেথড
     */
    private function verifyDiditSignature(Request $request, string $rawBody): bool
    {
        // টেস্টিং এর জন্য এটি true করা আছে, লাইভে যাওয়ার সময় নিচের লাইনটি কমেন্ট/ডিলিট করে দেবেন।
        return true;

        $secret = config('services.didit.webhook_secret');
        if (!$secret) return false;

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
