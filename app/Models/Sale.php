<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sale_number', 'customer_id', 'location_id', 'user_id',
        'subtotal', 'tax_amount', 'discount_amount', 'shipping_amount',
        'total_amount', 'paid_amount',
        'discount_type', 'discount_value',
        'payment_status', 'payment_method',
        'status', 'fulfillment_status',
        'sale_date', 'due_date', 'fulfilled_at',
        'shipping_address_id', 'delivery_method', 'delivery_notes',
        'channel', 'pos_terminal_id',
        'notes', 'metadata',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'discount_value' => 'decimal:2',
        'metadata' => 'array',
        'sale_date' => 'datetime',
        'due_date' => 'datetime',
        'fulfilled_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($sale) {
            if (! $sale->sale_number) {
                $sale->sale_number = static::generateSaleNumber();
            }
        });
    }

    public static function generateSaleNumber(): string
    {
        $prefix = 'SAL-';
        $date = now()->format('Ymd');
        $latest = static::whereDate('created_at', today())->count() + 1;

        return $prefix.$date.'-'.str_pad($latest, 4, '0', STR_PAD_LEFT);
    }

    // Relationships
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    public function invoice(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class);
    }

    // Methods
    public function calculateTotals(): void
    {
        $this->subtotal = $this->items->sum('line_total');
        $this->tax_amount = $this->items->sum('tax_amount');

        // Apply discount
        if ($this->discount_type === 'percentage') {
            $this->discount_amount = ($this->subtotal * $this->discount_value) / 100;
        } elseif ($this->discount_type === 'fixed') {
            $this->discount_amount = $this->discount_value;
        }

        $this->total_amount = $this->subtotal + $this->tax_amount - $this->discount_amount + $this->shipping_amount;
        $this->save();
    }

    public function updatePaymentStatus(): void
    {
        if ($this->paid_amount >= $this->total_amount) {
            $this->payment_status = 'paid';
        } elseif ($this->paid_amount > 0) {
            $this->payment_status = 'partial';
        } else {
            $this->payment_status = 'pending';
        }

        $this->save();
    }

    public function recordPayment(array $data): SalePayment
    {
        $payment = $this->payments()->create($data);

        $this->paid_amount += $payment->amount;
        $this->updatePaymentStatus();

        // Record usage for tenant billing
        tenant()->recordUsage('transactions', 1);

        return $payment;
    }

    public function getBalanceAttribute(): float
    {
        return $this->total_amount - $this->paid_amount;
    }

    public function getProfit(): float
    {
        $totalCost = $this->items->sum(function ($item) {
            return $item->quantity * $item->unit_cost;
        });

        return $this->subtotal - $totalCost;
    }
}
