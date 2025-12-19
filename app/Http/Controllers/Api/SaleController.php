<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    /**
     * Display a listing of sales
     */
    public function index(Request $request)
    {
        $query = Sale::with(['customer', 'items.product', 'payments']);

        // Date range filter
        if ($request->has('start_date')) {
            $query->whereDate('sale_date', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->whereDate('sale_date', '<=', $request->end_date);
        }

        // Customer filter
        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        // Status filter
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Payment status filter
        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        // Search by sale number
        if ($request->has('search')) {
            $query->where('sale_number', 'like', "%{$request->search}%");
        }

        $query->orderBy('created_at', 'desc');

        $perPage = $request->get('per_page', 15);
        $sales = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $sales,
        ]);
    }

    /**
     * Store a newly created sale
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'sale_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:fixed,percentage',
            'discount_value' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'location_id' => 'required|exists:stock_locations,id',
        ]);

        DB::beginTransaction();
        try {
            // Create sale
            $sale = Sale::create([
                'sale_number' => Sale::generateSaleNumber(),
                'customer_id' => $validated['customer_id'] ?? null,
                'sale_date' => $validated['sale_date'],
                'status' => 'completed',
                'payment_status' => 'unpaid',
                'discount_type' => $validated['discount_type'] ?? null,
                'discount_value' => $validated['discount_value'] ?? 0,
                'notes' => $validated['notes'] ?? null,
                'location_id' => $validated['location_id'],
                'user_id' => auth()->id(),
            ]);

            // Create sale items and update stock
            $subtotal = 0;
            $totalTax = 0;

            foreach ($validated['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);

                // Check stock availability
                if ($product->track_stock) {
                    $locationStock = $product->getStockAtLocation($validated['location_id']);
                    if ($locationStock < $item['quantity']) {
                        throw new \Exception("Insufficient stock for {$product->name}. Available: {$locationStock}");
                    }
                }

                $itemDiscount = $item['discount'] ?? 0;
                $itemTaxRate = $item['tax_rate'] ?? ($product->is_taxable ? $product->tax_rate : 0);

                $lineTotal = ($item['unit_price'] * $item['quantity']) - $itemDiscount;
                $lineTax = $lineTotal * ($itemTaxRate / 100);
                $totalAmount = $lineTotal + $lineTax;

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'cost_price' => $product->cost_price,
                    'discount' => $itemDiscount,
                    'tax_rate' => $itemTaxRate,
                    'tax_amount' => $lineTax,
                    'total_amount' => $totalAmount,
                ]);

                // Update stock
                if ($product->track_stock) {
                    $product->updateStock(
                        -$item['quantity'],
                        'out',
                        $validated['location_id'],
                        "Sale: {$sale->sale_number}"
                    );
                }

                $subtotal += $lineTotal;
                $totalTax += $lineTax;
            }

            // Apply sale-level discount
            $discountAmount = 0;
            if ($validated['discount_type'] === 'percentage') {
                $discountAmount = $subtotal * ($validated['discount_value'] / 100);
            } elseif ($validated['discount_type'] === 'fixed') {
                $discountAmount = $validated['discount_value'];
            }

            $sale->update([
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'tax_amount' => $totalTax,
                'total_amount' => ($subtotal - $discountAmount) + $totalTax,
            ]);

            // Record tenant usage
            $sale->tenant->recordUsage('transactions', 1);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Sale created successfully',
                'data' => $sale->load(['customer', 'items.product', 'payments']),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create sale: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * Display the specified sale
     */
    public function show(Sale $sale)
    {
        $sale->load(['customer', 'items.product', 'payments', 'user']);

        return response()->json([
            'success' => true,
            'data' => $sale,
        ]);
    }

    /**
     * Add payment to a sale
     */
    public function addPayment(Request $request, Sale $sale)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|in:cash,mpesa,card,bank_transfer,credit',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $payment = $sale->recordPayment($validated);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment recorded successfully',
                'data' => [
                    'payment' => $payment,
                    'sale' => $sale->fresh(['payments']),
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to record payment: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * Complete a sale (mark as completed)
     */
    public function complete(Sale $sale)
    {
        if ($sale->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Sale is already completed',
            ], 422);
        }

        $sale->update(['status' => 'completed']);

        return response()->json([
            'success' => true,
            'message' => 'Sale completed successfully',
            'data' => $sale,
        ]);
    }

    /**
     * Cancel a sale
     */
    public function cancel(Request $request, Sale $sale)
    {
        if ($sale->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'Sale is already cancelled',
            ], 422);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            // Restore stock for all items
            foreach ($sale->items as $item) {
                $product = $item->product;
                if ($product->track_stock) {
                    $product->updateStock(
                        $item->quantity,
                        'in',
                        $sale->location_id,
                        "Sale Cancelled: {$sale->sale_number}",
                        $validated['reason'] ?? null
                    );
                }
            }

            $sale->update([
                'status' => 'cancelled',
                'notes' => ($sale->notes ?? '')."\nCancelled: ".($validated['reason'] ?? 'No reason provided'),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Sale cancelled successfully',
                'data' => $sale->fresh(),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel sale: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get sale receipt data
     */
    public function receipt(Sale $sale)
    {
        $sale->load(['customer', 'items.product', 'payments', 'location']);

        $tenant = tenant();

        return response()->json([
            'success' => true,
            'data' => [
                'sale' => $sale,
                'business' => [
                    'name' => $tenant->name,
                    'phone' => $tenant->phone,
                    'email' => $tenant->email,
                    'address' => $tenant->address,
                    'kra_pin' => $tenant->kra_pin,
                ],
            ],
        ]);
    }

    /**
     * Create POS sale (simplified endpoint for POS terminals)
     */
    public function createPOS(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'payment_method' => 'required|in:cash,mpesa,card',
            'amount_paid' => 'required|numeric|min:0',
            'location_id' => 'required|exists:stock_locations,id',
            'terminal_id' => 'nullable|exists:pos_terminals,id',
        ]);

        DB::beginTransaction();
        try {
            // Build items with prices
            $items = [];
            foreach ($validated['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);
                $items[] = [
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $product->selling_price,
                    'tax_rate' => $product->is_taxable ? $product->tax_rate : 0,
                ];
            }

            // Create sale
            $saleRequest = new Request([
                'sale_date' => now(),
                'items' => $items,
                'location_id' => $validated['location_id'],
            ]);

            $saleResponse = $this->store($saleRequest);
            $sale = $saleResponse->getData()->data;

            // Record payment
            $paymentRequest = new Request([
                'amount' => $validated['amount_paid'],
                'payment_method' => $validated['payment_method'],
            ]);

            $this->addPayment($paymentRequest, Sale::find($sale->id));

            DB::commit();

            $sale = Sale::with(['items.product', 'payments'])->find($sale->id);

            return response()->json([
                'success' => true,
                'message' => 'POS sale created successfully',
                'data' => [
                    'sale' => $sale,
                    'change' => max(0, $validated['amount_paid'] - $sale->total_amount),
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create POS sale: '.$e->getMessage(),
            ], 422);
        }
    }
}
