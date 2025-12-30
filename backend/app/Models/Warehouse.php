<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'location',
        'address',
        'phone',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (Warehouse $warehouse) {
            // Ensure only one default warehouse
            if ($warehouse->is_default) {
                static::where('id', '!=', $warehouse->id)->update(['is_default' => false]);
            }
        });
    }

    /**
     * Stock levels in this warehouse.
     */
    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    /**
     * Stock movements in this warehouse.
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Get the default warehouse.
     */
    public static function getDefault(): ?self
    {
        return static::where('is_default', true)->first();
    }

    /**
     * Get total product count in warehouse.
     */
    public function getProductCountAttribute(): int
    {
        return $this->stockLevels()->where('quantity', '>', 0)->count();
    }

    /**
     * Get total stock value in warehouse.
     */
    public function getTotalStockValueAttribute(): float
    {
        return $this->stockLevels()
            ->join('products', 'stock_levels.product_id', '=', 'products.id')
            ->selectRaw('SUM(stock_levels.quantity * products.cost_price) as total')
            ->value('total') ?? 0.0;
    }

    /**
     * Scope for active warehouses.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
