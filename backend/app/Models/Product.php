<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'sku',
        'barcode',
        'description',
        'category_id',
        'tax_class_id',
        'cost_price',
        'price',
        'stock_tracked',
        'min_stock_level',
        'is_active',
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'price' => 'decimal:2',
        'stock_tracked' => 'boolean',
        'min_stock_level' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Product $product) {
            if (empty($product->slug)) {
                $product->slug = static::generateUniqueSlug($product->name);
            }
        });

        static::updating(function (Product $product) {
            if ($product->isDirty('name') && !$product->isDirty('slug')) {
                $product->slug = static::generateUniqueSlug($product->name, $product->id);
            }
        });
    }

    /**
     * Generate a unique slug.
     */
    public static function generateUniqueSlug(string $name, ?int $excludeId = null): string
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;

        $query = static::withTrashed()->where('slug', $slug);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        while ($query->exists()) {
            $slug = $originalSlug . '-' . $counter++;
            $query = static::withTrashed()->where('slug', $slug);
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
        }

        return $slug;
    }

    /**
     * Category relationship.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Tax class relationship.
     */
    public function taxClass(): BelongsTo
    {
        return $this->belongsTo(TaxClass::class);
    }

    /**
     * Product images relationship.
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    /**
     * Get primary image.
     */
    public function primaryImage()
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    /**
     * Stock levels relationship.
     */
    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    /**
     * Stock movements relationship.
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Get total stock across all warehouses.
     */
    public function getTotalStockAttribute(): int
    {
        return $this->stockLevels()->sum('quantity');
    }

    /**
     * Get available stock (total - reserved).
     */
    public function getAvailableStockAttribute(): int
    {
        return $this->stockLevels()->sum('quantity') - $this->stockLevels()->sum('reserved_quantity');
    }

    /**
     * Check if product is low on stock.
     */
    public function getIsLowStockAttribute(): bool
    {
        return $this->stock_tracked && $this->total_stock <= $this->min_stock_level;
    }

    /**
     * Get stock at specific warehouse.
     */
    public function getStockAtWarehouse(int $warehouseId): int
    {
        $stockLevel = $this->stockLevels()->where('warehouse_id', $warehouseId)->first();
        return $stockLevel ? $stockLevel->quantity : 0;
    }

    /**
     * Get price with tax.
     */
    public function getPriceWithTaxAttribute(): float
    {
        if ($this->taxClass) {
            return $this->taxClass->getPriceWithTax($this->price);
        }
        return (float) $this->price;
    }

    /**
     * Get tax amount.
     */
    public function getTaxAmountAttribute(): float
    {
        if ($this->taxClass) {
            return $this->taxClass->calculateTax($this->price);
        }
        return 0.0;
    }

    /**
     * Get profit margin.
     */
    public function getProfitMarginAttribute(): float
    {
        if ($this->cost_price > 0) {
            return round((($this->price - $this->cost_price) / $this->cost_price) * 100, 2);
        }
        return 0.0;
    }

    /**
     * Scope for active products.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for products in a category.
     */
    public function scopeInCategory($query, int $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Scope for low stock products.
     */
    public function scopeLowStock($query)
    {
        return $query->where('stock_tracked', true)
            ->whereHas('stockLevels', function ($q) {
                $q->selectRaw('product_id, SUM(quantity) as total')
                    ->groupBy('product_id')
                    ->havingRaw('SUM(quantity) <= products.min_stock_level');
            });
    }

    /**
     * Scope for searching products.
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('sku', 'like', "%{$search}%")
                ->orWhere('barcode', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        });
    }

    /**
     * Scope for finding by barcode.
     */
    public function scopeByBarcode($query, string $barcode)
    {
        return $query->where('barcode', $barcode);
    }
}
