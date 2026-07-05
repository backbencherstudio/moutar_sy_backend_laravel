<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserKyc;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class KycController extends Controller
{

    public function store(Request $request)
    {
        $request->validate([
            'country' => 'required|string',
            'document_type' => 'required|in:passport,id_card',
            'front_image' => 'required|image|max:5120',
            'back_image' => 'nullable|image|max:5120',
        ]);

        $userId = auth()->id();
        $existingKyc = UserKyc::where('user_id', $userId)->first();

        if ($existingKyc && $existingKyc->front_image) {
            Storage::disk('public')->delete($existingKyc->front_image);
        }
        $front = $request->file('front_image')->store('kyc/front', 'public');

        $back = $existingKyc ? $existingKyc->back_image : null;
        if ($request->hasFile('back_image')) {
            if ($existingKyc && $existingKyc->back_image) {
                Storage::disk('public')->delete($existingKyc->back_image);
            }
            $back = $request->file('back_image')->store('kyc/back', 'public');
        }

        try {
            $response = Http::attach(
                'file',
                fopen(storage_path('app/public/' . $front), 'r'),
                basename($front)
            )->withHeaders([
                'apikey' => env('OCR_SPACE_API_KEY'),
            ])->post(env('OCR_SPACE_URL'), [
                'language' => 'eng',
                'isOverlayRequired' => 'false',
                'OCREngine' => '2',
            ]);

            $result = $response->json();
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'OCR Service Connection Error: ' . $e->getMessage()
            ], 500);
        }


        if (!isset($result['ParsedResults'][0]['ParsedText'])) {
            return response()->json([
                'success' => false,
                'message' => 'OCR Failed to parse the image text.',
            ], 422);
        }

        $text = $result['ParsedResults'][0]['ParsedText'];


        $name = null;
        $nid = null;
        $dob = null;

        if (preg_match('/Name[:\s]+([A-Za-z ]+)/i', $text, $match)) {
            $name = trim($match[1]);
        }

        if (preg_match('/(\d{8,20})/', $text, $match)) {
            $nid = $match[1];
        }

        if (preg_match('/(\d{2}[\/\-]\d{2}[\/\-]\d{4})/', $text, $match)) {
            $dob = date('Y-m-d', strtotime($match[1]));
        }


        if (empty($nid)) {

            Storage::disk('public')->delete($front);
            if ($request->hasFile('back_image') && $back) {
                Storage::disk('public')->delete($back);
            }

            return response()->json([
                'success' => false,
                'message' => 'Could not detect NID or Passport number from the image. Please upload a clearer image.',
            ], 422);
        }


        $kyc = UserKyc::updateOrCreate(
            [
                'user_id' => $userId,
            ],
            [
                'country' => $request->country,
                'document_type' => $request->document_type,
                'front_image' => $front,
                'back_image' => $back,
                'nid_number' => $nid,
                'name' => $name,
                'date_of_birth' => $dob,
                'status' => 'pending',
            ]
        );

        $message = $kyc->wasRecentlyCreated ? 'KYC Submitted Successfully' : 'KYC Updated Successfully';

        return response()->json([
            'success' => true,
            'message' => $message,
            'extracted_data' => [
                'name' => $name,
                'nid' => $nid,
                'dob' => $dob,
            ],
        ], 200);
    }
}
