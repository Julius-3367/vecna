<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    /**
     * Display a listing of customers
     */
    public function index(Request $request)
    {
        $query = Customer::query();

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('customer_number', 'like', "%{$search}%");
            });
        }

        // Filter by type
        if ($request->has('customer_type')) {
            $query->where('customer_type', $request->customer_type);
        }

        // Filter by status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Sort
        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 15);
        $customers = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $customers,
        ]);
    }

    /**
     * Store a newly created customer
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:customers',
            'phone' => 'required|string|max:20',
            'kra_pin' => 'nullable|string|max:20',
            'customer_type' => 'required|in:b2b,b2c',
            'credit_limit' => 'nullable|numeric|min:0',
            'payment_terms' => 'nullable|integer|min:0',
            'currency' => 'nullable|string|max:3',
            'tax_exempt' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        $customer = Customer::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Customer created successfully',
            'data' => $customer,
        ], 201);
    }

    /**
     * Display the specified customer
     */
    public function show(Customer $customer)
    {
        $customer->load(['addresses', 'sales']);

        return response()->json([
            'success' => true,
            'data' => $customer,
        ]);
    }

    /**
     * Update the specified customer
     */
    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'nullable|email|unique:customers,email,'.$customer->id,
            'phone' => 'sometimes|string|max:20',
            'kra_pin' => 'nullable|string|max:20',
            'customer_type' => 'sometimes|in:b2b,b2c',
            'credit_limit' => 'nullable|numeric|min:0',
            'payment_terms' => 'nullable|integer|min:0',
            'currency' => 'nullable|string|max:3',
            'tax_exempt' => 'boolean',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        $customer->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Customer updated successfully',
            'data' => $customer,
        ]);
    }

    /**
     * Remove the specified customer
     */
    public function destroy(Customer $customer)
    {
        $customer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Customer deleted successfully',
        ]);
    }

    /**
     * Get customer's sales history
     */
    public function sales(Request $request, Customer $customer)
    {
        $perPage = $request->get('per_page', 15);

        $sales = $customer->sales()
            ->with(['items.product', 'payments'])
            ->orderBy('sale_date', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $sales,
        ]);
    }

    /**
     * Get customer statement
     */
    public function statement(Request $request, Customer $customer)
    {
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        $sales = $customer->getStatement($startDate, $endDate);

        $summary = [
            'total_purchases' => $sales->sum('total_amount'),
            'total_paid' => $sales->sum('paid_amount'),
            'outstanding_balance' => $customer->outstanding_balance,
            'credit_limit' => $customer->credit_limit,
            'available_credit' => $customer->available_credit,
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'customer' => $customer,
                'summary' => $summary,
                'transactions' => $sales,
            ],
        ]);
    }

    /**
     * Update customer credit limit
     */
    public function updateCreditLimit(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'credit_limit' => 'required|numeric|min:0',
            'payment_terms' => 'nullable|integer|min:0',
        ]);

        $customer->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Credit limit updated successfully',
            'data' => [
                'credit_limit' => $customer->credit_limit,
                'outstanding_balance' => $customer->outstanding_balance,
                'available_credit' => $customer->available_credit,
            ],
        ]);
    }

    /**
     * Import customers from CSV/Excel
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,xlsx',
        ]);

        // This would typically use Laravel Excel or similar package
        // For now, return placeholder response

        return response()->json([
            'success' => true,
            'message' => 'Customer import functionality - implementation pending',
        ]);
    }
}
