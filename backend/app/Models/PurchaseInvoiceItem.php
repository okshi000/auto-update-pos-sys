<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseInvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_invoice_id',
        'product_id',
        'quantity',
        'unit_cost',
        'tax_rate',
        'tax_amount',
        'discount_amount',
        'line_total',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'decimal:3',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:3',
        'discount_amount' => 'decimal:3',
        'line_total' => 'decimal:3',
    ];

    /**
     * Create item from product.
     */
    public static function createFromProduct(
        Product $product, 
        int $quantity, 
        float $unitCost,
        float $taxRate = 0,
        float $discountAmount = 0
    ): self {
        $item = new self();
        $item->product_id = $product->id;
        $item->quantity = $quantity;
        $item->unit_cost = $unitCost;
        $item->tax_rate = $taxRate;
        $item->discount_amount = $discountAmount;
        $item->calculateLineTotal();
        
        return $item;
    }

    /**
     * Calculate line total including tax and discount.
     */
    public function calculateLineTotal(): void
    {
        $subtotal = $this->quantity * $this->unit_cost;
        $this->tax_amount = $subtotal * ($this->tax_rate / 100);
        $this->line_total = $subtotal - $this->discount_amount + $this->tax_amount;
    }

    // Relationships

    public function purchaseInvoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
