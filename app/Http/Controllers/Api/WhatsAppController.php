<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;

class WhatsAppController extends Controller
{
    protected $whatsapp;

    public function __construct(WhatsAppService $whatsapp)
    {
        $this->whatsapp = $whatsapp;
    }

    /**
     * Send WhatsApp message
     */
    public function sendMessage(Request $request)
    {
        $validated = $request->validate([
            'phone' => 'required|string',
            'message' => 'required|string|max:4096',
        ]);

        $result = $this->whatsapp->sendMessage(
            $validated['phone'],
            $validated['message']
        );

        return response()->json($result);
    }

    /**
     * Send order confirmation
     */
    public function sendOrderConfirmation(Request $request)
    {
        $validated = $request->validate([
            'phone' => 'required|string',
            'order_number' => 'required|string',
            'total' => 'required|numeric',
            'status' => 'required|string',
            'items' => 'nullable|array',
        ]);

        $result = $this->whatsapp->sendOrderConfirmation(
            $validated['phone'],
            $validated
        );

        return response()->json($result);
    }

    /**
     * Send payment reminder
     */
    public function sendPaymentReminder(Request $request)
    {
        $validated = $request->validate([
            'phone' => 'required|string',
            'invoice_number' => 'required|string',
            'amount' => 'required|numeric',
            'due_date' => 'required|string',
        ]);

        $result = $this->whatsapp->sendPaymentReminder(
            $validated['phone'],
            $validated
        );

        return response()->json($result);
    }

    /**
     * Send daily snapshot
     */
    public function sendDailySnapshot(Request $request)
    {
        $validated = $request->validate([
            'phone' => 'required|string',
            'stats' => 'required|array',
        ]);

        $result = $this->whatsapp->sendDailySnapshot(
            $validated['phone'],
            $validated['stats']
        );

        return response()->json($result);
    }

    /**
     * Webhook verification
     */
    public function verifyWebhook(Request $request)
    {
        $mode = $request->query('hub.mode');
        $token = $request->query('hub.verify_token');
        $challenge = $request->query('hub.challenge');

        $result = $this->whatsapp->verifyWebhook($mode, $token, $challenge);

        if ($result) {
            return response($result, 200);
        }

        return response('Verification failed', 403);
    }

    /**
     * Handle incoming webhooks
     */
    public function handleWebhook(Request $request)
    {
        $payload = $request->all();

        $result = $this->whatsapp->handleWebhook($payload);

        return response()->json($result);
    }
}
