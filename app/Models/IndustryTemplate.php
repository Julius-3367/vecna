<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IndustryTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'categories',
        'products',
        'chart_of_accounts',
        'settings',
        'reports',
        'is_active',
        'usage_count',
        'sort_order',
    ];

    protected $casts = [
        'categories' => 'array',
        'products' => 'array',
        'chart_of_accounts' => 'array',
        'settings' => 'array',
        'reports' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Increment usage count
     */
    public function recordUsage()
    {
        $this->increment('usage_count');
    }

    /**
     * Apply template to tenant
     */
    public function applyToTenant($tenantId)
    {
        $tenant = \App\Models\Tenant::find($tenantId);

        if (! $tenant) {
            return false;
        }

        $tenant->run(function () {
            // Import categories
            if ($this->categories) {
                foreach ($this->categories as $categoryData) {
                    \App\Models\Category::create($categoryData);
                }
            }

            // Import products
            if ($this->products) {
                foreach ($this->products as $productData) {
                    \App\Models\Product::create($productData);
                }
            }

            // Apply settings
            if ($this->settings) {
                // Apply tenant-level settings
                foreach ($this->settings as $key => $value) {
                    // Store in tenant settings table or config
                }
            }
        });

        $this->recordUsage();

        return true;
    }
}
