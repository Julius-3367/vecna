<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'description', 'sku', 'barcode',
        'category_id', 'brand_id', 'unit_id',
        'cost_price', 'selling_price', 'wholesale_price', 'minimum_price',
        'tax_inclusive', 'tax_rate',
        'track_stock', 'stock_quantity', 'reorder_level', 'reorder_quantity',
        'minimum_order_quantity', 'maximum_order_quantity',
        'weight', 'length', 'width', 'height',
        'type', 'is_perishable', 'shelf_life_days',
        'image', 'images', 'metadata',
        'is_active', 'is_featured', 'published_at',
    ];

    protected $casts = [
        'images' => 'array',
        'metadata' => 'array',
        'cost_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
        'minimum_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'weight' => 'decimal:2',
        'track_stock' => 'boolean',
        'is_perishable' => 'boolean',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'published_at' => 'datetime',
    ];

    // Relationships
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function stockLocations(): HasMany
    {
        return $this->hasMany(StockLocation::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function suppliers(): HasManyThrough
    {
        return $this->hasManyThrough(Supplier::class, SupplierProduct::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeLowStock($query)
    {
        return $query->where('track_stock', true)
            ->whereColumn('stock_quantity', '<=', 'reorder_level');
    }

    public function scopeOutOfStock($query)
    {
        return $query->where('track_stock', true)
            ->where('stock_quantity', '<=', 0);
    }

    // Methods
    public function getProfitMargin(): float
    {
        if ($this->cost_price == 0) {
            return 0;
        }

        return (($this->selling_price - $this->cost_price) / $this->cost_price) * 100;
    }

    public function getStockValue(): float
    {
        return $this->stock_quantity * $this->cost_price;
    }

    public function isLowStock(): bool
    {
        return $this->track_stock && $this->stock_quantity <= $this->reorder_level;
    }

    public function isOutOfStock(): bool
    {
        return $this->track_stock && $this->stock_quantity <= 0;
    }

    public function updateStock(int $quantity, string $type, int $locationId, ?int $userId = null): void
    {
        $before = $this->stock_quantity;

        if ($type === 'sale' || $type === 'transfer_out' || $type === 'damage' || $type === 'expired') {
            $this->stock_quantity -= abs($quantity);
            $quantity = -abs($quantity);
        } else {
            $this->stock_quantity += abs($quantity);
            $quantity = abs($quantity);
        }

        $this->save();

        // Record movement
        $this->stockMovements()->create([
            'location_id' => $locationId,
            'type' => $type,
            'quantity' => $quantity,
            'quantity_before' => $before,
            'quantity_after' => $this->stock_quantity,
            'user_id' => $userId ?? auth()->id(),
        ]);

        // Update stock location
        $stockLocation = $this->stockLocations()->firstOrCreate(['location_id' => $locationId]);
        $stockLocation->increment('quantity', $quantity);

        // Check for low stock alert
        if ($this->isLowStock() && ! $this->isOutOfStock()) {
            StockAlert::create([
                'product_id' => $this->id,
                'location_id' => $locationId,
                'type' => 'low_stock',
                'message' => "{$this->name} is running low in stock. Current: {$this->stock_quantity}, Reorder level: {$this->reorder_level}",
            ]);
        } elseif ($this->isOutOfStock()) {
            StockAlert::create([
                'product_id' => $this->id,
                'location_id' => $locationId,
                'type' => 'out_of_stock',
                'message' => "{$this->name} is out of stock!",
            ]);
        }
    }

    public function getStockAtLocation(int $locationId): int
    {
        return $this->stockLocations()
            ->where('location_id', $locationId)
            ->value('quantity') ?? 0;
    }
}
