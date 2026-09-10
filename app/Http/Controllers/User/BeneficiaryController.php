<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Beneficiary;
use App\Models\MobileMoneyProvider;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class BeneficiaryController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'country_name' => ['required', 'string'],
        ]);

        $providers = MobileMoneyProvider::where('country_name', $request->country_name)
            ->where('status', 1)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Mobile money providers retrieved successfully.',
            'data' => $providers,
        ], 200);
    }

    // public function store(Request $request)
    // {
    //     $validated = $request->validate([
    //         'country_name' => 'required|string|max:255',
    //         'mobile_name' => 'nullable|string|max:255',
    //         'phone_number' => 'required|string|max:30|unique:beneficiaries,phone_number',
    //         'beneficiary_name' => 'required|string|max:255',
    //     ]);

    //     try {

    //         $phone = trim($validated['phone_number']);

    //         $countryCodes = [
    //             'Senegal' => '221',

    //         ];

    //         if (! str_starts_with($phone, '+')) {

    //             $countryCode = $countryCodes[$validated['country_name']] ?? null;

    //             if (! $countryCode) {
    //                 return response()->json([
    //                     'success' => false,
    //                     'message' => 'Country code not found for selected country.',
    //                 ], 422);
    //             }

    //             $phone = ltrim($phone, '0');
    //             $phone = '+'.$countryCode.$phone;
    //         }

    //         $response = Http::withHeaders([
    //             'x-api-key' => config('services.didit.api_key'),
    //             'Accept' => 'application/json',
    //             'Content-Type' => 'application/json',
    //         ])->post(
    //             config('services.didit.url').'/phone/send/',
    //             [
    //                 'phone_number' => $phone,

    //                 'options' => [
    //                     'code_size' => 4,
    //                     'preferred_channel' => 'sms',
    //                 ],
    //             ]
    //         );

    //         if (! $response->successful()) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Failed to send OTP.',
    //                 'response' => $response->json(),
    //             ], $response->status());
    //         }

    //         $diditData = $response->json();

    //         DB::table('otp_verifications')->updateOrInsert(
    //             [
    //                 'user_id' => Auth::id(),
    //                 'phone' => $phone,
    //             ],
    //             [
    //                 'otp' => null,

    //                 'payload' => json_encode([
    //                     'country_name' => $validated['country_name'],
    //                     'mobile_name' => $validated['mobile_name'] ?? null,
    //                     'phone_number' => $phone,
    //                     'beneficiary_name' => $validated['beneficiary_name'],

    //                     'session_id' => $diditData['session_id'] ?? null,
    //                 ]),

    //                 'expires_at' => now()->addMinutes(5),
    //                 'created_at' => now(),
    //                 'updated_at' => now(),
    //             ]
    //         );

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'OTP sent successfully.',
    //             'phone_number' => $phone,
    //             'data' => $diditData,
    //         ], 200);

    //     } catch (\Exception $e) {

    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Failed to send OTP.',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'country_name' => 'required|string|max:255',
            'mobile_name' => 'nullable|string|max:255',
            'beneficiary_name' => 'required|string|max:255',
        ]);

        try {

            // Logged-in user's account phone number
            $user = Auth::user();

            if (! $user || ! $user->phone) {
                return response()->json([
                    'success' => false,
                    'message' => 'User phone number not found.',
                ], 422);
            }

            // ONLY account creation phone number
            $phone = trim($user->phone);

            // Format phone number
            if (! str_starts_with($phone, '+')) {

                $countryCodes = [
                    'Senegal' => '221',
                ];

                $countryCode = $countryCodes[$validated['country_name']] ?? null;

                if (! $countryCode) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Country code not found for selected country.',
                    ], 422);
                }

                $phone = ltrim($phone, '0');
                $phone = '+'.$countryCode.$phone;
            }

            // Send OTP to user's registered phone
            $response = Http::withHeaders([
                'x-api-key' => config('services.didit.api_key'),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
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

            if (! $response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send OTP.',
                    'response' => $response->json(),
                ], $response->status());
            }

            $diditData = $response->json();

            DB::table('otp_verifications')->updateOrInsert(
                [
                    'user_id' => $user->id,
                    'phone' => $phone,
                ],
                [
                    'otp' => null,

                    'payload' => json_encode([
                        'country_name' => $validated['country_name'],
                        'mobile_name' => $validated['mobile_name'] ?? null,

                        // User's registered phone
                        'phone_number' => $phone,

                        'beneficiary_name' => $validated['beneficiary_name'],

                        'session_id' => $diditData['session_id'] ?? null,
                    ]),

                    'expires_at' => now()->addMinutes(5),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'OTP sent successfully to your registered phone number.',
                'phone_number' => $phone,
                'data' => $diditData,
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
            'phone_number' => 'required|string',
            'otp' => 'required|digits:4',
        ]);

        try {
            $user = Auth::user();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            $phoneInput = trim($request->phone_number);
            $formattedPhone = str_starts_with($phoneInput, '+')
                ? $phoneInput
                : '+'.ltrim($phoneInput, '0');

            $otpData = DB::table('otp_verifications')
                ->where('user_id', $user->id)
                ->where(function ($query) use ($phoneInput, $formattedPhone) {
                    $query->where('phone', $phoneInput)
                        ->orWhere('phone', $formattedPhone);
                })
                ->latest('id')
                ->first();

            if (! $otpData) {
                return response()->json([
                    'success' => false,
                    'message' => 'OTP request not found or invalid phone number.',
                ], 404);
            }

            if (now()->greaterThan(Carbon::parse($otpData->expires_at))) {
                DB::table('otp_verifications')
                    ->where('id', $otpData->id)
                    ->delete();

                return response()->json([
                    'success' => false,
                    'message' => 'OTP expired.',
                ], 400);
            }

            $payload = json_decode($otpData->payload, true);

            if (! $payload) {
                return response()->json([
                    'success' => false,
                    'message' => 'Beneficiary verification payload is missing or invalid.',
                ], 400);
            }

            $diditPhone = $otpData->phone;
            if (! str_starts_with($diditPhone, '+')) {
                $diditPhone = '+'.ltrim($diditPhone, '0');
            }

            $diditBody = [
                'phone_number' => $diditPhone,
                'code' => (string) $request->otp,
            ];

            if (! empty($payload['session_id'])) {
                $diditBody['session_id'] = $payload['session_id'];
            }

            $response = Http::withHeaders([
                'x-api-key' => config('services.didit.api_key'),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->post(
                rtrim(config('services.didit.url'), '/').'/phone/check/',
                $diditBody
            );

            $diditData = $response->json();

            if (! $response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => $diditData['message'] ?? 'Didit OTP verification failed.',
                    'response' => $diditData,
                ], $response->status());
            }

            if (($diditData['status'] ?? null) !== 'Approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or unapproved OTP.',
                    'response' => $diditData,
                ], 400);
            }

            $beneficiary = Beneficiary::create([
                'user_id' => $user->id,
                'country_name' => $payload['country_name'] ?? null,
                'mobile_name' => $payload['mobile_name'] ?? null,
                'phone_number' => $payload['phone_number'] ?? $diditPhone,
                'beneficiary_name' => $payload['beneficiary_name'] ?? null,
            ]);

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
