<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MpesaTransaction;
use App\Services\MpesaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MpesaController extends Controller
{
    protected $mpesaService;

    public function __construct(MpesaService $mpesaService)
    {
        $this->mpesaService = $mpesaService;
    }

    /**
     * Initiate STK Push
     */
    public function stkPush(Request $request)
    {
        $validated = $request->validate([
            'phone' => 'required|string',
            'amount' => 'required|numeric|min:1',
            'account_reference' => 'required|string|max:50',
            'description' => 'nullable|string|max:100',
        ]);

        try {
            $result = $this->mpesaService->stkPush(
                $validated['phone'],
                $validated['amount'],
                $validated['account_reference'],
                $validated['description'] ?? null
            );

            return response()->json($result, 201);

        } catch (\Exception $e) {
            Log::error('M-Pesa STK Push Error', [
                'error' => $e->getMessage(),
                'data' => $validated,
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Initiate B2C payment
     */
    public function b2c(Request $request)
    {
        $validated = $request->validate([
            'phone' => 'required|string',
            'amount' => 'required|numeric|min:10',
            'occasion' => 'required|string|max:100',
            'command_id' => 'nullable|in:BusinessPayment,SalaryPayment,PromotionPayment',
        ]);

        try {
            $result = $this->mpesaService->b2c(
                $validated['phone'],
                $validated['amount'],
                $validated['occasion'],
                $validated['command_id'] ?? 'BusinessPayment'
            );

            return response()->json($result, 201);

        } catch (\Exception $e) {
            Log::error('M-Pesa B2C Error', [
                'error' => $e->getMessage(),
                'data' => $validated,
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * M-Pesa callback handler
     */
    public function callback(Request $request)
    {
        Log::info('M-Pesa Callback Received', $request->all());

        try {
            $transaction = $this->mpesaService->handleCallback($request->all());

            return response()->json([
                'ResultCode' => 0,
                'ResultDesc' => 'Success',
            ]);

        } catch (\Exception $e) {
            Log::error('M-Pesa Callback Processing Error', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
            ]);

            return response()->json([
                'ResultCode' => 1,
                'ResultDesc' => 'Failed to process callback',
            ]);
        }
    }

    /**
     * M-Pesa timeout handler
     */
    public function timeout(Request $request)
    {
        Log::info('M-Pesa Timeout', $request->all());

        // Update transaction as timed out
        if (isset($request->CheckoutRequestID)) {
            MpesaTransaction::where('checkout_request_id', $request->CheckoutRequestID)
                ->update([
                    'status' => 'timeout',
                    'result_desc' => 'Request timed out',
                ]);
        }

        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Success',
        ]);
    }

    /**
     * M-Pesa validation (C2B)
     */
    public function validation(Request $request)
    {
        Log::info('M-Pesa Validation Request', $request->all());

        // Validate the transaction
        // Return 0 to accept, 1 to reject

        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Accepted',
        ]);
    }

    /**
     * M-Pesa confirmation (C2B)
     */
    public function confirmation(Request $request)
    {
        Log::info('M-Pesa Confirmation', $request->all());

        try {
            // Record C2B transaction
            MpesaTransaction::create([
                'transaction_type' => 'C2B',
                'trans_id' => $request->TransID,
                'trans_time' => $request->TransTime,
                'trans_amount' => $request->TransAmount,
                'business_short_code' => $request->BusinessShortCode,
                'bill_ref_number' => $request->BillRefNumber,
                'invoice_number' => $request->InvoiceNumber ?? null,
                'org_account_balance' => $request->OrgAccountBalance ?? null,
                'third_party_trans_id' => $request->ThirdPartyTransID ?? null,
                'msisdn' => $request->MSISDN,
                'first_name' => $request->FirstName ?? null,
                'middle_name' => $request->MiddleName ?? null,
                'last_name' => $request->LastName ?? null,
                'status' => 'completed',
            ]);

            return response()->json([
                'ResultCode' => 0,
                'ResultDesc' => 'Success',
            ]);

        } catch (\Exception $e) {
            Log::error('M-Pesa Confirmation Error', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
            ]);

            return response()->json([
                'ResultCode' => 1,
                'ResultDesc' => 'Failed',
            ]);
        }
    }

    /**
     * Get M-Pesa transactions
     */
    public function transactions(Request $request)
    {
        $query = MpesaTransaction::query();

        // Filter by type
        if ($request->has('transaction_type')) {
            $query->where('transaction_type', $request->transaction_type);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Date range
        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        // Search by phone or reference
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('phone_number', 'like', "%{$search}%")
                    ->orWhere('account_reference', 'like', "%{$search}%")
                    ->orWhere('mpesa_receipt_number', 'like', "%{$search}%");
            });
        }

        $query->orderBy('created_at', 'desc');

        $perPage = $request->get('per_page', 15);
        $transactions = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $transactions,
        ]);
    }

    /**
     * Reconcile M-Pesa transaction with sale
     */
    public function reconcile(Request $request)
    {
        $validated = $request->validate([
            'transaction_id' => 'required|exists:mpesa_transactions,id',
            'record_id' => 'required|integer',
            'record_type' => 'required|in:sale,invoice,payment',
        ]);

        $transaction = MpesaTransaction::findOrFail($validated['transaction_id']);

        $this->mpesaService->reconcileTransaction(
            $transaction,
            $validated['record_id'],
            $validated['record_type']
        );

        return response()->json([
            'success' => true,
            'message' => 'Transaction reconciled successfully',
            'data' => $transaction->fresh(),
        ]);
    }

    /**
     * Check M-Pesa account balance
     */
    public function accountBalance()
    {
        try {
            $result = $this->mpesaService->accountBalance();

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
