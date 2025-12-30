<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use HasFactory, SoftDeletes;

    // Status constants
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SENT,
        self::STATUS_PARTIAL,
        self::STATUS_RECEIVED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'po_number',
        'supplier_id',
        'warehouse_id',
        'created_by',
        'received_by',
        'status',
        'subtotal',
        'tax_total',
        'discount_amount',
        'shipping_cost',
        'total',
        'order_date',
        'expected_date',
        'received_at',
        'reference',
        'notes',
    ];

    protected $casts = [
        'subtotal' => 'decimal:3',
        'tax_total' => 'decimal:3',
        'discount_amount' => 'decimal:3',
        'shipping_cost' => 'decimal:3',
        'total' => 'decimal:3',
        'order_date' => 'date',
        'expected_date' => 'date',
        'received_at' => 'datetime',
    ];

    /**
     * Supplier relationship.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Warehouse relationship.
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Creator relationship.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Receiver relationship.
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * Items relationship.
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /**
     * Generate a unique PO number.
     */
    public static function generatePoNumber(): string
    {
        $prefix = 'PO';
        $date = now()->format('Ymd');
        
        $lastPo = static::withTrashed()
            ->where('po_number', 'like', "{$prefix}-{$date}-%")
            ->orderByRaw("CAST(SUBSTRING(po_number, -4) AS UNSIGNED) DESC")
            ->first();

        if ($lastPo) {
            $lastNumber = (int) substr($lastPo->po_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return sprintf('%s-%s-%04d', $prefix, $date, $newNumber);
    }

    /**
     * Calculate totals from items.
     */
    public function calculateTotals(): void
    {
        $this->subtotal = $this->items->sum('line_total');
        $this->total = $this->subtotal - $this->discount_amount + $this->shipping_cost;
    }

    /**
     * Check if PO can be edited.
     */
    public function canEdit(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT]);
    }

    /**
     * Check if PO can be received.
     */
    public function canReceive(): bool
    {
        return in_array($this->status, [self::STATUS_SENT, self::STATUS_PARTIAL]);
    }

    /**
     * Check if PO can be cancelled.
     */
    public function canCancel(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_SENT]);
    }

    /**
     * Check if PO is fully received.
     */
    public function isFullyReceived(): bool
    {
        foreach ($this->items as $item) {
            if ($item->quantity_received < $item->quantity_ordered) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check if PO is partially received.
     */
    public function isPartiallyReceived(): bool
    {
        $hasReceived = $this->items->where('quantity_received', '>', 0)->count() > 0;
        return $hasReceived && !$this->isFullyReceived();
    }

    /**
     * Scope for pending POs (not received or cancelled).
     */
    public function scopePending($query)
    {
        return $query->whereIn('status', [self::STATUS_DRAFT, self::STATUS_SENT, self::STATUS_PARTIAL]);
    }

    /**
     * Get the can_edit attribute.
     */
    public function getCanEditAttribute(): bool
    {
        return $this->canEdit();
    }

    /**
     * Get the can_receive attribute.
     */
    public function getCanReceiveAttribute(): bool
    {
        return $this->canReceive();
    }

    /**
     * Get the can_cancel attribute.
     */
    public function getCanCancelAttribute(): bool
    {
        return $this->canCancel();
    }
}
