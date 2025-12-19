<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
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
            'sale_date' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:fixed,percentage',
            'discount_value' => 'nullable|numeric|min:0',
            'payment_method' => 'required|in:cash,mpesa,card,bank_transfer,credit',
            'phone' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $useTransaction = DB::transactionLevel() === 0;
        if ($useTransaction) {
            DB::beginTransaction();
        }
        
        try {
            // Validate stock availability first
            foreach ($validated['items'] as $index => $item) {
                $product = Product::findOrFail($item['product_id']);
                
                if ($product->track_stock && $product->stock_quantity < $item['quantity']) {
                    return response()->json([
                        'success' => false,
                        'message' => "Insufficient stock for {$product->name}",
                        'errors' => [
                            "items.{$index}.quantity" => ["Insufficient stock. Available: {$product->stock_quantity}"],
                        ],
                    ], 422);
                }
            }
            
            // Calculate totals from items first
            $subtotal = 0;
            $totalTax = 0;

            foreach ($validated['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);
                $lineTotal = $item['quantity'] * $item['unit_price'];
                $subtotal += $lineTotal;
                
                if (isset($product->tax_rate) && $product->tax_rate > 0) {
                    $totalTax += ($lineTotal * $product->tax_rate / 100);
                }
            }
            
            // Apply discount
            $discountAmount = 0;
            if (isset($validated['discount_value']) && $validated['discount_value'] > 0) {
                if (($validated['discount_type'] ?? null) === 'percentage') {
                    $discountAmount = $subtotal * ($validated['discount_value'] / 100);
                } else {
                    $discountAmount = $validated['discount_value'];
                }
            }
            
            $totalAmount = $subtotal + $totalTax - $discountAmount;
            
            // Determine payment status and sale status based on payment method
            $paymentStatus = $validated['payment_method'] === 'mpesa' ? 'pending' : 'paid';
            $status = $validated['payment_method'] === 'mpesa' ? 'processing' : 'completed';
            
            // Get or create default location for tenant
            $location = DB::table('locations')->where('code', 'MAIN')->first();
            if (!$location) {
                $locationId = DB::table('locations')->insertGetId([
                    'name' => 'Main Location',
                    'code' => 'MAIN',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $locationId = $location->id;
            }
            
            // Create sale
            $sale = Sale::create([
                'sale_number' => Sale::generateSaleNumber(),
                'customer_id' => $validated['customer_id'] ?? null,
                'location_id' => $locationId,
                'sale_date' => $validated['sale_date'] ?? now(),
                'status' => $status,
                'payment_status' => $paymentStatus,
                'subtotal' => $subtotal,
                'tax_amount' => $totalTax,
                'discount_amount' => $discountAmount,
                'total_amount' => $totalAmount,
                'paid_amount' => $paymentStatus === 'paid' ? $totalAmount : 0,
                'discount_type' => $validated['discount_type'] ?? null,
                'discount_value' => $validated['discount_value'] ?? 0,
                'notes' => $validated['notes'] ?? null,
                'payment_method' => $validated['payment_method'],
                'user_id' => auth()->id(),
            ]);

            // Create sale items and update stock
            foreach ($validated['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);

                $itemDiscount = $item['discount'] ?? 0;
                $itemTaxRate = $item['tax_rate'] ?? ($product->is_taxable ? $product->tax_rate : 0);

                $lineTotal = ($item['unit_price'] * $item['quantity']);
                $discountAmount = $itemDiscount;
                $lineTax = ($lineTotal - $discountAmount) * ($itemTaxRate / 100);
                $lineTotalWithTax = $lineTotal - $discountAmount + $lineTax;

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $item['product_id'],
                    'product_name' => $product->name,
                    'sku' => $product->sku ?? '',
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'unit_cost' => $product->cost_price ?? 0,
                    'discount_amount' => $discountAmount,
                    'tax_rate' => $itemTaxRate,
                    'tax_amount' => $lineTax,
                    'line_total' => $lineTotalWithTax,
                ]);

                // Update stock
                if ($product->track_stock) {
                    $previousStock = $product->stock_quantity;
                    $product->stock_quantity -= $item['quantity'];
                    $product->save();
                    
                    // Create stock movement record
                    StockMovement::create([
                        'product_id' => $product->id,
                        'quantity' => $item['quantity'],
                        'type' => 'sale',
                        'reference_type' => 'sale',
                        'reference_id' => $sale->id,
                        'user_id' => auth()->id(),
                        'quantity_before' => $previousStock,
                        'quantity_after' => $product->stock_quantity,
                    ]);
                }
            }

            if ($useTransaction) {
                DB::commit();
            }

            return response()->json([
                'success' => true,
                'message' => 'Sale created successfully',
                'data' => $sale->load(['customer', 'items.product']),
            ], 201);

        } catch (\Exception $e) {
            if ($useTransaction) {
                DB::rollBack();
            }

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
