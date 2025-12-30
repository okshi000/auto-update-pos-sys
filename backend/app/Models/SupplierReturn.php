<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierReturn extends Model
{
    use HasFactory, SoftDeletes;

    // Status constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_SHIPPED,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    // Reason constants
    public const REASON_DAMAGED = 'damaged';
    public const REASON_DEFECTIVE = 'defective';
    public const REASON_WRONG_ITEM = 'wrong_item';
    public const REASON_EXCESS = 'excess';
    public const REASON_OTHER = 'other';

    public const REASONS = [
        self::REASON_DAMAGED,
        self::REASON_DEFECTIVE,
        self::REASON_WRONG_ITEM,
        self::REASON_EXCESS,
        self::REASON_OTHER,
    ];

    protected $fillable = [
        'return_number',
        'supplier_id',
        'purchase_order_id',
        'warehouse_id',
        'created_by',
        'status',
        'total',
        'reason',
        'notes',
        'shipped_at',
        'completed_at',
    ];

    protected $casts = [
        'total' => 'decimal:3',
        'shipped_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Supplier relationship.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Purchase order relationship.
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
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
     * Items relationship.
     */
    public function items(): HasMany
    {
        return $this->hasMany(SupplierReturnItem::class);
    }

    /**
     * Generate a unique return number.
     */
    public static function generateReturnNumber(): string
    {
        $prefix = 'RET';
        $date = now()->format('Ymd');
        
        $lastReturn = static::withTrashed()
            ->where('return_number', 'like', "{$prefix}-{$date}-%")
            ->orderByRaw("CAST(SUBSTRING(return_number, -4) AS UNSIGNED) DESC")
            ->first();

        if ($lastReturn) {
            $lastNumber = (int) substr($lastReturn->return_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return sprintf('%s-%s-%04d', $prefix, $date, $newNumber);
    }

    /**
     * Calculate total from items.
     */
    public function calculateTotal(): void
    {
        $this->total = $this->items->sum('line_total');
    }

    /**
     * Check if return can be edited.
     */
    public function canEdit(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING]);
    }

    /**
     * Check if return can be cancelled.
     */
    public function canCancel(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_APPROVED]);
    }

    /**
     * Get the can_edit attribute.
     */
    public function getCanEditAttribute(): bool
    {
        return $this->canEdit();
    }

    /**
     * Get the can_cancel attribute.
     */
    public function getCanCancelAttribute(): bool
    {
        return $this->canCancel();
    }
}
