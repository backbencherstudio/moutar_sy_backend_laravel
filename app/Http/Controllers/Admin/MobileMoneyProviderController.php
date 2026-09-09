<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MobileMoneyProvider;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

    public function store(Request $request)
    {
        $allowedCountries = [
            'Burkina Faso',
            'Benin',
            "Côte d'Ivoire",
            'Cameroon',
            'Ghana',
            'Guinea',
            'Kenya',
            'Mali',
            'Niger',
            'DRC',
            'Sierra Leone',
            'Senegal',
            'Togo',
            'Uganda',
        ];

        $validated = $request->validate([
            'country_name' => [
                'required',
                'string',
                Rule::in($allowedCountries),
            ],
            'name' => [
                'required',
                'string',
                'max:100',
                'unique:mobile_money_providers,name',
            ],
        ]);

        $validated['status'] = 1;

        $provider = MobileMoneyProvider::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Mobile money provider created successfully.',
            'data' => $provider,
        ], 201);
    }

    public function show($id)
    {
        $provider = MobileMoneyProvider::find($id);

        if (! $provider) {
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

    public function update(Request $request, $id)
    {
        $provider = MobileMoneyProvider::find($id);

        if (! $provider) {
            return response()->json([
                'status' => false,
                'message' => 'Mobile money provider not found.',
            ], 404);
        }

        $allowedCountries = [
            'Burkina Faso',
            'Benin',
            "Côte d'Ivoire",
            'Cameroon',
            'Ghana',
            'Guinea',
            'Kenya',
            'Mali',
            'Niger',
            'DRC',
            'Sierra Leone',
            'Senegal',
            'Togo',
            'Uganda',
        ];

        $validated = $request->validate([
            'country_name' => ['sometimes', 'required', 'string', Rule::in($allowedCountries)],
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'status' => ['sometimes', 'required', 'integer', 'in:0,1'],
        ]);

        $provider->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Mobile money provider updated successfully.',
            'data' => $provider,
        ], 200);
    }

    // DELETE /api/mobile-money-providers/{id}
    public function destroy($id)
    {
        $provider = MobileMoneyProvider::find($id);

        if (! $provider) {
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
