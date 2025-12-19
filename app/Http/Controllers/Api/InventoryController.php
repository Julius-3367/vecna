<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockAlert;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    /**
     * Get stock levels across all products
     */
    public function stockLevels(Request $request)
    {
        $query = Product::where('track_stock', true)
            ->with(['category', 'stockLocations']);

        // Filter by location
        if ($request->has('location_id')) {
            $locationId = $request->location_id;
            $query->whereHas('stockLocations', function ($q) use ($locationId) {
                $q->where('stock_location_id', $locationId);
            });
        }

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        $products = $query->get()->map(function ($product) use ($request) {
            $data = [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'category' => $product->category->name ?? null,
                'current_stock' => $product->current_stock,
                'minimum_stock' => $product->minimum_stock,
                'maximum_stock' => $product->maximum_stock,
                'reorder_level' => $product->reorder_level,
                'is_low_stock' => $product->isLowStock(),
                'is_out_of_stock' => $product->isOutOfStock(),
                'stock_value' => $product->getStockValue(),
            ];

            if ($request->has('location_id')) {
                $data['location_stock'] = $product->getStockAtLocation($request->location_id);
            }

            return $data;
        });

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }

    /**
     * Get low stock products
     */
    public function lowStock(Request $request)
    {
        $products = Product::whereRaw('current_stock <= minimum_stock')
            ->where('is_active', true)
            ->where('track_stock', true)
            ->with('category')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }

    /**
     * Get total stock value
     */
    public function stockValue(Request $request)
    {
        $locationId = $request->get('location_id');

        $query = Product::where('track_stock', true)
            ->where('is_active', true);

        if ($locationId) {
            // Get stock value for specific location
            $value = DB::table('stock_locations')
                ->where('stock_location_id', $locationId)
                ->join('products', 'stock_locations.product_id', '=', 'products.id')
                ->selectRaw('SUM(stock_locations.quantity * products.cost_price) as total_value')
                ->first();
        } else {
            // Get total stock value across all locations
            $value = $query->selectRaw('SUM(current_stock * cost_price) as total_value')
                ->first();
        }

        $totalValue = $value->total_value ?? 0;

        // Get breakdown by category
        $byCategory = DB::table('products')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->where('products.track_stock', true)
            ->where('products.is_active', true)
            ->groupBy('categories.id', 'categories.name')
            ->selectRaw('
                categories.name as category,
                SUM(products.current_stock) as total_units,
                SUM(products.current_stock * products.cost_price) as value
            ')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_value' => $totalValue,
                'by_category' => $byCategory,
            ],
        ]);
    }

    /**
     * Transfer stock between locations
     */
    public function transfer(Request $request)
    {
        $validated = $request->validate([
            'from_location_id' => 'required|exists:stock_locations,id',
            'to_location_id' => 'required|exists:stock_locations,id|different:from_location_id',
            'transfer_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            // Create stock transfer
            $transfer = StockTransfer::create([
                'transfer_number' => StockTransfer::generateTransferNumber(),
                'from_location_id' => $validated['from_location_id'],
                'to_location_id' => $validated['to_location_id'],
                'transfer_date' => $validated['transfer_date'],
                'status' => 'completed',
                'notes' => $validated['notes'] ?? null,
                'transferred_by' => auth()->id(),
            ]);

            // Process each item
            foreach ($validated['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);

                // Check stock availability at source location
                $availableStock = $product->getStockAtLocation($validated['from_location_id']);
                if ($availableStock < $item['quantity']) {
                    throw new \Exception("Insufficient stock for {$product->name} at source location");
                }

                // Create transfer item
                $transfer->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                ]);

                // Update stock at both locations
                $product->updateStock(
                    -$item['quantity'],
                    'out',
                    $validated['from_location_id'],
                    "Transfer Out: {$transfer->transfer_number}"
                );

                $product->updateStock(
                    $item['quantity'],
                    'in',
                    $validated['to_location_id'],
                    "Transfer In: {$transfer->transfer_number}"
                );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stock transferred successfully',
                'data' => $transfer->load('items.product'),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to transfer stock: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * Make stock adjustment
     */
    public function adjustment(Request $request)
    {
        $validated = $request->validate([
            'location_id' => 'required|exists:stock_locations,id',
            'adjustment_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity_counted' => 'required|integer|min:0',
            'items.*.reason' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $adjustment = StockAdjustment::create([
                'adjustment_number' => StockAdjustment::generateAdjustmentNumber(),
                'location_id' => $validated['location_id'],
                'adjustment_date' => $validated['adjustment_date'],
                'status' => 'completed',
                'notes' => $validated['notes'] ?? null,
                'adjusted_by' => auth()->id(),
            ]);

            foreach ($validated['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);
                $currentStock = $product->getStockAtLocation($validated['location_id']);
                $variance = $item['quantity_counted'] - $currentStock;

                $adjustment->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity_before' => $currentStock,
                    'quantity_counted' => $item['quantity_counted'],
                    'variance' => $variance,
                    'reason' => $item['reason'] ?? null,
                ]);

                if ($variance != 0) {
                    $product->updateStock(
                        $variance,
                        $variance > 0 ? 'in' : 'out',
                        $validated['location_id'],
                        "Adjustment: {$adjustment->adjustment_number}",
                        $item['reason'] ?? null
                    );
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stock adjusted successfully',
                'data' => $adjustment->load('items.product'),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to adjust stock: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get stock movements history
     */
    public function movements(Request $request)
    {
        $query = StockMovement::with(['product', 'location', 'user']);

        // Filter by product
        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        // Filter by location
        if ($request->has('location_id')) {
            $query->where('location_id', $request->location_id);
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Date range
        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $query->orderBy('created_at', 'desc');

        $perPage = $request->get('per_page', 20);
        $movements = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $movements,
        ]);
    }

    /**
     * Get stock locations
     */
    public function locations(Request $request)
    {
        $locations = StockLocation::where('is_active', true)->get();

        return response()->json([
            'success' => true,
            'data' => $locations,
        ]);
    }

    /**
     * Get stock alerts
     */
    public function alerts(Request $request)
    {
        $query = StockAlert::with(['product', 'location'])
            ->where('is_resolved', false);

        // Filter by alert type
        if ($request->has('alert_type')) {
            $query->where('alert_type', $request->alert_type);
        }

        $alerts = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $alerts,
        ]);
    }

    /**
     * Acknowledge stock alert
     */
    public function acknowledgeAlert(Request $request, StockAlert $alert)
    {
        $validated = $request->validate([
            'action_taken' => 'nullable|string',
        ]);

        $alert->update([
            'is_resolved' => true,
            'resolved_at' => now(),
            'resolved_by' => auth()->id(),
            'action_taken' => $validated['action_taken'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Alert acknowledged successfully',
            'data' => $alert,
        ]);
    }
}
