<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Twilio\Rest\Client;

class UserController extends Controller
{
    
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|unique:users,phone',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('users', 'public');
        }

        $userData = [
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            // 'password' => Hash::make($request->password),
            'image' => $imagePath,
        ];

        Cache::put('temp_user_'.$request->phone, $userData, now()->addMinutes(3));

        $otp = rand(1000, 9999);

        DB::table('otp_verifications')->where('phone', $request->phone)->delete();

        DB::table('otp_verifications')->insert([
            'phone' => $request->phone,
            'otp' => $otp,
            'expires_at' => Carbon::now()->addMinutes(3),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        try {
            $sid = env('TWILIO_SID');
            $token = env('TWILIO_AUTH_TOKEN');
            $twilio_number = env('TWILIO_NUMBER');

            $client = new Client($sid, $token);
            $client->messages->create($request->phone, [
                'from' => $twilio_number,
                'body' => "Your Teracash verification code is: $otp",
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Registration OTP sent to your Phone Number.',
                'phone' => $request->phone,
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to send SMS: '.$e->getMessage(),
            ], 500);
        }
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'otp' => 'required|string|size:4',
        ]);

        $verification = DB::table('otp_verifications')
            ->where('phone', $request->phone)
            ->where('otp', $request->otp)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (! $verification) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid OTP or OTP has expired.',
            ], 400);
        }

        $cachedData = Cache::get('temp_user_'.$request->phone);

        if (! $cachedData) {
            return response()->json([
                'status' => false,
                'message' => 'Registration session expired. Please register again.',
            ], 400);
        }

        $user = User::create([
            'name' => $cachedData['name'],
            'email' => $cachedData['email'],
            'phone' => $cachedData['phone'],
            'image' => $cachedData['image'],
            // 'password' => $cachedData['password'],
            'email_verified_at' => Carbon::now(),
        ]);

        DB::table('otp_verifications')->where('phone', $request->phone)->delete();
        Cache::forget('temp_user_'.$request->phone);

        return response()->json([
            'status' => true,
            'message' => 'User verification and registration successful!',
            'user' => $user,
        ], 201);
    }

    public function resetOtp(Request $request)
    {

        $request->validate([
            'phone' => 'required|string',
        ]);

        $cachedData = Cache::get('temp_user_'.$request->phone);

        if (! $cachedData) {
            return response()->json([
                'status' => false,
                'message' => 'Registration session expired. Please register again from the beginning.',
            ], 400);
        }

        $otp = rand(1000, 9999);

        DB::table('otp_verifications')->where('phone', $request->phone)->delete();

        DB::table('otp_verifications')->insert([
            'phone' => $request->phone,
            'otp' => $otp,
            'expires_at' => Carbon::now()->addMinutes(3),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        try {
            $sid = config('services.twilio.sid');
            $token = config('services.twilio.token');
            $twilio_number = config('services.twilio.number');

            $client = new \Twilio\Rest\Client($sid, $token);
            $client->messages->create($request->phone, [
                'from' => $twilio_number,
                'body' => "Your Teracash verification code is resend: $otp",
            ]);

            return response()->json([
                'status' => true,
                'message' => 'A new OTP has been resent to your Phone Number.',
                'phone' => $request->phone,
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to resend SMS: '.$e->getMessage(),
            ], 500);
        }
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|exists:users,phone',
        ], [
            'phone.exists' => 'This phone number is not registered.',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $otp = rand(1000, 9999);

        $user = User::where('phone', $request->phone)->first();
        $user->update([
            'otp' => $otp,
            'otp_expires_at' => \Carbon\Carbon::now()->addMinutes(3),
        ]);

        try {
            $sid = config('services.twilio.sid');
            $token = config('services.twilio.token');
            $twilio_number = config('services.twilio.number');

            $client = new \Twilio\Rest\Client($sid, $token);
            $client->messages->create($request->phone, [
                'from' => $twilio_number,
                'body' => "Your Teracash login verification code is: $otp",
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Login OTP sent to your Phone Number.',
                'phone' => $request->phone,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to send login SMS: '.$e->getMessage(),
            ], 500);
        }
    }

    public function loginVerify(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'otp' => 'required|string|size:4',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('phone', $request->phone)
            ->where('otp', $request->otp)
            ->where('otp_expires_at', '>', \Carbon\Carbon::now())
            ->first();

        if (! $user) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid OTP or OTP has expired.',
            ], 400);
        }

        $user->update([
            'otp' => null,
            'otp_expires_at' => null,
        ]);

        $plainToken = $user->createToken('login_token')->plainTextToken;

        $cleanToken = \Illuminate\Support\Str::after($plainToken, '|');
        

        return response()->json([
            'status' => true,
            'message' => 'Login successful!',
            'token' => $cleanToken,
            'user' => $user,
        ], 200);
    }
}
