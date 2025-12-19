<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockTransfer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'transfer_number',
        'from_location_id',
        'to_location_id',
        'transfer_date',
        'status',
        'notes',
        'transferred_by',
        'received_by',
        'received_at',
    ];

    protected $casts = [
        'transfer_date' => 'date',
        'received_at' => 'datetime',
    ];

    /**
     * Generate unique transfer number
     */
    public static function generateTransferNumber(): string
    {
        $date = now()->format('Ymd');
        $lastTransfer = static::whereDate('created_at', today())
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastTransfer ? intval(substr($lastTransfer->transfer_number, -4)) + 1 : 1;

        return 'TRF-'.$date.'-'.str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get source location
     */
    public function fromLocation()
    {
        return $this->belongsTo(StockLocation::class, 'from_location_id');
    }

    /**
     * Get destination location
     */
    public function toLocation()
    {
        return $this->belongsTo(StockLocation::class, 'to_location_id');
    }

    /**
     * Get transfer items
     */
    public function items()
    {
        return $this->hasMany(StockTransferItem::class);
    }

    /**
     * Get user who initiated transfer
     */
    public function transferredBy()
    {
        return $this->belongsTo(User::class, 'transferred_by');
    }

    /**
     * Get user who received transfer
     */
    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
