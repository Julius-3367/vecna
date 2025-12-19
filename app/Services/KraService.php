<?php

namespace App\Services;

use App\Models\TaxRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KraService
{
    protected $apiKey;

    protected $apiSecret;

    protected $environment;

    protected $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.kra.api_key');
        $this->apiSecret = config('services.kra.api_secret');
        $this->environment = config('services.kra.environment', 'sandbox');

        $this->baseUrl = $this->environment === 'production'
            ? 'https://itax.kra.go.ke/KRA-Portal/api'
            : 'https://sandbox.itax.kra.go.ke/KRA-Portal/api';
    }

    /**
     * Generate access token
     */
    protected function getAccessToken(): string
    {
        $url = "{$this->baseUrl}/oauth/token";

        $response = Http::asForm()->post($url, [
            'grant_type' => 'client_credentials',
            'client_id' => $this->apiKey,
            'client_secret' => $this->apiSecret,
        ]);

        if ($response->successful()) {
            return $response->json()['access_token'];
        }

        throw new \Exception('Failed to get KRA access token: '.$response->body());
    }

    /**
     * Validate KRA PIN
     */
    public function validatePin(string $pin): array
    {
        try {
            $token = $this->getAccessToken();
            $url = "{$this->baseUrl}/pin/validate";

            $response = Http::withToken($token)->post($url, [
                'pin' => $pin,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'success' => true,
                    'valid' => $data['valid'] ?? false,
                    'taxpayer_name' => $data['taxpayer_name'] ?? null,
                    'status' => $data['status'] ?? null,
                ];
            }

            return [
                'success' => false,
                'message' => 'PIN validation failed',
            ];

        } catch (\Exception $e) {
            Log::error('KRA PIN Validation Error', [
                'pin' => $pin,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Submit VAT return
     */
    public function submitVatReturn(int $month, int $year): array
    {
        $tenant = tenant();
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        // Calculate VAT totals
        $salesVat = \DB::table('sales')
            ->whereBetween('sale_date', [$startDate, $endDate])
            ->where('status', '!=', 'cancelled')
            ->sum('tax_amount');

        $purchaseVat = \DB::table('purchase_orders')
            ->whereBetween('order_date', [$startDate, $endDate])
            ->where('status', '!=', 'cancelled')
            ->sum('tax_amount');

        $netVat = $salesVat - $purchaseVat;

        try {
            $token = $this->getAccessToken();
            $url = "{$this->baseUrl}/vat/return";

            $payload = [
                'taxpayer_pin' => $tenant->kra_pin,
                'period_month' => $month,
                'period_year' => $year,
                'output_vat' => round($salesVat, 2),
                'input_vat' => round($purchaseVat, 2),
                'net_vat' => round($netVat, 2),
                'vat_payable' => round(max(0, $netVat), 2),
                'vat_refundable' => round(max(0, -$netVat), 2),
                'submission_date' => now()->toIso8601String(),
            ];

            $response = Http::withToken($token)->post($url, $payload);

            if ($response->successful()) {
                $data = $response->json();

                // Record in database
                TaxRecord::create([
                    'tax_type' => 'VAT',
                    'period_month' => $month,
                    'period_year' => $year,
                    'sales_vat' => $salesVat,
                    'purchase_vat' => $purchaseVat,
                    'net_vat' => $netVat,
                    'status' => 'submitted',
                    'submission_date' => now(),
                    'reference_number' => $data['reference_number'] ?? null,
                    'response_data' => json_encode($data),
                ]);

                Log::info('VAT Return Submitted to KRA', [
                    'period' => "{$month}/{$year}",
                    'net_vat' => $netVat,
                    'reference' => $data['reference_number'] ?? null,
                ]);

                return [
                    'success' => true,
                    'message' => 'VAT return submitted successfully',
                    'data' => $data,
                ];
            }

            throw new \Exception('Failed to submit VAT return: '.$response->body());
        } catch (\Exception $e) {
            Log::error('KRA VAT Submission Error', [
                'period' => "{$month}/{$year}",
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get VAT return data (without submitting)
     */
    public function getVatReturnData(int $month, int $year): array
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        // Output VAT (Sales)
        $salesData = \DB::table('sales')
            ->whereBetween('sale_date', [$startDate, $endDate])
            ->where('status', '!=', 'cancelled')
            ->selectRaw('
                COUNT(*) as sales_count,
                SUM(subtotal) as gross_sales,
                SUM(tax_amount) as output_vat
            ')
            ->first();

        // Input VAT (Purchases)
        $purchaseData = \DB::table('purchase_orders')
            ->whereBetween('order_date', [$startDate, $endDate])
            ->where('status', '!=', 'cancelled')
            ->selectRaw('
                COUNT(*) as purchase_count,
                SUM(subtotal) as gross_purchases,
                SUM(tax_amount) as input_vat
            ')
            ->first();

        $outputVat = $salesData->output_vat ?? 0;
        $inputVat = $purchaseData->input_vat ?? 0;
        $netVat = $outputVat - $inputVat;

        return [
            'period' => [
                'month' => $month,
                'year' => $year,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ],
            'sales' => [
                'count' => $salesData->sales_count ?? 0,
                'gross_amount' => $salesData->gross_sales ?? 0,
                'output_vat' => $outputVat,
            ],
            'purchases' => [
                'count' => $purchaseData->purchase_count ?? 0,
                'gross_amount' => $purchaseData->gross_purchases ?? 0,
                'input_vat' => $inputVat,
            ],
            'vat_summary' => [
                'output_vat' => $outputVat,
                'input_vat' => $inputVat,
                'net_vat' => $netVat,
                'vat_payable' => max(0, $netVat),
                'vat_refundable' => max(0, -$netVat),
            ],
        ];
    }

    /**
     * Generate eTIMS invoice number
     */
    public function generateEtimsInvoice($sale): array
    {
        try {
            $token = $this->getAccessToken();
            $url = "{$this->baseUrl}/etims/invoice";

            $tenant = tenant();

            $payload = [
                'taxpayer_pin' => $tenant->kra_pin,
                'invoice_number' => $sale->sale_number,
                'invoice_date' => $sale->sale_date->format('Y-m-d'),
                'customer_pin' => $sale->customer?->kra_pin,
                'customer_name' => $sale->customer?->name ?? 'Walk-in Customer',
                'items' => $sale->items->map(function ($item) {
                    return [
                        'description' => $item->product->name,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'tax_rate' => $item->tax_rate,
                        'tax_amount' => $item->tax_amount,
                        'total_amount' => $item->total_amount,
                    ];
                })->toArray(),
                'subtotal' => $sale->subtotal,
                'tax_amount' => $sale->tax_amount,
                'total_amount' => $sale->total_amount,
            ];

            $response = Http::withToken($token)->post($url, $payload);

            if ($response->successful()) {
                $data = $response->json();

                // Update sale with eTIMS details
                $sale->update([
                    'kra_cu_invoice_number' => $data['cu_invoice_number'] ?? null,
                    'kra_qr_code' => $data['qr_code'] ?? null,
                ]);

                return [
                    'success' => true,
                    'cu_invoice_number' => $data['cu_invoice_number'] ?? null,
                    'qr_code' => $data['qr_code'] ?? null,
                ];
            }

            throw new \Exception('Failed to generate eTIMS invoice: '.$response->body());
        } catch (\Exception $e) {
            Log::error('KRA eTIMS Invoice Error', [
                'sale_id' => $sale->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get tax compliance status
     */
    public function getComplianceStatus(string $pin): array
    {
        try {
            $token = $this->getAccessToken();
            $url = "{$this->baseUrl}/compliance/status";

            $response = Http::withToken($token)->get($url, [
                'pin' => $pin,
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            throw new \Exception('Failed to get compliance status: '.$response->body());
        } catch (\Exception $e) {
            Log::error('KRA Compliance Status Error', [
                'pin' => $pin,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
