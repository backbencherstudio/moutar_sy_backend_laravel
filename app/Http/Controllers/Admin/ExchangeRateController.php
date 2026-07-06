<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExchangeRate;
use Illuminate\Http\Request;

class ExchangeRateController extends Controller
{
    public function index()
    {
        $rates = ExchangeRate::latest()->get();

        return response()->json([
            'success' => true,
            'data' => $rates,
        ], 200);
    }

    // public function calculate(Request $request)
    // {
    //     $request->validate([
    //         'amount' => 'required|numeric|min:1',
    //         'to_country' => 'required|string',
    //         'to_currency' => 'required|string',
    //     ]);

    //     $rate = ExchangeRate::where('to_country', $request->to_country)
    //         ->where('to_currency', $request->to_currency)
    //         ->where('status', 1)
    //         ->first();

    //     if (!$rate) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Exchange rate not found.'
    //         ], 404);
    //     }

    //     // User amount × Exchange Rate
    //     $convertedAmount = $request->amount * $rate->customer_rate;

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Exchange calculated successfully.',
    //         'data' => [
    //             'amount'            => $request->amount,
    //             'exchange_rate'     => $rate->customer_rate,
    //             'converted_amount'  => round($convertedAmount, 2),
    //             'rate_text'         => "1 {$rate->from_currency} = {$rate->customer_rate} {$rate->to_currency}"
    //         ]
    //     ]);
    // }
    public function calculate(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'to_country' => 'required|string',
            'to_currency' => 'required|string',
        ]);

        $rate = ExchangeRate::where('to_country', $request->to_country)
            ->where('to_currency', $request->to_currency)
            ->where('status', 1)
            ->first();

        if (! $rate) {
            return response()->json([
                'success' => false,
                'message' => 'Exchange rate not found.',
            ], 404);
        }

        // Exchange Amount
        $convertedAmount = $request->amount * $rate->customer_rate;

        // Fees
        $fixedFee = $rate->fixed_fee ?? 0;
        $percentageFee = ($request->amount * ($rate->percentage_fee ?? 0)) / 100;

        // Total Fee
        $totalFee = $fixedFee + $percentageFee;

        // User Pays
        $totalPayable = $request->amount + $totalFee;

        return response()->json([
            'success' => true,
            'message' => 'Exchange calculated successfully.',
            'data' => [
                'amount' => $request->amount,
                'exchange_rate' => $rate->customer_rate,
                'converted_amount' => round($convertedAmount, 2),

                'fixed_fee' => round($fixedFee, 2),
                'percentage_fee' => round($percentageFee, 2),
                // 'total_fee' => round($totalFee, 2),
                // 'total_payable' => round($totalPayable, 2),

                'rate_text' => "1 {$rate->from_currency} = {$rate->customer_rate} {$rate->to_currency}",
            ],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'to_country' => 'required|string|unique:exchange_rates,to_country',
            'to_currency' => 'required|string|max:3',
            'customer_rate' => 'required|numeric|min:0',
            'fixed_fee' => 'nullable|numeric|min:0',
            'percentage_fee' => 'nullable|numeric|min:0',
            'charge_type' => 'required|in:fixed,percentage,both',
            'status' => 'nullable|boolean',
        ]);

        $rate = ExchangeRate::create([
            'from_currency' => $request->from_currency,
            'to_country' => $request->to_country,
            'to_currency' => strtoupper($request->to_currency),
            'customer_rate' => $request->customer_rate,
            'fixed_fee' => $request->fixed_fee ?? 0.00,
            'percentage_fee' => $request->percentage_fee ?? 0.00,
            'charge_type' => $request->charge_type,
            'status' => $request->status ?? 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Exchange rate created successfully',
            'data' => $rate,
        ], 201);
    }

    public function edit($id)
    {
        $rate = ExchangeRate::find($id);

        if (! $rate) {
            return response()->json(['success' => false, 'message' => 'Rate not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $rate], 200);
    }

    public function update(Request $request, $id)
    {
        $rate = ExchangeRate::find($id);

        if (! $rate) {
            return response()->json(['success' => false, 'message' => 'Rate not found'], 404);
        }

        $request->validate([
            'to_country' => 'required|string|unique:exchange_rates,to_country,'.$id,
            'to_currency' => 'required|string|max:3',
            'customer_rate' => 'required|numeric|min:0',
            'fixed_fee' => 'nullable|numeric|min:0',
            'percentage_fee' => 'nullable|numeric|min:0',
            'charge_type' => 'required|in:fixed,percentage,both',
            'status' => 'required|boolean',
        ]);

        $rate->update([
            'from_currency' => $request->from_currency ?? 'EUR',
            'to_country' => $request->to_country,
            'to_currency' => strtoupper($request->to_currency),
            'customer_rate' => $request->customer_rate,
            'fixed_fee' => $request->fixed_fee ?? 0.00,
            'percentage_fee' => $request->percentage_fee ?? 0.00,
            'charge_type' => $request->charge_type,
            'status' => $request->status,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Exchange rate updated successfully',
            'data' => $rate,
        ], 200);
    }

    public function destroy($id)
    {
        $rate = ExchangeRate::find($id);

        if (! $rate) {
            return response()->json(['success' => false, 'message' => 'Rate not found'], 404);
        }

        $rate->delete();

        return response()->json([
            'success' => true,
            'message' => 'Exchange rate deleted successfully',
        ], 200);
    }
}
