<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BillingInvoice;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubscriptionController extends Controller
{
    /**
     * Get all available plans
     */
    public function plans()
    {
        $plans = Plan::active()
            ->orderBy('sort_order')
            ->get()
            ->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'slug' => $plan->slug,
                    'description' => $plan->description,
                    'price' => $plan->price,
                    'annual_price' => $plan->annual_price,
                    'savings_percentage' => $plan->savings_percentage,
                    'limits' => [
                        'shops' => $plan->max_shops,
                        'users' => $plan->max_users,
                        'transactions' => $plan->max_transactions,
                        'products' => $plan->max_products,
                        'locations' => $plan->max_locations,
                    ],
                    'features' => [
                        'pos_sync' => $plan->pos_sync,
                        'mpesa_reconciliation' => $plan->mpesa_reconciliation,
                        'hr_module' => $plan->hr_module,
                        'crm_module' => $plan->crm_module,
                        'mobile_apps' => $plan->mobile_apps,
                        'ai_analytics' => $plan->ai_analytics,
                        'custom_modules' => $plan->custom_modules,
                        'priority_support' => $plan->priority_support,
                        'white_label' => $plan->white_label,
                    ],
                    'is_popular' => $plan->is_popular,
                    'is_featured' => $plan->is_featured,
                ];
            });

        return response()->json([
            'data' => $plans,
        ]);
    }

    /**
     * Get current subscription
     */
    public function current(Request $request)
    {
        $tenant = $request->user()->tenant ?? tenant();

        $subscription = $tenant->activeSubscription()
            ->with('plan')
            ->first();

        if (! $subscription) {
            return response()->json([
                'message' => 'No active subscription',
            ], 404);
        }

        return response()->json([
            'data' => [
                'id' => $subscription->id,
                'plan' => [
                    'name' => $subscription->plan->name,
                    'slug' => $subscription->plan->slug,
                ],
                'status' => $subscription->status,
                'billing_period' => $subscription->billing_period,
                'amount' => $subscription->amount,
                'current_period_start' => $subscription->current_period_start,
                'current_period_end' => $subscription->current_period_end,
                'next_billing_date' => $subscription->next_billing_date,
                'usage' => [
                    'transactions' => [
                        'used' => $subscription->current_period_transactions,
                        'limit' => $subscription->plan->max_transactions,
                        'remaining' => max(0, $subscription->plan->max_transactions - $subscription->current_period_transactions),
                    ],
                    'shops' => [
                        'used' => $subscription->current_period_shops,
                        'limit' => $subscription->plan->max_shops,
                    ],
                    'users' => [
                        'used' => $subscription->current_period_users,
                        'limit' => $subscription->plan->max_users,
                    ],
                ],
                'overages' => [
                    'amount' => $subscription->overage_amount,
                    'details' => $subscription->overage_details,
                ],
                'auto_renew' => $subscription->auto_renew,
                'trial_ends_at' => $subscription->trial_ends_at,
            ],
        ]);
    }

    /**
     * Subscribe to a plan
     */
    public function subscribe(Request $request)
    {
        $validated = $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'billing_period' => 'required|in:monthly,annual',
            'payment_method' => 'required|in:mpesa,stripe,bank_transfer',
            'payment_reference' => 'nullable|string',
        ]);

        $tenant = $request->user()->tenant ?? tenant();
        $plan = Plan::findOrFail($validated['plan_id']);

        // Check if already has active subscription
        $existingSubscription = $tenant->activeSubscription()->first();
        if ($existingSubscription) {
            return response()->json([
                'message' => 'Already have an active subscription. Please upgrade or cancel first.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $amount = $validated['billing_period'] === 'annual'
                ? $plan->annual_price
                : $plan->price;

            $periodStart = now();
            $periodEnd = $validated['billing_period'] === 'annual'
                ? $periodStart->copy()->addYear()
                : $periodStart->copy()->addMonth();

            $subscription = Subscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'billing_period' => $validated['billing_period'],
                'amount' => $amount,
                'payment_method' => $validated['payment_method'],
                'payment_reference' => $validated['payment_reference'] ?? null,
                'current_period_start' => $periodStart,
                'current_period_end' => $periodEnd,
                'next_billing_date' => $periodEnd,
                'auto_renew' => true,
            ]);

            // Generate first invoice
            $invoice = $subscription->generateInvoice();

            // Update tenant subscription status
            $tenant->update([
                'subscription_status' => 'active',
                'plan_id' => $plan->id,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Subscription created successfully',
                'data' => [
                    'subscription' => $subscription,
                    'invoice' => $invoice,
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to create subscription',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel subscription
     */
    public function cancel(Request $request)
    {
        $validated = $request->validate([
            'immediately' => 'boolean',
            'reason' => 'nullable|string',
        ]);

        $tenant = $request->user()->tenant ?? tenant();
        $subscription = $tenant->activeSubscription()->first();

        if (! $subscription) {
            return response()->json([
                'message' => 'No active subscription to cancel',
            ], 404);
        }

        $subscription->cancel($validated['immediately'] ?? false);

        return response()->json([
            'message' => $validated['immediately']
                ? 'Subscription cancelled immediately'
                : 'Subscription will cancel at end of billing period',
            'data' => $subscription,
        ]);
    }

    /**
     * Resume cancelled subscription
     */
    public function resume(Request $request)
    {
        $tenant = $request->user()->tenant ?? tenant();
        $subscription = Subscription::where('tenant_id', $tenant->id)
            ->where('status', 'cancelled')
            ->whereNotNull('ends_at')
            ->where('ends_at', '>', now())
            ->first();

        if (! $subscription) {
            return response()->json([
                'message' => 'No subscription available to resume',
            ], 404);
        }

        if ($subscription->resume()) {
            return response()->json([
                'message' => 'Subscription resumed successfully',
                'data' => $subscription,
            ]);
        }

        return response()->json([
            'message' => 'Failed to resume subscription',
        ], 422);
    }

    /**
     * Change subscription plan
     */
    public function changePlan(Request $request)
    {
        $validated = $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'immediately' => 'boolean',
        ]);

        $tenant = $request->user()->tenant ?? tenant();
        $subscription = $tenant->activeSubscription()->first();

        if (! $subscription) {
            return response()->json([
                'message' => 'No active subscription',
            ], 404);
        }

        $newPlan = Plan::findOrFail($validated['plan_id']);

        if ($subscription->plan_id === $newPlan->id) {
            return response()->json([
                'message' => 'Already subscribed to this plan',
            ], 422);
        }

        $subscription->changePlan($newPlan, $validated['immediately'] ?? false);

        return response()->json([
            'message' => 'Plan changed successfully',
            'data' => $subscription->fresh('plan'),
        ]);
    }

    /**
     * Get billing invoices
     */
    public function invoices(Request $request)
    {
        $tenant = $request->user()->tenant ?? tenant();

        $invoices = BillingInvoice::where('tenant_id', $tenant->id)
            ->with('subscription.plan')
            ->latest()
            ->paginate(20);

        return response()->json($invoices);
    }

    /**
     * Get specific invoice
     */
    public function invoice(Request $request, $id)
    {
        $tenant = $request->user()->tenant ?? tenant();

        $invoice = BillingInvoice::where('tenant_id', $tenant->id)
            ->with('subscription.plan')
            ->findOrFail($id);

        return response()->json([
            'data' => $invoice,
        ]);
    }

    /**
     * Pay invoice
     */
    public function payInvoice(Request $request, $id)
    {
        $validated = $request->validate([
            'payment_method' => 'required|in:mpesa,stripe,bank_transfer',
            'payment_reference' => 'required|string',
        ]);

        $tenant = $request->user()->tenant ?? tenant();
        $invoice = BillingInvoice::where('tenant_id', $tenant->id)
            ->findOrFail($id);

        if ($invoice->isFullyPaid()) {
            return response()->json([
                'message' => 'Invoice already paid',
            ], 422);
        }

        $invoice->markAsPaid(
            $validated['payment_method'],
            $validated['payment_reference']
        );

        return response()->json([
            'message' => 'Payment recorded successfully',
            'data' => $invoice->fresh(),
        ]);
    }
}
