<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Beneficiary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Twilio\Rest\Client;

class BeneficiaryController extends Controller
{
    

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone_number' => 'nullable|string|max:30|unique:beneficiaries,phone_number',
            'country_code' => 'required|string|max:5',
            'city' => 'nullable|string|max:255',
            'transfer_type' => 'required|in:bank,mobile_wallet',
            'bank_or_wallet_name' => 'nullable|string|max:255',
            'account_or_wallet_number' => 'required|string|max:255',
            'branch_name' => 'nullable|string|max:255',
            'routing_number' => 'nullable|string|max:255',
            'swift_code' => 'nullable|string|max:255',
        ]);

        try {

            $phone = $validated['phone_number'];

            if (! str_starts_with($phone, '+')) {
                $phone = '+'.$validated['country_code'].ltrim($phone, '0');
            }

            // Send OTP using Didit
            $response = Http::withHeaders([
                'x-api-key' => config('services.didit.api_key'),
                'Accept' => 'application/json',
            ])->post(
                config('services.didit.url').'/phone/send/',
                [
                    'phone_number' => $phone,
                    'options' => [
                        'code_size' => 4,
                        'preferred_channel' => 'sms',
                    ],
                ]
            );

            // Didit failed
            if (! $response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send OTP.',
                    'response' => $response->json(),
                ], $response->status());
            }

            // Save beneficiary payload
            DB::table('otp_verifications')->updateOrInsert(
                [
                    'user_id' => Auth::id(),
                    'phone' => $phone,
                ],
                [
                    'otp' => null,
                    'payload' => json_encode($validated),
                    'expires_at' => now()->addMinutes(5),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'OTP sent successfully.',
                'phone' => $phone,
                'data' => $response->json(),
            ], 200);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Failed to send OTP.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'otp' => 'required|digits:4',
        ]);

        $user = Auth::user();

        // Make sure phone is in E.164 format
        $phone = $request->phone;

        if (! str_starts_with($phone, '+')) {
            $phone = '+'.ltrim($phone, '0');
        }

        // Find OTP request
        $otpData = DB::table('otp_verifications')
            ->where('user_id', $user->id)
            ->where('phone', $phone)
            ->first();

        if (! $otpData) {
            return response()->json([
                'success' => false,
                'message' => 'OTP request not found.',
            ], 404);
        }

        // Check local expiry
        if (now()->gt($otpData->expires_at)) {

            DB::table('otp_verifications')
                ->where('id', $otpData->id)
                ->delete();

            return response()->json([
                'success' => false,
                'message' => 'OTP expired.',
            ], 400);
        }

        try {

            // Verify OTP using Didit
            $response = Http::withHeaders([
                'x-api-key' => config('services.didit.api_key'),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->post(
                config('services.didit.url').'/phone/check/',
                [
                    'phone_number' => $phone,
                    'code' => $request->otp,
                ]
            );

            $diditData = $response->json();

            // Didit API error
            if (! $response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'OTP verification failed.',
                    'response' => $diditData,
                ], $response->status());
            }

            // Didit OTP not approved
            if (($diditData['status'] ?? null) !== 'Approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid OTP.',
                    'response' => $diditData,
                ], 400);
            }

            // Get beneficiary data
            $data = json_decode($otpData->payload, true);

            if (! $data) {
                return response()->json([
                    'success' => false,
                    'message' => 'Beneficiary data not found.',
                ], 400);
            }

            // Create beneficiary
            $beneficiary = Beneficiary::create([
                'user_id' => $user->id,
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone_number' => $data['phone_number'] ?? $phone,
                'country_code' => strtoupper($data['country_code']),
                'city' => $data['city'] ?? null,
                'transfer_type' => $data['transfer_type'],
                'bank_or_wallet_name' => $data['bank_or_wallet_name'] ?? null,
                'account_or_wallet_number' => $data['account_or_wallet_number'],
                'branch_name' => $data['branch_name'] ?? null,
                'routing_number' => $data['routing_number'] ?? null,
                'swift_code' => $data['swift_code'] ?? null,
                'status' => 'active',
            ]);

            // Delete OTP record after successful verification
            DB::table('otp_verifications')
                ->where('id', $otpData->id)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => 'Beneficiary created successfully.',
                'data' => $beneficiary,
            ], 201);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'OTP verification failed.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
