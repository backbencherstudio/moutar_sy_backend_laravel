<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExchangeRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;


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

    

   public function store(Request $request)
    {
       
        $request->validate([
            'from_country'      => 'required|string',   
            'from_country_flag' => 'required|image|mimes:jpg,jpeg,png,svg|max:2048',
            'from_currency'     => 'required|string|size:3',   
            'to_country'        => 'required|string',
            'to_country_flag'   => 'required|image|mimes:jpg,jpeg,png,svg|max:2048',
            'to_currency'       => 'required|string|size:3',  
            'customer_rate'     => 'required|numeric|min:0',
            'fixed_fee'         => 'nullable|numeric|min:0',
            'status'            => 'nullable|in:0,1,true,false', 
        ]);

      
        $fromFlagPath = null;
        if ($request->hasFile('from_country_flag')) {
            $fromFlag = $request->file('from_country_flag');
            $fromFileName = time() . '_from_' . uniqid() . '.' . $fromFlag->getClientOriginalExtension();
            $fromFlag->move(public_path('uploads/flags'), $fromFileName);
            $fromFlagPath = 'uploads/flags/' . $fromFileName;
        }

    
        $toFlagPath = null;
        if ($request->hasFile('to_country_flag')) {
            $toFlag = $request->file('to_country_flag');
            $toFileName = time() . '_to_' . uniqid() . '.' . $toFlag->getClientOriginalExtension();
            $toFlag->move(public_path('uploads/flags'), $toFileName);
            $toFlagPath = 'uploads/flags/' . $toFileName;
        }

        $status = $request->has('status') ? filter_var($request->status, FILTER_VALIDATE_BOOLEAN) : true;

  
        $rate = ExchangeRate::create([
            'from_country'      => $request->from_country,
            'from_country_flag' => $fromFlagPath,
            'from_currency'     => strtoupper($request->from_currency),
            
            'to_country'        => $request->to_country,
            'to_country_flag'   => $toFlagPath,
            'to_currency'       => strtoupper($request->to_currency),
            
            'customer_rate'     => $request->customer_rate,
            'fixed_fee'         => $request->fixed_fee ?? 0.00,
            'status'            => $status,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Exchange rate created successfully.',
            'data'    => $rate,
        ], 201);
    }

  //calculate the converted amount based on the provided exchange rate and fixed fee
    public function calculate(Request $request)
    {
    
        $request->validate([
            'from_currency' => 'required|string|size:3',
            'to_currency'   => 'required|string|size:3',
            'amount'        => 'required|numeric|min:0.01',
        ]);

        $fromCurrency = strtoupper($request->from_currency);
        $toCurrency   = strtoupper($request->to_currency);
        $amount       = $request->amount;

        $exchangeRate = ExchangeRate::where('from_currency', $fromCurrency)
                                    ->where('to_currency', $toCurrency)
                                    ->where('status', true)
                                    ->first();

        if (!$exchangeRate) {
            return response()->json([
                'success' => false,
                'message' => "Exchange rate not found or inactive for {$fromCurrency} to {$toCurrency}."
            ], 404);
        }

        $customerRate = $exchangeRate->customer_rate;
        $fixedFee     = $exchangeRate->fixed_fee;

        $convertedAmount = ($amount * $customerRate) + $fixedFee;
    
        return response()->json([
            'success' => true,
            'data' => [
                'from_currency'    => $fromCurrency,
                'to_currency'      => $toCurrency,
                'amount'           => $amount,
                'customer_rate'    => $customerRate,
                'fixed_fee'        => $fixedFee,
                'converted_amount' => round($convertedAmount, 4), 
                'formula'          => "(Amount * Rate) + Fee"
            ]
        ], 200);
    }

    public function edit($id)
    {
        $rate = ExchangeRate::find($id);

        if (!$rate) {
            return response()->json([
                'success' => false,
                'message' => 'Exchange rate not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $rate,
        ], 200);
    }

    public function update(Request $request, $id)
    {
    
        $rate = ExchangeRate::find($id);

        if (!$rate) {
            return response()->json([
                'success' => false,
                'message' => 'Exchange rate not found.',
            ], 404);
        }

        $request->validate([
            'from_country'      => 'required|string',   
            'from_country_flag' => 'nullable|image|mimes:jpg,jpeg,png,svg|max:2048',
            'from_currency'     => 'required|string|size:3',   
            'to_country'        => 'required|string',
            'to_country_flag'   => 'nullable|image|mimes:jpg,jpeg,png,svg|max:2048',
            'to_currency'       => 'required|string|size:3',  
            'customer_rate'     => 'required|numeric|min:0',
            'fixed_fee'         => 'nullable|numeric|min:0',
            'status'            => 'nullable|in:0,1,true,false', 
        ]);

    
        $fromFlagPath = $rate->from_country_flag;
        if ($request->hasFile('from_country_flag')) {
        
            if ($fromFlagPath && File::exists(public_path($fromFlagPath))) {
                File::delete(public_path($fromFlagPath));
            }
            
            $fromFlag = $request->file('from_country_flag');
            $fromFileName = time() . '_from_' . uniqid() . '.' . $fromFlag->getClientOriginalExtension();
            $fromFlag->move(public_path('uploads/flags'), $fromFileName);
            $fromFlagPath = 'uploads/flags/' . $fromFileName;
        }

    
        $toFlagPath = $rate->to_country_flag; 
        if ($request->hasFile('to_country_flag')) {
        
            if ($toFlagPath && File::exists(public_path($toFlagPath))) {
                File::delete(public_path($toFlagPath));
            }

            $toFlag = $request->file('to_country_flag');
            $toFileName = time() . '_to_' . uniqid() . '.' . $toFlag->getClientOriginalExtension();
            $toFlag->move(public_path('uploads/flags'), $toFileName);
            $toFlagPath = 'uploads/flags/' . $toFileName;
        }


        $status = $request->has('status') ? filter_var($request->status, FILTER_VALIDATE_BOOLEAN) : $rate->status;

        
        $rate->update([
            'from_country'      => $request->from_country,
            'from_country_flag' => $fromFlagPath,
            'from_currency'     => strtoupper($request->from_currency),
            
            'to_country'        => $request->to_country,
            'to_country_flag'   => $toFlagPath,
            'to_currency'       => strtoupper($request->to_currency),
            
            'customer_rate'     => $request->customer_rate,
            'fixed_fee'         => $request->fixed_fee ?? 0.00,
            'status'            => $status,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Exchange rate updated successfully.',
            'data'    => $rate,
        ], 200);
    }

    public function destroy($id)
    {
    
        $rate = ExchangeRate::find($id);

        if (!$rate) {
            return response()->json([
                'success' => false,
                'message' => 'Exchange rate not found.',
            ], 404);
        }
        if ($rate->from_country_flag && File::exists(public_path($rate->from_country_flag))) {
            File::delete(public_path($rate->from_country_flag));
        }

        if ($rate->to_country_flag && File::exists(public_path($rate->to_country_flag))) {
            File::delete(public_path($rate->to_country_flag));
        }
        $rate->delete();
        return response()->json([
            'success' => true,
            'message' => 'Exchange rate and associated flags deleted successfully.'
        ], 200);
    }

    
}