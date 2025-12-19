<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_number',
        'name',
        'email',
        'phone',
        'kra_pin',
        'customer_type',
        'credit_limit',
        'payment_terms',
        'currency',
        'tax_exempt',
        'loyalty_points',
        'total_purchases',
        'total_paid',
        'outstanding_balance',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'loyalty_points' => 'integer',
        'total_purchases' => 'decimal:2',
        'total_paid' => 'decimal:2',
        'outstanding_balance' => 'decimal:2',
        'tax_exempt' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $appends = ['available_credit'];

    /**
     * Boot model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($customer) {
            if (empty($customer->customer_number)) {
                $customer->customer_number = static::generateCustomerNumber();
            }
        });
    }

    /**
     * Generate unique customer number
     */
    public static function generateCustomerNumber(): string
    {
        $date = now()->format('Ymd');
        $lastCustomer = static::whereDate('created_at', today())
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastCustomer ? intval(substr($lastCustomer->customer_number, -4)) + 1 : 1;

        return 'CUS-'.$date.'-'.str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get customer's sales
     */
    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * Get customer's addresses
     */
    public function addresses()
    {
        return $this->hasMany(CustomerAddress::class);
    }

    /**
     * Get customer's communications
     */
    public function communications()
    {
        return $this->hasMany(CustomerCommunication::class);
    }

    /**
     * Get customer's notes
     */
    public function notes()
    {
        return $this->hasMany(CustomerNote::class);
    }

    /**
     * Get loyalty transactions
     */
    public function loyaltyTransactions()
    {
        return $this->hasMany(LoyaltyTransaction::class);
    }

    /**
     * Get available credit
     */
    public function getAvailableCreditAttribute(): float
    {
        return max(0, $this->credit_limit - $this->outstanding_balance);
    }

    /**
     * Check if customer can purchase on credit
     */
    public function canPurchaseOnCredit(float $amount): bool
    {
        if ($this->customer_type !== 'b2b') {
            return false;
        }

        return $this->available_credit >= $amount;
    }

    /**
     * Add loyalty points
     */
    public function addLoyaltyPoints(int $points, string $description, $referenceId = null, $referenceType = null)
    {
        $this->increment('loyalty_points', $points);

        return $this->loyaltyTransactions()->create([
            'points' => $points,
            'type' => 'earned',
            'description' => $description,
            'reference_id' => $referenceId,
            'reference_type' => $referenceType,
            'balance_after' => $this->loyalty_points,
        ]);
    }

    /**
     * Redeem loyalty points
     */
    public function redeemLoyaltyPoints(int $points, string $description, $referenceId = null, $referenceType = null)
    {
        if ($this->loyalty_points < $points) {
            throw new \Exception('Insufficient loyalty points');
        }

        $this->decrement('loyalty_points', $points);

        return $this->loyaltyTransactions()->create([
            'points' => -$points,
            'type' => 'redeemed',
            'description' => $description,
            'reference_id' => $referenceId,
            'reference_type' => $referenceType,
            'balance_after' => $this->loyalty_points,
        ]);
    }

    /**
     * Update purchase totals
     */
    public function updatePurchaseTotals()
    {
        $totals = $this->sales()
            ->where('status', '!=', 'cancelled')
            ->selectRaw('SUM(total_amount) as total_purchases, SUM(paid_amount) as total_paid')
            ->first();

        $this->update([
            'total_purchases' => $totals->total_purchases ?? 0,
            'total_paid' => $totals->total_paid ?? 0,
            'outstanding_balance' => ($totals->total_purchases ?? 0) - ($totals->total_paid ?? 0),
        ]);
    }

    /**
     * Get customer statement
     */
    public function getStatement($startDate = null, $endDate = null)
    {
        $query = $this->sales()->with('payments');

        if ($startDate) {
            $query->whereDate('sale_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('sale_date', '<=', $endDate);
        }

        return $query->orderBy('sale_date')->get();
    }

    /**
     * Scope: Active customers
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: B2B customers
     */
    public function scopeB2b($query)
    {
        return $query->where('customer_type', 'b2b');
    }

    /**
     * Scope: B2C customers
     */
    public function scopeB2c($query)
    {
        return $query->where('customer_type', 'b2c');
    }
}
