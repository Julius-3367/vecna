<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'location_id',
        'alert_type',
        'threshold',
        'current_quantity',
        'is_resolved',
        'resolved_at',
        'resolved_by',
        'action_taken',
    ];

    protected $casts = [
        'threshold' => 'integer',
        'current_quantity' => 'integer',
        'is_resolved' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    /**
     * Get the product
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the location
     */
    public function location()
    {
        return $this->belongsTo(StockLocation::class, 'location_id');
    }

    /**
     * Get user who resolved alert
     */
    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Scope: Unresolved alerts
     */
    public function scopeUnresolved($query)
    {
        return $query->where('is_resolved', false);
    }

    /**
     * Scope: Low stock alerts
     */
    public function scopeLowStock($query)
    {
        return $query->where('alert_type', 'low_stock');
    }

    /**
     * Scope: Out of stock alerts
     */
    public function scopeOutOfStock($query)
    {
        return $query->where('alert_type', 'out_of_stock');
    }
}
