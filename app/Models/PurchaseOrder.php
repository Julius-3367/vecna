<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'po_number',
        'supplier_id',
        'order_date',
        'expected_delivery_date',
        'received_date',
        'location_id',
        'status',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'notes',
        'approved_by',
        'approved_at',
        'created_by',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_delivery_date' => 'date',
        'received_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    /**
     * Boot model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($po) {
            if (empty($po->po_number)) {
                $po->po_number = static::generatePONumber();
            }
        });
    }

    /**
     * Generate unique PO number
     */
    public static function generatePONumber(): string
    {
        $date = now()->format('Ymd');
        $lastPO = static::whereDate('created_at', today())
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastPO ? intval(substr($lastPO->po_number, -4)) + 1 : 1;

        return 'PO-'.$date.'-'.str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get supplier
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get purchase order items
     */
    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /**
     * Get location
     */
    public function location()
    {
        return $this->belongsTo(StockLocation::class, 'location_id');
    }

    /**
     * Get approver
     */
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get creator
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get goods received notes
     */
    public function grns()
    {
        return $this->hasMany(GoodsReceivedNote::class);
    }

    /**
     * Approve purchase order
     */
    public function approve($userId)
    {
        if ($this->status !== 'pending') {
            throw new \Exception('Only pending purchase orders can be approved');
        }

        $this->update([
            'status' => 'approved',
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        return $this;
    }

    /**
     * Receive items (create GRN)
     */
    public function receive(array $items, $userId)
    {
        if ($this->status !== 'approved') {
            throw new \Exception('Purchase order must be approved before receiving items');
        }

        // Create GRN
        $grn = GoodsReceivedNote::create([
            'grn_number' => GoodsReceivedNote::generateGRNNumber(),
            'purchase_order_id' => $this->id,
            'supplier_id' => $this->supplier_id,
            'location_id' => $this->location_id,
            'received_date' => now(),
            'received_by' => $userId,
            'status' => 'pending',
        ]);

        // Add GRN items and update stock
        foreach ($items as $item) {
            $poItem = $this->items()->where('product_id', $item['product_id'])->first();

            if (! $poItem) {
                throw new \Exception('Product not found in purchase order');
            }

            $grn->items()->create([
                'product_id' => $item['product_id'],
                'ordered_quantity' => $poItem->quantity,
                'received_quantity' => $item['received_quantity'],
                'rejected_quantity' => $item['rejected_quantity'] ?? 0,
                'unit_cost' => $poItem->unit_cost,
                'notes' => $item['notes'] ?? null,
            ]);

            // Update product stock
            $product = Product::find($item['product_id']);
            if ($product->track_stock && $item['received_quantity'] > 0) {
                $product->updateStock(
                    $item['received_quantity'],
                    'in',
                    $this->location_id,
                    "PO: {$this->po_number} | GRN: {$grn->grn_number}"
                );
            }
        }

        // Check if all items received
        $allReceived = $this->items->every(function ($item) {
            $receivedQty = $this->grns()->join('grn_items', 'goods_received_notes.id', '=', 'grn_items.grn_id')
                ->where('grn_items.product_id', $item->product_id)
                ->sum('grn_items.received_quantity');

            return $receivedQty >= $item->quantity;
        });

        if ($allReceived) {
            $this->update([
                'status' => 'received',
                'received_date' => now(),
            ]);
        } else {
            $this->update(['status' => 'partially_received']);
        }

        return $grn;
    }

    /**
     * Cancel purchase order
     */
    public function cancel($reason = null)
    {
        if (in_array($this->status, ['received', 'cancelled'])) {
            throw new \Exception('Cannot cancel a received or already cancelled purchase order');
        }

        $this->update([
            'status' => 'cancelled',
            'notes' => ($this->notes ?? '')."\nCancelled: ".($reason ?? 'No reason provided'),
        ]);

        return $this;
    }

    /**
     * Calculate totals from items
     */
    public function calculateTotals()
    {
        $subtotal = $this->items->sum(function ($item) {
            return $item->quantity * $item->unit_cost;
        });

        $taxAmount = $this->items->sum(function ($item) {
            $lineTotal = $item->quantity * $item->unit_cost;

            return $lineTotal * ($item->tax_rate / 100);
        });

        $this->update([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => ($subtotal - $this->discount_amount) + $taxAmount,
        ]);

        return $this;
    }

    /**
     * Scope: Pending approval
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: Approved
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope: Overdue deliveries
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', 'approved')
            ->where('expected_delivery_date', '<', now());
    }
}
