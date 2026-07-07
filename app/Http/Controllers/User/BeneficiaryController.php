<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Beneficiary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        $otp = rand(1000, 9999);

        DB::table('otp_verifications')->updateOrInsert(
            [
                'user_id' => Auth::id(),
                'phone' => Auth::user()->phone,
            ],
            [
                'otp' => $otp,
                'payload' => json_encode($validated),
                'expires_at' => now()->addMinutes(5),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        try {

            $phone = Auth::user()->phone;

            if (! str_starts_with($phone, '+')) {
                $phone = '+'.$validated['country_code'].ltrim($phone, '0');
            }

            $client = new Client(
                config('services.twilio.sid'),
                config('services.twilio.token')
            );

            $client->messages->create($phone, [
                'from' => config('services.twilio.number'),
                'body' => "Your Teracash OTP is {$otp}. It is valid for 5 minutes.",
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Failed to send OTP.',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP sent successfully.',
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'otp' => 'required|digits:4',
        ]);

        $otpData = DB::table('otp_verifications')
            ->where('user_id', Auth::id())
            ->where('phone', Auth::user()->phone)
            ->first();

        if (! $otpData) {
            return response()->json([
                'success' => false,
                'message' => 'OTP not found.',
            ], 404);
        }

        if (now()->gt($otpData->expires_at)) {

            DB::table('otp_verifications')
                ->where('id', $otpData->id)
                ->delete();

            return response()->json([
                'success' => false,
                'message' => 'OTP expired.',
            ], 400);
        }

        if ($otpData->otp != $request->otp) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid OTP.',
            ], 400);
        }

        $data = json_decode($otpData->payload, true);

        if (! $data) {
            return response()->json([
                'success' => false,
                'message' => 'Beneficiary data not found.',
            ], 400);
        }

        $beneficiary = Beneficiary::create([
            'user_id' => Auth::id(),
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone_number' => $data['phone_number'] ?? null,
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

        DB::table('otp_verifications')
            ->where('id', $otpData->id)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Beneficiary created successfully.',
            'data' => $beneficiary,
        ], 201);
    }
}
