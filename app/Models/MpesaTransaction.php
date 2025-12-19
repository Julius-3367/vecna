<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MpesaTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'merchant_request_id',
        'checkout_request_id',
        'conversation_id',
        'originator_conversation_id',
        'phone_number',
        'amount',
        'account_reference',
        'transaction_type',
        'status',
        'mpesa_receipt_number',
        'transaction_date',
        'result_code',
        'result_desc',
        'request_data',
        'response_data',
        'reconciled',
        'reconciled_at',
        'record_id',
        'record_type',
        // C2B fields
        'trans_id',
        'trans_time',
        'trans_amount',
        'business_short_code',
        'bill_ref_number',
        'invoice_number',
        'org_account_balance',
        'third_party_trans_id',
        'msisdn',
        'first_name',
        'middle_name',
        'last_name',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'trans_amount' => 'decimal:2',
        'transaction_date' => 'datetime',
        'reconciled' => 'boolean',
        'reconciled_at' => 'datetime',
        'request_data' => 'array',
        'response_data' => 'array',
    ];

    /**
     * Scope: Completed transactions
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope: Pending transactions
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: Failed transactions
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope: Unreconciled transactions
     */
    public function scopeUnreconciled($query)
    {
        return $query->where('reconciled', false);
    }

    /**
     * Get related record (polymorphic)
     */
    public function record()
    {
        return $this->morphTo();
    }
}
