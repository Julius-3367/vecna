<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains, SoftDeletes;

    protected $fillable = [
        'id',
        'business_name',
        'subdomain',
        'custom_domain',
        'email',
        'phone',
        'industry',
        'country',
        'currency',
        'timezone',
        'language',
        'plan_id',
        'status',
        'trial_ends_at',
        'suspended_at',
        'suspension_reason',
        'settings',
        'metadata',
        'industry_template',
    ];

    protected $casts = [
        'settings' => 'array',
        'metadata' => 'array',
        'trial_ends_at' => 'datetime',
        'suspended_at' => 'datetime',
    ];

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'business_name',
            'subdomain',
            'custom_domain',
            'email',
            'phone',
            'industry',
            'country',
            'currency',
            'timezone',
            'language',
            'plan_id',
            'status',
            'trial_ends_at',
            'suspended_at',
            'suspension_reason',
            'settings',
            'metadata',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function usageRecords(): HasMany
    {
        return $this->hasMany(UsageRecord::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function activeSubscription()
    {
        return $this->hasOne(Subscription::class)
            ->where('status', 'active')
            ->latest();
    }

    public function billingInvoices()
    {
        return $this->hasMany(BillingInvoice::class);
    }

    // Status helpers
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isTrial(): bool
    {
        return $this->status === 'trial' && $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function suspend(?string $reason = null): void
    {
        $this->update([
            'status' => 'suspended',
            'suspended_at' => now(),
            'suspension_reason' => $reason,
        ]);
    }

    public function activate(): void
    {
        $this->update([
            'status' => 'active',
            'suspended_at' => null,
            'suspension_reason' => null,
        ]);
    }

    // Usage tracking
    public function recordUsage(string $metric, int $quantity): void
    {
        $period = now()->format('Y-m-d');

        $this->usageRecords()->updateOrCreate(
            [
                'metric' => $metric,
                'period' => $period,
            ],
            [
                'quantity' => \DB::raw("quantity + {$quantity}"),
            ]
        );
    }

    public function getUsage(string $metric, ?string $period = null): int
    {
        $query = $this->usageRecords()->where('metric', $metric);

        if ($period) {
            $query->where('period', $period);
        } else {
            $query->whereMonth('period', now()->month)
                ->whereYear('period', now()->year);
        }

        return $query->sum('quantity');
    }

    // Feature access based on plan
    public function hasFeature(string $feature): bool
    {
        if (! $this->plan) {
            return false;
        }

        return in_array($feature, $this->plan->features ?? []);
    }

    public function canAddShop(): bool
    {
        if (! $this->plan) {
            return false;
        }

        // Count current shops (locations) in tenant database
        $currentShops = tenancy()->run($this, function () {
            return \App\Models\Location::count();
        });

        return $currentShops < $this->plan->max_shops;
    }

    public function canAddUser(): bool
    {
        if (! $this->plan) {
            return false;
        }

        $currentUsers = tenancy()->run($this, function () {
            return \App\Models\User::count();
        });

        return $currentUsers < $this->plan->max_users;
    }

    public function remainingTransactions(): int
    {
        if (! $this->plan || $this->plan->max_transactions === null) {
            return PHP_INT_MAX;
        }

        $used = $this->getUsage('transactions');

        return max(0, $this->plan->max_transactions - $used);
    }
}
