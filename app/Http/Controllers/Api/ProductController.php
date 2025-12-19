<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * Display a listing of products
     */
    public function index(Request $request)
    {
        $query = Product::with(['category', 'brand', 'unit']);

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by brand
        if ($request->has('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }

        // Filter by status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filter low stock
        if ($request->boolean('low_stock')) {
            $query->whereRaw('current_stock <= minimum_stock');
        }

        // Sort
        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 15);
        $products = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }

    /**
     * Store a newly created product
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100|unique:products',
            'barcode' => 'nullable|string|max:100|unique:products',
            'category_id' => 'required|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'unit_id' => 'required|exists:units,id',
            'description' => 'nullable|string',
            'cost_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'minimum_stock' => 'nullable|integer|min:0',
            'maximum_stock' => 'nullable|integer|min:0',
            'reorder_level' => 'nullable|integer|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'is_taxable' => 'boolean',
            'track_stock' => 'boolean',
            'image' => 'nullable|image|max:2048',
        ]);

        // Generate SKU if not provided
        if (empty($validated['sku'])) {
            $validated['sku'] = 'PRD-'.strtoupper(Str::random(8));
        }

        // Handle image upload
        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('products', 'public');
        }

        $product = Product::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully',
            'data' => $product->load(['category', 'brand', 'unit']),
        ], 201);
    }

    /**
     * Display the specified product
     */
    public function show(Product $product)
    {
        $product->load(['category', 'brand', 'unit', 'stockLocations']);

        return response()->json([
            'success' => true,
            'data' => $product,
        ]);
    }

    /**
     * Update the specified product
     */
    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'sku' => 'sometimes|string|max:100|unique:products,sku,'.$product->id,
            'barcode' => 'sometimes|string|max:100|unique:products,barcode,'.$product->id,
            'category_id' => 'sometimes|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'unit_id' => 'sometimes|exists:units,id',
            'description' => 'nullable|string',
            'cost_price' => 'sometimes|numeric|min:0',
            'selling_price' => 'sometimes|numeric|min:0',
            'minimum_stock' => 'nullable|integer|min:0',
            'maximum_stock' => 'nullable|integer|min:0',
            'reorder_level' => 'nullable|integer|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'is_taxable' => 'boolean',
            'is_active' => 'boolean',
            'track_stock' => 'boolean',
            'image' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('products', 'public');
        }

        $product->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully',
            'data' => $product->load(['category', 'brand', 'unit']),
        ]);
    }

    /**
     * Remove the specified product
     */
    public function destroy(Product $product)
    {
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully',
        ]);
    }

    /**
     * Update stock for a product
     */
    public function updateStock(Request $request, Product $product)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer',
            'type' => 'required|in:in,out,adjustment',
            'location_id' => 'required|exists:stock_locations,id',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $product->updateStock(
                $validated['quantity'],
                $validated['type'],
                $validated['location_id'],
                $validated['reference'] ?? null,
                $validated['notes'] ?? null
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stock updated successfully',
                'data' => [
                    'product' => $product->fresh(),
                    'current_stock' => $product->current_stock,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update stock: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get stock history for a product
     */
    public function stockHistory(Request $request, Product $product)
    {
        $perPage = $request->get('per_page', 20);

        $movements = StockMovement::where('product_id', $product->id)
            ->with(['location', 'user'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $movements,
        ]);
    }

    /**
     * Get profit analysis for a product
     */
    public function profitAnalysis(Product $product)
    {
        $margin = $product->getProfitMargin();
        $stockValue = $product->getStockValue();

        // Get sales data for this product (last 30 days)
        $salesData = DB::table('sale_items')
            ->where('product_id', $product->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->select(
                DB::raw('COUNT(*) as total_sales'),
                DB::raw('SUM(quantity) as units_sold'),
                DB::raw('SUM(total_amount) as revenue'),
                DB::raw('SUM(quantity * cost_price) as cost'),
                DB::raw('SUM(total_amount - (quantity * cost_price)) as profit')
            )
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'product' => $product,
                'margin_percentage' => $margin,
                'current_stock_value' => $stockValue,
                'last_30_days' => [
                    'total_sales' => $salesData->total_sales ?? 0,
                    'units_sold' => $salesData->units_sold ?? 0,
                    'revenue' => $salesData->revenue ?? 0,
                    'cost' => $salesData->cost ?? 0,
                    'profit' => $salesData->profit ?? 0,
                    'profit_margin' => $salesData->revenue > 0
                        ? (($salesData->profit / $salesData->revenue) * 100)
                        : 0,
                ],
            ],
        ]);
    }

    /**
     * Duplicate a product
     */
    public function duplicate(Product $product)
    {
        $newProduct = $product->replicate();
        $newProduct->sku = 'PRD-'.strtoupper(Str::random(8));
        $newProduct->barcode = null;
        $newProduct->name = $product->name.' (Copy)';
        $newProduct->current_stock = 0;
        $newProduct->save();

        return response()->json([
            'success' => true,
            'message' => 'Product duplicated successfully',
            'data' => $newProduct->load(['category', 'brand', 'unit']),
        ], 201);
    }
}
