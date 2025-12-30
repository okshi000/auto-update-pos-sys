<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory;

    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPE_PURCHASE = 'purchase';
    public const TYPE_SALE = 'sale';
    public const TYPE_TRANSFER_IN = 'transfer_in';
    public const TYPE_TRANSFER_OUT = 'transfer_out';
    public const TYPE_RETURN = 'return';
    public const TYPE_SUPPLIER_RETURN = 'supplier_return';
    public const TYPE_DAMAGE = 'damage';
    public const TYPE_CORRECTION = 'correction';

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'quantity_change',
        'quantity_before',
        'quantity_after',
        'type',
        'reason',
        'reference_type',
        'reference_id',
        'user_id',
    ];

    protected $casts = [
        'quantity_change' => 'integer',
        'quantity_before' => 'integer',
        'quantity_after' => 'integer',
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
     * User who made the movement.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the reference model.
     */
    public function reference()
    {
        if ($this->reference_type && $this->reference_id) {
            return $this->morphTo('reference', 'reference_type', 'reference_id');
        }
        return null;
    }

    /**
     * Check if movement is incoming.
     */
    public function getIsIncomingAttribute(): bool
    {
        return $this->quantity_change > 0;
    }

    /**
     * Check if movement is outgoing.
     */
    public function getIsOutgoingAttribute(): bool
    {
        return $this->quantity_change < 0;
    }

    /**
     * Scope by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope by product.
     */
    public function scopeByProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Scope by warehouse.
     */
    public function scopeByWarehouse($query, int $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    /**
     * Scope for incoming movements.
     */
    public function scopeIncoming($query)
    {
        return $query->where('quantity_change', '>', 0);
    }

    /**
     * Scope for outgoing movements.
     */
    public function scopeOutgoing($query)
    {
        return $query->where('quantity_change', '<', 0);
    }

    /**
     * Scope between dates.
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Get all available types.
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_ADJUSTMENT,
            self::TYPE_PURCHASE,
            self::TYPE_SALE,
            self::TYPE_TRANSFER_IN,
            self::TYPE_TRANSFER_OUT,
            self::TYPE_RETURN,
            self::TYPE_SUPPLIER_RETURN,
            self::TYPE_DAMAGE,
            self::TYPE_CORRECTION,
        ];
    }
}
