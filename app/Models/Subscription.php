<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'plan_id',
        'status',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'cancelled_at',
        'ends_at',
        'billing_period',
        'amount',
        'payment_method',
        'payment_reference',
        'auto_renew',
        'next_billing_date',
        'current_period_transactions',
        'current_period_shops',
        'current_period_users',
        'overage_amount',
        'overage_details',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'cancelled_at' => 'datetime',
        'ends_at' => 'datetime',
        'next_billing_date' => 'datetime',
        'amount' => 'decimal:2',
        'overage_amount' => 'decimal:2',
        'overage_details' => 'array',
        'auto_renew' => 'boolean',
    ];

    /**
     * Relationships
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function invoices()
    {
        return $this->hasMany(BillingInvoice::class);
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeTrialing($query)
    {
        return $query->where('status', 'trialing');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeDueForRenewal($query)
    {
        return $query->where('auto_renew', true)
            ->where('next_billing_date', '<=', now())
            ->whereIn('status', ['active', 'trialing']);
    }

    /**
     * Check if subscription is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active' &&
               (! $this->ends_at || $this->ends_at->isFuture());
    }

    /**
     * Check if subscription is on trial
     */
    public function onTrial(): bool
    {
        return $this->status === 'trialing' &&
               $this->trial_ends_at &&
               $this->trial_ends_at->isFuture();
    }

    /**
     * Check if subscription is cancelled
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled' ||
               ($this->ends_at && $this->ends_at->isPast());
    }

    /**
     * Cancel subscription
     */
    public function cancel(bool $immediately = false)
    {
        if ($immediately) {
            $this->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'ends_at' => now(),
                'auto_renew' => false,
            ]);
        } else {
            // Cancel at end of billing period
            $this->update([
                'cancelled_at' => now(),
                'ends_at' => $this->current_period_end,
                'auto_renew' => false,
            ]);
        }
    }

    /**
     * Resume cancelled subscription
     */
    public function resume()
    {
        if (! $this->isCancelled() || $this->ends_at->isPast()) {
            return false;
        }

        $this->update([
            'status' => 'active',
            'cancelled_at' => null,
            'ends_at' => null,
            'auto_renew' => true,
        ]);

        return true;
    }

    /**
     * Renew subscription for next period
     */
    public function renew()
    {
        $periodStart = $this->current_period_end ?? now();
        $periodEnd = $this->billing_period === 'annual'
            ? $periodStart->copy()->addYear()
            : $periodStart->copy()->addMonth();

        $this->update([
            'status' => 'active',
            'current_period_start' => $periodStart,
            'current_period_end' => $periodEnd,
            'next_billing_date' => $periodEnd,
            'current_period_transactions' => 0,
            'current_period_shops' => 0,
            'current_period_users' => 0,
            'overage_amount' => 0,
            'overage_details' => null,
        ]);

        // Generate invoice
        $this->generateInvoice();
    }

    /**
     * Track usage
     */
    public function recordTransaction()
    {
        $this->increment('current_period_transactions');
        $this->calculateOverages();
    }

    public function recordShop()
    {
        $this->increment('current_period_shops');
        $this->calculateOverages();
    }

    public function recordUser()
    {
        $this->increment('current_period_users');
        $this->calculateOverages();
    }

    /**
     * Calculate overage charges
     */
    protected function calculateOverages()
    {
        $overages = [];
        $totalOverage = 0;

        // Transaction overage
        if ($this->current_period_transactions > $this->plan->max_transactions) {
            $excess = $this->current_period_transactions - $this->plan->max_transactions;
            $charge = $excess * $this->plan->transaction_overage_price;
            $overages['transactions'] = [
                'limit' => $this->plan->max_transactions,
                'actual' => $this->current_period_transactions,
                'excess' => $excess,
                'charge' => $charge,
            ];
            $totalOverage += $charge;
        }

        // Shop/Location overage
        if ($this->current_period_shops > $this->plan->max_shops) {
            $excess = $this->current_period_shops - $this->plan->max_shops;
            $charge = $excess * $this->plan->location_overage_price;
            $overages['shops'] = [
                'limit' => $this->plan->max_shops,
                'actual' => $this->current_period_shops,
                'excess' => $excess,
                'charge' => $charge,
            ];
            $totalOverage += $charge;
        }

        // User overage
        if ($this->current_period_users > $this->plan->max_users) {
            $excess = $this->current_period_users - $this->plan->max_users;
            $charge = $excess * $this->plan->user_overage_price;
            $overages['users'] = [
                'limit' => $this->plan->max_users,
                'actual' => $this->current_period_users,
                'excess' => $excess,
                'charge' => $charge,
            ];
            $totalOverage += $charge;
        }

        $this->update([
            'overage_amount' => $totalOverage,
            'overage_details' => $overages,
        ]);
    }

    /**
     * Generate invoice for billing period
     */
    public function generateInvoice()
    {
        $lineItems = [
            [
                'description' => "{$this->plan->name} Plan - ".ucfirst($this->billing_period),
                'quantity' => 1,
                'unit_price' => $this->amount,
                'amount' => $this->amount,
            ],
        ];

        // Add overage charges
        if ($this->overage_amount > 0 && $this->overage_details) {
            foreach ($this->overage_details as $type => $details) {
                $lineItems[] = [
                    'description' => ucfirst($type)." overage ({$details['excess']} extra)",
                    'quantity' => $details['excess'],
                    'unit_price' => $details['charge'] / $details['excess'],
                    'amount' => $details['charge'],
                ];
            }
        }

        $subtotal = $this->amount + $this->overage_amount;
        $taxAmount = $subtotal * 0.16; // 16% VAT
        $total = $subtotal + $taxAmount;

        return BillingInvoice::create([
            'tenant_id' => $this->tenant_id,
            'subscription_id' => $this->id,
            'invoice_number' => BillingInvoice::generateInvoiceNumber(),
            'invoice_date' => now(),
            'due_date' => now()->addDays(7),
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $total,
            'line_items' => $lineItems,
            'status' => 'pending',
            'period_start' => $this->current_period_start,
            'period_end' => $this->current_period_end,
        ]);
    }

    /**
     * Upgrade/downgrade to different plan
     */
    public function changePlan(Plan $newPlan, bool $immediately = false)
    {
        $oldPlan = $this->plan;

        if ($immediately) {
            // Immediate change - prorate if needed
            $this->update([
                'plan_id' => $newPlan->id,
                'amount' => $newPlan->price,
            ]);
        } else {
            // Change at next billing cycle
            // Store pending change in metadata or separate table
        }

        $this->calculateOverages();
    }
}
