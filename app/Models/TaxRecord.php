<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'tax_type',
        'period_month',
        'period_year',
        'sales_vat',
        'purchase_vat',
        'net_vat',
        'status',
        'submission_date',
        'reference_number',
        'response_data',
    ];

    protected $casts = [
        'period_month' => 'integer',
        'period_year' => 'integer',
        'sales_vat' => 'decimal:2',
        'purchase_vat' => 'decimal:2',
        'net_vat' => 'decimal:2',
        'submission_date' => 'datetime',
    ];
}
