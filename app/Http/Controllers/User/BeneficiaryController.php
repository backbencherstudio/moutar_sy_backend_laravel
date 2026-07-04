<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Beneficiary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BeneficiaryController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone_number' => 'nullable|string|max:30',
            'country_code' => 'required|string|max:5',
            'city' => 'nullable|string|max:255',
            'transfer_type' => 'required|in:bank,mobile_wallet',
            'bank_or_wallet_name' => 'nullable|string|max:255',
            'account_or_wallet_number' => 'required|string|max:255',
            'branch_name' => 'nullable|string|max:255',
            'routing_number' => 'nullable|string|max:255',
            'swift_code' => 'nullable|string|max:255',
        ]);

        $beneficiary = Beneficiary::create([
            'user_id' => Auth::id(),
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'phone_number' => $validated['phone_number'] ?? null,
            'country_code' => strtoupper($validated['country_code']),
            'city' => $validated['city'] ?? null,
            'transfer_type' => $validated['transfer_type'],
            'bank_or_wallet_name' => $validated['bank_or_wallet_name'] ?? null,
            'account_or_wallet_number' => $validated['account_or_wallet_number'],
            'branch_name' => $validated['branch_name'] ?? null,
            'routing_number' => $validated['routing_number'] ?? null,
            'swift_code' => $validated['swift_code'] ?? null,
            'status' => 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Beneficiary created successfully.',
            'data' => $beneficiary,
        ], 201);
    }
}
