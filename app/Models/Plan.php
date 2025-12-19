<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'annual_price',
        'billing_period',
        'max_shops',
        'max_users',
        'max_transactions',
        'max_products',
        'max_locations',
        'features',
        'pos_sync',
        'mpesa_reconciliation',
        'hr_module',
        'crm_module',
        'mobile_apps',
        'ai_analytics',
        'custom_modules',
        'priority_support',
        'white_label',
        'transaction_overage_price',
        'location_overage_price',
        'user_overage_price',
        'sort_order',
        'is_active',
        'is_featured',
        'is_popular',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'annual_price' => 'decimal:2',
        'transaction_overage_price' => 'decimal:2',
        'location_overage_price' => 'decimal:2',
        'user_overage_price' => 'decimal:2',
        'features' => 'array',
        'pos_sync' => 'boolean',
        'mpesa_reconciliation' => 'boolean',
        'hr_module' => 'boolean',
        'crm_module' => 'boolean',
        'mobile_apps' => 'boolean',
        'ai_analytics' => 'boolean',
        'custom_modules' => 'boolean',
        'priority_support' => 'boolean',
        'white_label' => 'boolean',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'is_popular' => 'boolean',
    ];

    /**
     * Relationships
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopePopular($query)
    {
        return $query->where('is_popular', true);
    }

    /**
     * Check if plan has specific feature
     */
    public function hasFeature(string $feature): bool
    {
        if (property_exists($this, $feature) && is_bool($this->$feature)) {
            return $this->$feature;
        }

        return in_array($feature, $this->features ?? []);
    }

    /**
     * Calculate annual savings
     */
    public function getAnnualSavingsAttribute(): float
    {
        if (! $this->annual_price) {
            return 0;
        }

        $monthlyAnnual = $this->price * 12;

        return $monthlyAnnual - $this->annual_price;
    }

    /**
     * Get savings percentage
     */
    public function getSavingsPercentageAttribute(): int
    {
        if (! $this->annual_price || $this->price <= 0) {
            return 0;
        }

        $monthlyAnnual = $this->price * 12;

        return (int) round((($monthlyAnnual - $this->annual_price) / $monthlyAnnual) * 100);
    }

    /**
     * Check if usage exceeds plan limits
     */
    public function exceedsLimit(string $metric, int $value): bool
    {
        $limitField = 'max_'.$metric;

        if (! property_exists($this, $limitField)) {
            return false;
        }

        return $value > $this->$limitField;
    }

    /**
     * Calculate overage charge
     */
    public function calculateOverage(string $metric, int $actual, int $included): float
    {
        if ($actual <= $included) {
            return 0;
        }

        $overageField = $metric.'_overage_price';

        if (! property_exists($this, $overageField)) {
            return 0;
        }

        $excess = $actual - $included;

        return $excess * $this->$overageField;
    }
}
