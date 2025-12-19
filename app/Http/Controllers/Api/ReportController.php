<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Sales summary report
     */
    public function salesSummary(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth());
        $endDate = $request->get('end_date', now()->endOfMonth());

        $sales = Sale::whereBetween('sale_date', [$startDate, $endDate])
            ->where('status', '!=', 'cancelled')
            ->selectRaw('
                COUNT(*) as total_sales,
                SUM(subtotal) as gross_sales,
                SUM(discount_amount) as total_discounts,
                SUM(tax_amount) as total_tax,
                SUM(total_amount) as net_sales,
                SUM(paid_amount) as total_collected,
                AVG(total_amount) as average_sale_value
            ')
            ->first();

        // Sales by payment method
        $paymentMethods = DB::table('sale_payments')
            ->join('sales', 'sale_payments.sale_id', '=', 'sales.id')
            ->whereBetween('sales.sale_date', [$startDate, $endDate])
            ->where('sales.status', '!=', 'cancelled')
            ->groupBy('payment_method')
            ->selectRaw('payment_method, SUM(amount) as total_amount, COUNT(*) as count')
            ->get();

        // Daily sales trend
        $dailySales = Sale::whereBetween('sale_date', [$startDate, $endDate])
            ->where('status', '!=', 'cancelled')
            ->groupBy(DB::raw('DATE(sale_date)'))
            ->selectRaw('DATE(sale_date) as date, COUNT(*) as count, SUM(total_amount) as total')
            ->orderBy('date')
            ->get();

        // Calculate profit
        $profit = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->whereBetween('sales.sale_date', [$startDate, $endDate])
            ->where('sales.status', '!=', 'cancelled')
            ->selectRaw('SUM((unit_price - cost_price) * quantity) as gross_profit')
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $sales,
                'profit' => $profit->gross_profit ?? 0,
                'profit_margin' => $sales->net_sales > 0 ? (($profit->gross_profit / $sales->net_sales) * 100) : 0,
                'payment_methods' => $paymentMethods,
                'daily_trend' => $dailySales,
            ],
        ]);
    }

    /**
     * Sales by product
     */
    public function salesByProduct(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth());
        $endDate = $request->get('end_date', now()->endOfMonth());
        $limit = $request->get('limit', 10);

        $products = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->whereBetween('sales.sale_date', [$startDate, $endDate])
            ->where('sales.status', '!=', 'cancelled')
            ->groupBy('sale_items.product_id', 'products.name', 'products.sku')
            ->selectRaw('
                sale_items.product_id,
                products.name,
                products.sku,
                SUM(sale_items.quantity) as units_sold,
                SUM(sale_items.total_amount) as revenue,
                SUM((sale_items.unit_price - sale_items.cost_price) * sale_items.quantity) as profit,
                COUNT(DISTINCT sales.id) as transaction_count
            ')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }

    /**
     * Sales by customer
     */
    public function salesByCustomer(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth());
        $endDate = $request->get('end_date', now()->endOfMonth());
        $limit = $request->get('limit', 10);

        $customers = Sale::whereBetween('sale_date', [$startDate, $endDate])
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('customer_id')
            ->join('customers', 'sales.customer_id', '=', 'customers.id')
            ->groupBy('sales.customer_id', 'customers.name', 'customers.customer_number')
            ->selectRaw('
                sales.customer_id,
                customers.name,
                customers.customer_number,
                COUNT(*) as purchase_count,
                SUM(sales.total_amount) as total_spent,
                AVG(sales.total_amount) as average_order_value,
                MAX(sales.sale_date) as last_purchase_date
            ')
            ->orderByDesc('total_spent')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $customers,
        ]);
    }

    /**
     * Inventory valuation report
     */
    public function inventoryValuation(Request $request)
    {
        $products = Product::where('is_active', true)
            ->where('track_stock', true)
            ->with('category')
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'category' => $product->category->name ?? 'Uncategorized',
                    'current_stock' => $product->current_stock,
                    'cost_price' => $product->cost_price,
                    'selling_price' => $product->selling_price,
                    'stock_value' => $product->getStockValue(),
                    'potential_revenue' => $product->current_stock * $product->selling_price,
                    'potential_profit' => $product->current_stock * ($product->selling_price - $product->cost_price),
                ];
            });

        $summary = [
            'total_stock_value' => $products->sum('stock_value'),
            'potential_revenue' => $products->sum('potential_revenue'),
            'potential_profit' => $products->sum('potential_profit'),
            'total_items' => $products->count(),
            'total_units' => $products->sum('current_stock'),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $summary,
                'products' => $products,
            ],
        ]);
    }

    /**
     * Profit & Loss statement
     */
    public function profitLoss(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth());
        $endDate = $request->get('end_date', now()->endOfMonth());

        // Revenue
        $sales = Sale::whereBetween('sale_date', [$startDate, $endDate])
            ->where('status', '!=', 'cancelled')
            ->selectRaw('
                SUM(subtotal) as gross_sales,
                SUM(discount_amount) as discounts,
                SUM(total_amount) as net_sales
            ')
            ->first();

        // Cost of Goods Sold
        $cogs = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->whereBetween('sales.sale_date', [$startDate, $endDate])
            ->where('sales.status', '!=', 'cancelled')
            ->selectRaw('SUM(sale_items.cost_price * sale_items.quantity) as total_cogs')
            ->first();

        // Expenses by category
        $expenses = Expense::whereBetween('expense_date', [$startDate, $endDate])
            ->where('status', 'approved')
            ->join('expense_categories', 'expenses.expense_category_id', '=', 'expense_categories.id')
            ->groupBy('expense_categories.id', 'expense_categories.name')
            ->selectRaw('expense_categories.name as category, SUM(expenses.amount) as total')
            ->get();

        $totalExpenses = $expenses->sum('total');

        // Calculate P&L
        $grossProfit = ($sales->net_sales ?? 0) - ($cogs->total_cogs ?? 0);
        $netProfit = $grossProfit - $totalExpenses;

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
                'revenue' => [
                    'gross_sales' => $sales->gross_sales ?? 0,
                    'discounts' => $sales->discounts ?? 0,
                    'net_sales' => $sales->net_sales ?? 0,
                ],
                'cogs' => $cogs->total_cogs ?? 0,
                'gross_profit' => $grossProfit,
                'gross_profit_margin' => $sales->net_sales > 0 ? ($grossProfit / $sales->net_sales) * 100 : 0,
                'expenses' => [
                    'by_category' => $expenses,
                    'total' => $totalExpenses,
                ],
                'net_profit' => $netProfit,
                'net_profit_margin' => $sales->net_sales > 0 ? ($netProfit / $sales->net_sales) * 100 : 0,
            ],
        ]);
    }

    /**
     * VAT report for KRA
     */
    public function vatReport(Request $request)
    {
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        // Output VAT (Sales)
        $salesVat = Sale::whereBetween('sale_date', [$startDate, $endDate])
            ->where('status', '!=', 'cancelled')
            ->sum('tax_amount');

        // Input VAT (Purchases)
        $purchaseVat = DB::table('purchase_orders')
            ->whereBetween('order_date', [$startDate, $endDate])
            ->where('status', '!=', 'cancelled')
            ->sum('tax_amount');

        $netVat = $salesVat - $purchaseVat;

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'month' => $month,
                    'year' => $year,
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                ],
                'output_vat' => $salesVat,
                'input_vat' => $purchaseVat,
                'net_vat_payable' => max(0, $netVat),
                'vat_refundable' => max(0, -$netVat),
            ],
        ]);
    }

    /**
     * Dashboard KPIs
     */
    public function dashboard(Request $request)
    {
        $today = now();
        $thisMonth = now()->startOfMonth();

        // Today's sales
        $todaySales = Sale::whereDate('sale_date', $today)
            ->where('status', '!=', 'cancelled')
            ->selectRaw('COUNT(*) as count, SUM(total_amount) as total')
            ->first();

        // This month's sales
        $monthSales = Sale::whereDate('sale_date', '>=', $thisMonth)
            ->where('status', '!=', 'cancelled')
            ->selectRaw('COUNT(*) as count, SUM(total_amount) as total')
            ->first();

        // Low stock alerts
        $lowStock = Product::whereRaw('current_stock <= minimum_stock')
            ->where('is_active', true)
            ->count();

        // Pending orders
        $pendingOrders = Sale::where('payment_status', 'unpaid')
            ->where('status', '!=', 'cancelled')
            ->count();

        // Top selling products today
        $topProducts = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->whereDate('sales.sale_date', $today)
            ->where('sales.status', '!=', 'cancelled')
            ->groupBy('products.id', 'products.name')
            ->selectRaw('products.name, SUM(sale_items.quantity) as units_sold')
            ->orderByDesc('units_sold')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'today' => [
                    'sales_count' => $todaySales->count ?? 0,
                    'sales_total' => $todaySales->total ?? 0,
                ],
                'this_month' => [
                    'sales_count' => $monthSales->count ?? 0,
                    'sales_total' => $monthSales->total ?? 0,
                ],
                'alerts' => [
                    'low_stock_count' => $lowStock,
                    'pending_orders' => $pendingOrders,
                ],
                'top_products_today' => $topProducts,
            ],
        ]);
    }
}
