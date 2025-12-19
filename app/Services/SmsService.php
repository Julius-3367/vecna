<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    protected $username;

    protected $apiKey;

    protected $senderId;

    public function __construct()
    {
        $this->username = config('services.africas_talking.username');
        $this->apiKey = config('services.africas_talking.api_key');
        $this->senderId = config('services.africas_talking.sender_id');
    }

    /**
     * Send SMS to single recipient
     */
    public function send(string $phone, string $message): array
    {
        return $this->sendBulk([$phone], $message);
    }

    /**
     * Send SMS to multiple recipients
     */
    public function sendBulk(array $phones, string $message): array
    {
        $url = 'https://api.africastalking.com/version1/messaging';

        // Format phone numbers
        $recipients = array_map(function ($phone) {
            return $this->formatPhoneNumber($phone);
        }, $phones);

        $payload = [
            'username' => $this->username,
            'to' => implode(',', $recipients),
            'message' => $message,
            'from' => $this->senderId,
        ];

        try {
            $response = Http::withHeaders([
                'apiKey' => $this->apiKey,
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json',
            ])->asForm()->post($url, $payload);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('SMS Sent Successfully', [
                    'recipients' => count($recipients),
                    'response' => $data,
                ]);

                return [
                    'success' => true,
                    'message' => 'SMS sent successfully',
                    'data' => $data,
                ];
            }

            throw new \Exception('Failed to send SMS: '.$response->body());
        } catch (\Exception $e) {
            Log::error('SMS Sending Failed', [
                'error' => $e->getMessage(),
                'recipients' => $recipients,
                'message' => $message,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send sale receipt via SMS
     */
    public function sendSaleReceipt($sale): array
    {
        $customer = $sale->customer;

        if (! $customer || ! $customer->phone) {
            return [
                'success' => false,
                'message' => 'Customer phone number not found',
            ];
        }

        $tenant = tenant();

        $message = "Thank you for your purchase at {$tenant->name}!\n\n";
        $message .= "Receipt: {$sale->sale_number}\n";
        $message .= 'Date: '.$sale->sale_date->format('d/m/Y')."\n";
        $message .= 'Amount: KES '.number_format($sale->total_amount, 2)."\n";
        $message .= 'Paid: KES '.number_format($sale->paid_amount, 2)."\n";

        if ($sale->balance > 0) {
            $message .= 'Balance: KES '.number_format($sale->balance, 2)."\n";
        }

        $message .= "\nThank you for your business!";

        return $this->send($customer->phone, $message);
    }

    /**
     * Send payment reminder
     */
    public function sendPaymentReminder($invoice): array
    {
        $customer = $invoice->customer;

        if (! $customer || ! $customer->phone) {
            return [
                'success' => false,
                'message' => 'Customer phone number not found',
            ];
        }

        $tenant = tenant();

        $message = "Payment Reminder from {$tenant->name}\n\n";
        $message .= "Invoice: {$invoice->invoice_number}\n";
        $message .= 'Amount Due: KES '.number_format($invoice->balance, 2)."\n";
        $message .= 'Due Date: '.$invoice->due_date->format('d/m/Y')."\n";

        if ($invoice->is_overdue) {
            $message .= "Status: OVERDUE ({$invoice->days_overdue} days)\n";
        }

        $message .= "\nPlease make payment at your earliest convenience.";

        return $this->send($customer->phone, $message);
    }

    /**
     * Send stock alert notification
     */
    public function sendStockAlert($product): array
    {
        // Get manager/admin phone numbers
        $managers = \App\Models\User::whereIn('role', ['admin', 'manager'])
            ->where('is_active', true)
            ->pluck('phone')
            ->filter()
            ->toArray();

        if (empty($managers)) {
            return [
                'success' => false,
                'message' => 'No manager contacts found',
            ];
        }

        $message = "STOCK ALERT!\n\n";
        $message .= "Product: {$product->name}\n";
        $message .= "SKU: {$product->sku}\n";
        $message .= "Current Stock: {$product->current_stock}\n";
        $message .= "Minimum Level: {$product->minimum_stock}\n";
        $message .= "\nPlease reorder immediately.";

        return $this->sendBulk($managers, $message);
    }

    /**
     * Send OTP for verification
     */
    public function sendOTP(string $phone, string $otp): array
    {
        $message = "Your verification code is: {$otp}\n\n";
        $message .= "This code will expire in 10 minutes.\n";
        $message .= 'Do not share this code with anyone.';

        return $this->send($phone, $message);
    }

    /**
     * Format phone number to +254XXXXXXXXX
     */
    protected function formatPhoneNumber(string $phone): string
    {
        // Remove any spaces, dashes, or plus signs
        $phone = preg_replace('/[\s\-\+]/', '', $phone);

        // Remove leading zero if present
        if (substr($phone, 0, 1) === '0') {
            $phone = substr($phone, 1);
        }

        // Add +254 if not present
        if (substr($phone, 0, 3) !== '254') {
            $phone = '254'.$phone;
        }

        // Add + prefix
        if (substr($phone, 0, 1) !== '+') {
            $phone = '+'.$phone;
        }

        return $phone;
    }
}
