<?php

namespace App\Services;

use App\Models\MpesaTransaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MpesaService
{
    protected $consumerKey;

    protected $consumerSecret;

    protected $passkey;

    protected $shortcode;

    protected $environment;

    protected $baseUrl;

    public function __construct()
    {
        $this->consumerKey = config('services.mpesa.consumer_key');
        $this->consumerSecret = config('services.mpesa.consumer_secret');
        $this->passkey = config('services.mpesa.passkey');
        $this->shortcode = config('services.mpesa.shortcode');
        $this->environment = config('services.mpesa.environment', 'sandbox');

        $this->baseUrl = $this->environment === 'production'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';
    }

    /**
     * Generate access token
     */
    public function getAccessToken()
    {
        $url = "{$this->baseUrl}/oauth/v1/generate?grant_type=client_credentials";

        $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
            ->get($url);

        if ($response->successful()) {
            return $response->json()['access_token'];
        }

        throw new \Exception('Failed to get M-Pesa access token: '.$response->body());
    }

    /**
     * Initiate STK Push (Lipa Na M-Pesa Online)
     */
    public function stkPush(string $phone, float $amount, string $accountReference, ?string $description = null)
    {
        $token = $this->getAccessToken();
        $timestamp = now()->format('YmdHis');
        $password = base64_encode($this->shortcode.$this->passkey.$timestamp);

        // Format phone number (remove leading 0 or +254, add 254)
        $phone = $this->formatPhoneNumber($phone);

        $url = "{$this->baseUrl}/mpesa/stkpush/v1/processrequest";

        $payload = [
            'BusinessShortCode' => $this->shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => (int) $amount,
            'PartyA' => $phone,
            'PartyB' => $this->shortcode,
            'PhoneNumber' => $phone,
            'CallBackURL' => route('api.mpesa.callback'),
            'AccountReference' => $accountReference,
            'TransactionDesc' => $description ?? "Payment for {$accountReference}",
        ];

        $response = Http::withToken($token)
            ->post($url, $payload);

        if ($response->successful()) {
            $data = $response->json();

            // Log transaction
            $transaction = MpesaTransaction::create([
                'merchant_request_id' => $data['MerchantRequestID'],
                'checkout_request_id' => $data['CheckoutRequestID'],
                'phone_number' => $phone,
                'amount' => $amount,
                'account_reference' => $accountReference,
                'transaction_type' => 'STK_PUSH',
                'status' => 'pending',
                'request_data' => json_encode($payload),
                'response_data' => json_encode($data),
            ]);

            return [
                'success' => true,
                'message' => 'STK Push sent successfully',
                'data' => $transaction,
            ];
        }

        Log::error('M-Pesa STK Push failed', [
            'response' => $response->body(),
            'status' => $response->status(),
        ]);

        throw new \Exception('Failed to initiate STK Push: '.$response->body());
    }

    /**
     * Process STK Push callback
     */
    public function handleCallback(array $callbackData)
    {
        $body = $callbackData['Body']['stkCallback'];
        $merchantRequestId = $body['MerchantRequestID'];
        $checkoutRequestId = $body['CheckoutRequestID'];
        $resultCode = $body['ResultCode'];

        $transaction = MpesaTransaction::where('checkout_request_id', $checkoutRequestId)->first();

        if (! $transaction) {
            Log::error('M-Pesa callback for unknown transaction', ['data' => $callbackData]);

            return;
        }

        if ($resultCode == 0) {
            // Successful transaction
            $metadata = $body['CallbackMetadata']['Item'];
            $mpesaData = [];

            foreach ($metadata as $item) {
                $mpesaData[$item['Name']] = $item['Value'] ?? null;
            }

            $transaction->update([
                'status' => 'completed',
                'mpesa_receipt_number' => $mpesaData['MpesaReceiptNumber'] ?? null,
                'transaction_date' => isset($mpesaData['TransactionDate'])
                    ? \Carbon\Carbon::createFromFormat('YmdHis', $mpesaData['TransactionDate'])
                    : now(),
                'phone_number' => $mpesaData['PhoneNumber'] ?? $transaction->phone_number,
                'response_data' => json_encode($callbackData),
            ]);

            // Trigger event for further processing (e.g., update sale payment)
            event(new \App\Events\MpesaPaymentReceived($transaction));

        } else {
            // Failed transaction
            $transaction->update([
                'status' => 'failed',
                'result_desc' => $body['ResultDesc'] ?? 'Transaction failed',
                'response_data' => json_encode($callbackData),
            ]);
        }

        return $transaction;
    }

    /**
     * Query STK Push transaction status
     */
    public function queryStkPush(string $checkoutRequestId)
    {
        $token = $this->getAccessToken();
        $timestamp = now()->format('YmdHis');
        $password = base64_encode($this->shortcode.$this->passkey.$timestamp);

        $url = "{$this->baseUrl}/mpesa/stkpushquery/v1/query";

        $payload = [
            'BusinessShortCode' => $this->shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'CheckoutRequestID' => $checkoutRequestId,
        ];

        $response = Http::withToken($token)
            ->post($url, $payload);

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception('Failed to query STK Push: '.$response->body());
    }

    /**
     * B2C Payment (Business to Customer)
     */
    public function b2c(string $phone, float $amount, string $occasion, string $commandId = 'BusinessPayment')
    {
        $token = $this->getAccessToken();
        $phone = $this->formatPhoneNumber($phone);

        $url = "{$this->baseUrl}/mpesa/b2c/v1/paymentrequest";

        $payload = [
            'InitiatorName' => config('services.mpesa.initiator_name'),
            'SecurityCredential' => $this->getSecurityCredential(),
            'CommandID' => $commandId, // BusinessPayment, SalaryPayment, PromotionPayment
            'Amount' => (int) $amount,
            'PartyA' => $this->shortcode,
            'PartyB' => $phone,
            'Remarks' => $occasion,
            'QueueTimeOutURL' => route('api.mpesa.timeout'),
            'ResultURL' => route('api.mpesa.callback'),
            'Occasion' => $occasion,
        ];

        $response = Http::withToken($token)
            ->post($url, $payload);

        if ($response->successful()) {
            $data = $response->json();

            $transaction = MpesaTransaction::create([
                'conversation_id' => $data['ConversationID'],
                'originator_conversation_id' => $data['OriginatorConversationID'],
                'phone_number' => $phone,
                'amount' => $amount,
                'transaction_type' => 'B2C',
                'status' => 'pending',
                'request_data' => json_encode($payload),
                'response_data' => json_encode($data),
            ]);

            return [
                'success' => true,
                'message' => 'B2C payment initiated successfully',
                'data' => $transaction,
            ];
        }

        throw new \Exception('Failed to initiate B2C payment: '.$response->body());
    }

    /**
     * Check account balance
     */
    public function accountBalance()
    {
        $token = $this->getAccessToken();

        $url = "{$this->baseUrl}/mpesa/accountbalance/v1/query";

        $payload = [
            'Initiator' => config('services.mpesa.initiator_name'),
            'SecurityCredential' => $this->getSecurityCredential(),
            'CommandID' => 'AccountBalance',
            'PartyA' => $this->shortcode,
            'IdentifierType' => '4', // 4 for organization shortcode
            'Remarks' => 'Account Balance Query',
            'QueueTimeOutURL' => route('api.mpesa.timeout'),
            'ResultURL' => route('api.mpesa.callback'),
        ];

        $response = Http::withToken($token)
            ->post($url, $payload);

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception('Failed to check account balance: '.$response->body());
    }

    /**
     * Format phone number to 254XXXXXXXXX
     */
    protected function formatPhoneNumber(string $phone): string
    {
        // Remove any spaces, dashes, or plus signs
        $phone = preg_replace('/[\s\-\+]/', '', $phone);

        // Remove leading zero if present
        if (substr($phone, 0, 1) === '0') {
            $phone = substr($phone, 1);
        }

        // Add 254 if not present
        if (substr($phone, 0, 3) !== '254') {
            $phone = '254'.$phone;
        }

        return $phone;
    }

    /**
     * Get security credential (encrypted initiator password)
     */
    protected function getSecurityCredential(): string
    {
        // In production, encrypt the initiator password with M-Pesa public key
        // For sandbox, you can use the test credential
        return config('services.mpesa.security_credential');
    }

    /**
     * Reconcile M-Pesa transactions with sales
     */
    public function reconcileTransaction(MpesaTransaction $transaction, $recordId, $recordType = 'sale')
    {
        $transaction->update([
            'reconciled' => true,
            'reconciled_at' => now(),
            'record_id' => $recordId,
            'record_type' => $recordType,
        ]);

        return $transaction;
    }
}
