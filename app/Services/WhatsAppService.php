<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected $apiUrl;

    protected $apiToken;

    protected $fromNumber;

    public function __construct()
    {
        $this->apiUrl = config('services.whatsapp.api_url', 'https://graph.facebook.com/v18.0');
        $this->apiToken = config('services.whatsapp.api_token');
        $this->fromNumber = config('services.whatsapp.from_number');
    }

    /**
     * Send WhatsApp message
     */
    public function sendMessage(string $to, string $message, array $options = [])
    {
        try {
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $this->formatPhoneNumber($to),
                'type' => 'text',
                'text' => [
                    'body' => $message,
                ],
            ];

            $response = Http::withToken($this->apiToken)
                ->post("{$this->apiUrl}/{$this->fromNumber}/messages", $payload);

            if ($response->successful()) {
                Log::info('WhatsApp message sent', [
                    'to' => $to,
                    'message_id' => $response->json('messages.0.id'),
                ]);

                return [
                    'success' => true,
                    'message_id' => $response->json('messages.0.id'),
                    'data' => $response->json(),
                ];
            }

            Log::error('WhatsApp message failed', [
                'to' => $to,
                'error' => $response->json(),
            ]);

            return [
                'success' => false,
                'error' => $response->json('error.message', 'Failed to send message'),
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp exception', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send template message
     */
    public function sendTemplate(string $to, string $templateName, array $parameters = [])
    {
        try {
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $this->formatPhoneNumber($to),
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => [
                        'code' => 'en',
                    ],
                    'components' => $this->buildTemplateComponents($parameters),
                ],
            ];

            $response = Http::withToken($this->apiToken)
                ->post("{$this->apiUrl}/{$this->fromNumber}/messages", $payload);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message_id' => $response->json('messages.0.id'),
                ];
            }

            return [
                'success' => false,
                'error' => $response->json('error.message', 'Failed to send template'),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send order confirmation
     */
    public function sendOrderConfirmation(string $phone, array $orderDetails)
    {
        $message = "✅ *Order Confirmed*\n\n";
        $message .= "Order #: {$orderDetails['order_number']}\n";
        $message .= 'Total: KES '.number_format($orderDetails['total'], 2)."\n";
        $message .= "Status: {$orderDetails['status']}\n\n";

        if (! empty($orderDetails['items'])) {
            $message .= "Items:\n";
            foreach ($orderDetails['items'] as $item) {
                $message .= "• {$item['name']} x{$item['quantity']}\n";
            }
        }

        $message .= "\nThank you for your order!";

        return $this->sendMessage($phone, $message);
    }

    /**
     * Send payment reminder
     */
    public function sendPaymentReminder(string $phone, array $invoiceDetails)
    {
        $message = "💰 *Payment Reminder*\n\n";
        $message .= "Invoice #: {$invoiceDetails['invoice_number']}\n";
        $message .= 'Amount: KES '.number_format($invoiceDetails['amount'], 2)."\n";
        $message .= "Due Date: {$invoiceDetails['due_date']}\n\n";
        $message .= 'Please make payment to avoid service interruption.';

        return $this->sendMessage($phone, $message);
    }

    /**
     * Send daily business snapshot
     */
    public function sendDailySnapshot(string $phone, array $stats)
    {
        $message = "📊 *Daily Business Snapshot*\n";
        $message .= 'Date: '.now()->format('d M Y')."\n\n";

        $message .= '💰 Sales: KES '.number_format($stats['sales'] ?? 0, 2)."\n";
        $message .= '📦 Orders: '.($stats['orders'] ?? 0)."\n";
        $message .= '👥 Customers: '.($stats['customers'] ?? 0)."\n";
        $message .= '📈 Profit: KES '.number_format($stats['profit'] ?? 0, 2)."\n\n";

        if (! empty($stats['alerts'])) {
            $message .= "⚠️ Alerts:\n";
            foreach ($stats['alerts'] as $alert) {
                $message .= "• {$alert}\n";
            }
        } else {
            $message .= '✅ No alerts today';
        }

        return $this->sendMessage($phone, $message);
    }

    /**
     * Send low stock alert
     */
    public function sendLowStockAlert(string $phone, array $products)
    {
        $message = "⚠️ *Low Stock Alert*\n\n";
        $message .= "The following items are running low:\n\n";

        foreach ($products as $product) {
            $message .= "• {$product['name']}\n";
            $message .= "  Current: {$product['current_stock']} {$product['unit']}\n";
            $message .= "  Minimum: {$product['minimum_stock']} {$product['unit']}\n\n";
        }

        $message .= 'Please reorder soon to avoid stockouts.';

        return $this->sendMessage($phone, $message);
    }

    /**
     * Send delivery update
     */
    public function sendDeliveryUpdate(string $phone, array $deliveryInfo)
    {
        $message = "🚚 *Delivery Update*\n\n";
        $message .= "Order #: {$deliveryInfo['order_number']}\n";
        $message .= "Status: {$deliveryInfo['status']}\n";

        if (! empty($deliveryInfo['tracking_number'])) {
            $message .= "Tracking: {$deliveryInfo['tracking_number']}\n";
        }

        if (! empty($deliveryInfo['estimated_delivery'])) {
            $message .= "ETA: {$deliveryInfo['estimated_delivery']}\n";
        }

        $message .= "\nThank you for your patience!";

        return $this->sendMessage($phone, $message);
    }

    /**
     * Send receipt
     */
    public function sendReceipt(string $phone, array $saleDetails)
    {
        $message = "🧾 *Payment Receipt*\n\n";
        $message .= "Receipt #: {$saleDetails['receipt_number']}\n";
        $message .= "Date: {$saleDetails['date']}\n";
        $message .= 'Amount: KES '.number_format($saleDetails['amount'], 2)."\n";
        $message .= "Payment: {$saleDetails['payment_method']}\n\n";
        $message .= 'Thank you for your business!';

        return $this->sendMessage($phone, $message);
    }

    /**
     * Format phone number for WhatsApp (E.164 format)
     */
    protected function formatPhoneNumber(string $phone): string
    {
        // Remove any non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Add country code if not present (Kenya = 254)
        if (strlen($phone) === 9) {
            $phone = '254'.$phone;
        } elseif (strlen($phone) === 10 && $phone[0] === '0') {
            $phone = '254'.substr($phone, 1);
        }

        return $phone;
    }

    /**
     * Build template components
     */
    protected function buildTemplateComponents(array $parameters): array
    {
        if (empty($parameters)) {
            return [];
        }

        $components = [];

        if (! empty($parameters['body'])) {
            $components[] = [
                'type' => 'body',
                'parameters' => array_map(function ($value) {
                    return ['type' => 'text', 'text' => $value];
                }, $parameters['body']),
            ];
        }

        return $components;
    }

    /**
     * Verify webhook signature
     */
    public function verifyWebhook(string $mode, string $token, string $challenge): ?string
    {
        $verifyToken = config('services.whatsapp.verify_token');

        if ($mode === 'subscribe' && $token === $verifyToken) {
            return $challenge;
        }

        return null;
    }

    /**
     * Handle incoming webhook
     */
    public function handleWebhook(array $payload)
    {
        Log::info('WhatsApp webhook received', $payload);

        // Process incoming messages, status updates, etc.
        // Implement based on your business logic

        return ['success' => true];
    }
}
