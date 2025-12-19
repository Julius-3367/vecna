<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'supplier_number',
        'name',
        'email',
        'phone',
        'kra_pin',
        'supplier_category_id',
        'payment_terms',
        'credit_limit',
        'currency',
        'contact_person',
        'address',
        'city',
        'country',
        'website',
        'bank_name',
        'bank_account',
        'rating',
        'on_time_delivery_rate',
        'quality_rating',
        'total_purchases',
        'total_paid',
        'outstanding_balance',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'rating' => 'decimal:2',
        'on_time_delivery_rate' => 'decimal:2',
        'quality_rating' => 'decimal:2',
        'total_purchases' => 'decimal:2',
        'total_paid' => 'decimal:2',
        'outstanding_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Boot model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($supplier) {
            if (empty($supplier->supplier_number)) {
                $supplier->supplier_number = static::generateSupplierNumber();
            }
        });
    }

    /**
     * Generate unique supplier number
     */
    public static function generateSupplierNumber(): string
    {
        $date = now()->format('Ymd');
        $lastSupplier = static::whereDate('created_at', today())
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastSupplier ? intval(substr($lastSupplier->supplier_number, -4)) + 1 : 1;

        return 'SUP-'.$date.'-'.str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get supplier category
     */
    public function category()
    {
        return $this->belongsTo(SupplierCategory::class, 'supplier_category_id');
    }

    /**
     * Get purchase orders
     */
    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    /**
     * Get supplier products (catalog)
     */
    public function products()
    {
        return $this->hasMany(SupplierProduct::class);
    }

    /**
     * Get supplier ratings
     */
    public function ratings()
    {
        return $this->hasMany(SupplierRating::class);
    }

    /**
     * Get supplier payments
     */
    public function payments()
    {
        return $this->hasMany(SupplierPayment::class);
    }

    /**
     * Update performance metrics
     */
    public function updatePerformanceMetrics()
    {
        // Calculate on-time delivery rate
        $totalDeliveries = $this->purchaseOrders()
            ->where('status', 'received')
            ->count();

        if ($totalDeliveries > 0) {
            $onTimeDeliveries = $this->purchaseOrders()
                ->where('status', 'received')
                ->whereRaw('received_date <= expected_delivery_date')
                ->count();

            $onTimeRate = ($onTimeDeliveries / $totalDeliveries) * 100;

            $this->update(['on_time_delivery_rate' => $onTimeRate]);
        }

        // Update average rating
        $avgRating = $this->ratings()->avg('rating');
        $avgQuality = $this->ratings()->avg('quality_score');

        $this->update([
            'rating' => $avgRating ?? 0,
            'quality_rating' => $avgQuality ?? 0,
        ]);
    }

    /**
     * Update purchase totals
     */
    public function updatePurchaseTotals()
    {
        $totals = $this->purchaseOrders()
            ->where('status', '!=', 'cancelled')
            ->selectRaw('SUM(total_amount) as total_purchases')
            ->first();

        $totalPaid = $this->payments()
            ->sum('amount');

        $this->update([
            'total_purchases' => $totals->total_purchases ?? 0,
            'total_paid' => $totalPaid,
            'outstanding_balance' => ($totals->total_purchases ?? 0) - $totalPaid,
        ]);
    }

    /**
     * Scope: Active suppliers
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Top rated suppliers
     */
    public function scopeTopRated($query, $minRating = 4.0)
    {
        return $query->where('rating', '>=', $minRating);
    }
}
