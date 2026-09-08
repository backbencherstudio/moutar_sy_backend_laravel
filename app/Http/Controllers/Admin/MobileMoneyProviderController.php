<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MobileMoneyProvider;
use Illuminate\Http\Request;

class MobileMoneyProviderController extends Controller
{
     // GET /api/mobile-money-providers
    public function index()
    {
        $providers = MobileMoneyProvider::latest()->get();

        return response()->json([
            'status' => true,
            'message' => 'Mobile money providers retrieved successfully.',
            'data' => $providers,
        ]);
    }

    // POST /api/mobile-money-providers
    public function store(Request $request)
    {
        $validated = $request->validate([
            'country_name' => 'required|string|max:100',
            'name' => 'required|string|max:100',
            'status' => 'required|integer|in:1,2',
        ]);

        $provider = MobileMoneyProvider::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Mobile money provider created successfully.',
            'data' => $provider,
        ], 201);
    }

    // GET /api/mobile-money-providers/{id}
    public function show($id)
    {
        $provider = MobileMoneyProvider::find($id);

        if (!$provider) {
            return response()->json([
                'status' => false,
                'message' => 'Mobile money provider not found.',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $provider,
        ]);
    }

    // PUT /api/mobile-money-providers/{id}
    public function update(Request $request, $id)
    {
        $provider = MobileMoneyProvider::find($id);

        if (!$provider) {
            return response()->json([
                'status' => false,
                'message' => 'Mobile money provider not found.',
            ], 404);
        }

        $validated = $request->validate([
            'country_name' => 'required|string|max:100',
            'name' => 'required|string|max:100',
            'status' => 'required|integer|in:1,2',
        ]);

        $provider->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Mobile money provider updated successfully.',
            'data' => $provider,
        ]);
    }

    // DELETE /api/mobile-money-providers/{id}
    public function destroy($id)
    {
        $provider = MobileMoneyProvider::find($id);

        if (!$provider) {
            return response()->json([
                'status' => false,
                'message' => 'Mobile money provider not found.',
            ], 404);
        }

        $provider->delete();

        return response()->json([
            'status' => true,
            'message' => 'Mobile money provider deleted successfully.',
        ]);
    }
}
