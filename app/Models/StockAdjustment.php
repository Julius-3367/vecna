<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockAdjustment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'adjustment_number',
        'location_id',
        'adjustment_date',
        'status',
        'notes',
        'adjusted_by',
    ];

    protected $casts = [
        'adjustment_date' => 'date',
    ];

    /**
     * Generate unique adjustment number
     */
    public static function generateAdjustmentNumber(): string
    {
        $date = now()->format('Ymd');
        $lastAdjustment = static::whereDate('created_at', today())
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastAdjustment ? intval(substr($lastAdjustment->adjustment_number, -4)) + 1 : 1;

        return 'ADJ-'.$date.'-'.str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get location
     */
    public function location()
    {
        return $this->belongsTo(StockLocation::class, 'location_id');
    }

    /**
     * Get adjustment items
     */
    public function items()
    {
        return $this->hasMany(StockAdjustmentItem::class);
    }

    /**
     * Get user who made adjustment
     */
    public function adjustedBy()
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }
}
