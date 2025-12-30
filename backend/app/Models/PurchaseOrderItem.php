<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'quantity_ordered',
        'quantity_received',
        'quantity_returned',
        'unit_cost',
        'discount_amount',
        'line_total',
        'notes',
    ];

    protected $casts = [
        'quantity_ordered' => 'integer',
        'quantity_received' => 'integer',
        'quantity_returned' => 'integer',
        'unit_cost' => 'decimal:3',
        'discount_amount' => 'decimal:3',
        'line_total' => 'decimal:3',
    ];

    /**
     * Purchase order relationship.
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
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
    public function calculateLineTotal(): void
    {
        $subtotal = $this->quantity_ordered * $this->unit_cost;
        $this->line_total = $subtotal - $this->discount_amount;
    }

    /**
     * Create from product with quantity and cost.
     */
    public static function createFromProduct(Product $product, int $quantity, float $unitCost): self
    {
        $item = new self();
        $item->product_id = $product->id;
        $item->quantity_ordered = $quantity;
        $item->quantity_received = 0;
        $item->quantity_returned = 0;
        $item->unit_cost = $unitCost;
        $item->discount_amount = 0;
        $item->calculateLineTotal();
        
        return $item;
    }

    /**
     * Get remaining quantity to receive.
     */
    public function getRemainingQuantityAttribute(): int
    {
        return max(0, $this->quantity_ordered - $this->quantity_received);
    }

    /**
     * Get net received quantity (received minus returned).
     */
    public function getNetReceivedQuantityAttribute(): int
    {
        return $this->quantity_received - $this->quantity_returned;
    }
}
