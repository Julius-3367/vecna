<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'invoice_number',
        'customer_id',
        'sale_id',
        'issue_date',
        'due_date',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'paid_amount',
        'balance',
        'status',
        'payment_status',
        'kra_cu_invoice_number',
        'kra_qr_code',
        'terms',
        'notes',
        'sent_at',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'sent_at' => 'datetime',
    ];

    protected $appends = ['is_overdue', 'days_overdue'];

    /**
     * Boot model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($invoice) {
            if (empty($invoice->invoice_number)) {
                $invoice->invoice_number = static::generateInvoiceNumber();
            }

            $invoice->balance = $invoice->total_amount - ($invoice->paid_amount ?? 0);
            $invoice->payment_status = $invoice->balance <= 0 ? 'paid' : 'unpaid';
        });
    }

    /**
     * Generate unique invoice number
     */
    public static function generateInvoiceNumber(): string
    {
        $date = now()->format('Ymd');
        $lastInvoice = static::whereDate('created_at', today())
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastInvoice ? intval(substr($lastInvoice->invoice_number, -4)) + 1 : 1;

        return 'INV-'.$date.'-'.str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get customer
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get related sale
     */
    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * Check if invoice is overdue
     */
    public function getIsOverdueAttribute(): bool
    {
        if ($this->payment_status === 'paid') {
            return false;
        }

        return $this->due_date && $this->due_date->isPast();
    }

    /**
     * Get days overdue
     */
    public function getDaysOverdueAttribute(): int
    {
        if (! $this->is_overdue) {
            return 0;
        }

        return now()->diffInDays($this->due_date);
    }

    /**
     * Record payment
     */
    public function recordPayment(float $amount, string $method, $reference = null)
    {
        $this->paid_amount += $amount;
        $this->balance = $this->total_amount - $this->paid_amount;

        if ($this->balance <= 0) {
            $this->payment_status = 'paid';
            $this->status = 'paid';
        } elseif ($this->paid_amount > 0) {
            $this->payment_status = 'partially_paid';
        }

        $this->save();

        // Also update related sale if exists
        if ($this->sale_id) {
            $this->sale->recordPayment([
                'amount' => $amount,
                'payment_method' => $method,
                'reference' => $reference,
            ]);
        }

        return $this;
    }

    /**
     * Mark as sent
     */
    public function markAsSent()
    {
        $this->update([
            'sent_at' => now(),
            'status' => 'sent',
        ]);
    }

    /**
     * Scope: Overdue invoices
     */
    public function scopeOverdue($query)
    {
        return $query->where('payment_status', '!=', 'paid')
            ->where('due_date', '<', now());
    }

    /**
     * Scope: Unpaid invoices
     */
    public function scopeUnpaid($query)
    {
        return $query->where('payment_status', 'unpaid');
    }

    /**
     * Scope: Partially paid invoices
     */
    public function scopePartiallyPaid($query)
    {
        return $query->where('payment_status', 'partially_paid');
    }
}
