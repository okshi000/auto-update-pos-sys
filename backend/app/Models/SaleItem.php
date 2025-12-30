<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'product_id',
        'product_name',
        'product_sku',
        'quantity',
        'unit_price',
        'cost_price',
        'discount_amount',
        'line_total',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:3',
        'cost_price' => 'decimal:3',
        'discount_amount' => 'decimal:3',
        'line_total' => 'decimal:3',
    ];

    /**
     * Sale relationship.
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * Product relationship.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Calculate line total.
     */
    public function calculateLineTotal(): float
    {
        $subtotal = $this->unit_price * $this->quantity;
        $this->line_total = $subtotal - $this->discount_amount;
        
        return (float) $this->line_total;
    }

    /**
     * Get gross margin for this item.
     */
    public function getGrossMarginAttribute(): float
    {
        $revenue = $this->unit_price * $this->quantity;
        $cost = $this->cost_price * $this->quantity;
        return (float) ($revenue - $cost);
    }

    /**
     * Get gross margin percentage.
     */
    public function getGrossMarginPercentAttribute(): float
    {
        $revenue = $this->unit_price * $this->quantity;
        if ($revenue == 0) {
            return 0;
        }
        return ($this->gross_margin / $revenue) * 100;
    }

    /**
     * Create from product with current prices.
     */
    public static function createFromProduct(Product $product, int $quantity): self
    {
        $item = new self();
        $item->product_id = $product->id;
        $item->product_name = $product->name;
        $item->product_sku = $product->sku;
        $item->quantity = $quantity;
        $item->unit_price = $product->price;
        $item->cost_price = $product->cost_price ?? 0;
        $item->discount_amount = 0;
        $item->calculateLineTotal();
        
        return $item;
    }
}
