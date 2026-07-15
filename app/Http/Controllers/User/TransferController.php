<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\GatewayLog;
use App\Models\Transaction;
use App\Models\Transfer;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Stripe\StripeClient;

class TransferController extends Controller
{
    protected StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    /**
     * Store and process a new transfer transaction.
     */
    public function store(Request $request)
    {
        // 1. Validation
        $validator = Validator::make($request->all(), [
            'beneficiary_id'    => 'required|integer|exists:beneficiaries,id',
            'amount'            => 'required|numeric|gt:0',
            'currency'          => 'required|string|in:EUR,USD,CAD,eur,usd,cad',
            'payment_method_id' => 'required|string',
            'operator_code'     => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $payoutAmount = round((float) $request->amount, 2);
        $currency = strtoupper($request->currency);

        // 2% Fee calculation using precise rounding
        $commissionRate = 0.02;
        $feeAmount = round($payoutAmount * $commissionRate, 2);
        $totalChargedAmount = $payoutAmount + $feeAmount;

        DB::beginTransaction();
        try {
            // 2. Create initiated transfer
            $transfer = Transfer::create([
                'user_id'              => $user->id,
                'beneficiary_id'       => $request->beneficiary_id,
                'payout_method'        => 'stripe',
                'operator_code'        => $request->operator_code,
                'payout_amount'        => $payoutAmount,
                'payout_currency'      => strtolower($currency),
                'gateway_reference_id' => null,
                'status'               => 'initiated',
            ]);

            $userOpeningBalance = $user->wallet_balance ?? 0.00;

            // 3. Create main transaction record
            $mainTransaction = Transaction::create([
                'transaction_number' => 'TXN-' . strtoupper(uniqid()),
                'user_id'            => $user->id,
                'reference_number'   => 'REF-' . time(),
                'transfer_id'        => $transfer->id,
                'type'               => 'debit',
                'purpose'            => 'money_transfer',
                'amount'             => $payoutAmount,
                'currency'           => $currency,
                'opening_balance'    => $userOpeningBalance,
                'closing_balance'    => $userOpeningBalance - $payoutAmount,
                'status'             => 'pending',
                'remarks'            => 'Transfer payout amount',
            ]);

            // 4. Create fee transaction record
            Transaction::create([
                'transaction_number' => 'FEE-' . strtoupper(uniqid()),
                'user_id'            => $user->id,
                'reference_number'   => $mainTransaction->reference_number,
                'transfer_id'        => $transfer->id,
                'type'               => 'debit',
                'purpose'            => 'transfer_fee',
                'amount'             => $feeAmount,
                'currency'           => $currency,
                'opening_balance'    => $userOpeningBalance - $payoutAmount,
                'closing_balance'    => $userOpeningBalance - $totalChargedAmount,
                'status'             => 'pending',
                'remarks'            => '2% Platform fee charge',
            ]);

            // 5. Stripe API PaymentIntent creation
            try {
                $paymentIntent = $this->stripe->paymentIntents->create([
                    'amount'   => (int) round($totalChargedAmount * 100), // Converted cleanly to cents
                    'currency' => strtolower($currency),
                    'payment_method' => $request->payment_method_id,
                    'confirm'  => true,
                    'automatic_payment_methods' => [
                        'enabled' => true,
                        'allow_redirects' => 'never',
                    ],
                    'metadata' => [
                        'transfer_id'     => $transfer->id,
                        'transaction_id'  => $mainTransaction->id,
                        'payout_amount'   => $payoutAmount,
                        'admin_fee'       => $feeAmount,
                    ],
                ]);

                // 6. Save Gateway Log
                GatewayLog::create([
                    'transaction_id'   => $mainTransaction->id,
                    'gateway_name'     => 'stripe',
                    'api_endpoint'     => 'paymentIntents.create',
                    'request_payload'  => json_encode($request->all()),
                    'response_payload' => json_encode($paymentIntent),
                    'http_status_code' => 200,
                ]);

                // 7. Update transfer status upon successful payment intent creation
                $transfer->update([
                    'gateway_reference_id' => $paymentIntent->id,
                    'status'               => 'processing',
                    'processed_at'         => now(),
                ]);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Transfer processing started',
                    'data'    => [
                        'transfer_id'        => $transfer->id,
                        'transaction_number' => $mainTransaction->transaction_number,
                        'payout_amount'      => $payoutAmount,
                        'fee'                => $feeAmount,
                        'total_charged'      => $totalChargedAmount,
                        'currency'           => $currency,
                        'status'             => $transfer->status,
                        'client_secret'      => $paymentIntent->client_secret,
                    ]
                ], 201);

            } catch (Exception $stripeError) {
                // Mark DB records as failed if Stripe payment rejects/fails
                $transfer->update(['status' => 'failed']);
                
                Transaction::where('transfer_id', $transfer->id)->update([
                    'status' => 'failed',
                ]);

                GatewayLog::create([
                    'transaction_id'   => $mainTransaction->id,
                    'gateway_name'     => 'stripe',
                    'api_endpoint'     => 'paymentIntents.create',
                    'request_payload'  => json_encode($request->all()),
                    'response_payload' => json_encode(['error' => $stripeError->getMessage()]),
                    'http_status_code' => $stripeError->getCode() ?: 400,
                ]);

                DB::commit();

                return response()->json([
                    'success' => false,
                    'message' => 'Gateway Error: ' . $stripeError->getMessage(),
                ], 400);
            }

        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'System Error: ' . $e->getMessage(),
            ], 500);
        }
    }
}
