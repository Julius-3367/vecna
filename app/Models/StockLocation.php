<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockLocation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'address',
        'city',
        'phone',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get stock movements for this location
     */
    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class, 'location_id');
    }

    /**
     * Get sales from this location
     */
    public function sales()
    {
        return $this->hasMany(Sale::class, 'location_id');
    }

    /**
     * Get users assigned to this location
     */
    public function users()
    {
        return $this->hasMany(User::class, 'location_id');
    }

    /**
     * Scope: Active locations
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Default location
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}
