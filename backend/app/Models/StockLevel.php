<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLevel extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'quantity',
        'reserved_quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'reserved_quantity' => 'integer',
    ];

    /**
     * Product relationship.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Warehouse relationship.
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Get available quantity (quantity - reserved).
     */
    public function getAvailableQuantityAttribute(): int
    {
        return $this->quantity - $this->reserved_quantity;
    }

    /**
     * Check if stock is low.
     */
    public function getIsLowStockAttribute(): bool
    {
        return $this->product && $this->product->stock_tracked 
            && $this->quantity <= $this->product->min_stock_level;
    }

    /**
     * Reserve stock.
     */
    public function reserve(int $quantity): bool
    {
        if ($this->available_quantity >= $quantity) {
            $this->reserved_quantity += $quantity;
            return $this->save();
        }
        return false;
    }

    /**
     * Release reserved stock.
     */
    public function releaseReservation(int $quantity): bool
    {
        $quantity = min($quantity, $this->reserved_quantity);
        $this->reserved_quantity -= $quantity;
        return $this->save();
    }

    /**
     * Scope for low stock.
     */
    public function scopeLowStock($query)
    {
        return $query->whereHas('product', function ($q) {
            $q->where('stock_tracked', true);
        })->whereRaw('quantity <= (SELECT min_stock_level FROM products WHERE products.id = stock_levels.product_id)');
    }

    /**
     * Scope for out of stock.
     */
    public function scopeOutOfStock($query)
    {
        return $query->where('quantity', '<=', 0);
    }

    /**
     * Find or create stock level for product and warehouse.
     */
    public static function findOrCreateForProductAndWarehouse(int $productId, int $warehouseId): self
    {
        return static::firstOrCreate(
            ['product_id' => $productId, 'warehouse_id' => $warehouseId],
            ['quantity' => 0, 'reserved_quantity' => 0]
        );
    }
}
