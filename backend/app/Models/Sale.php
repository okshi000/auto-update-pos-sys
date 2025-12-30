<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_PARTIALLY_REFUNDED = 'partially_refunded';

    public const DISCOUNT_TYPE_FIXED = 'fixed';
    public const DISCOUNT_TYPE_PERCENTAGE = 'percentage';

    protected $fillable = [
        'invoice_number',
        'client_uuid',
        'idempotency_key',
        'user_id',
        'warehouse_id',
        'subtotal',
        'discount_amount',
        'discount_type',
        'total',
        'status',
        'is_synced',
        'has_stock_conflict',
        'notes',
        'completed_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:3',
        'discount_amount' => 'decimal:3',
        'total' => 'decimal:3',
        'is_synced' => 'boolean',
        'has_stock_conflict' => 'boolean',
        'completed_at' => 'datetime',
    ];

    /**
     * User (cashier) relationship.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Warehouse relationship.
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Sale items relationship.
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * Payments relationship.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Generate a unique invoice number.
     */
    public static function generateInvoiceNumber(): string
    {
        $prefix = 'INV';
        $date = now()->format('Ymd');
        
        // Get last invoice number for today
        $lastInvoice = static::withTrashed()
            ->where('invoice_number', 'like', "{$prefix}-{$date}-%")
            ->orderByRaw("CAST(SUBSTRING(invoice_number, -4) AS UNSIGNED) DESC")
            ->first();

        if ($lastInvoice) {
            $lastNumber = (int) substr($lastInvoice->invoice_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return sprintf('%s-%s-%04d', $prefix, $date, $newNumber);
    }

    /**
     * Check if sale exists by idempotency key.
     */
    public static function existsByIdempotencyKey(string $key): bool
    {
        return static::withTrashed()->where('idempotency_key', $key)->exists();
    }

    /**
     * Find sale by idempotency key.
     */
    public static function findByIdempotencyKey(string $key): ?self
    {
        return static::withTrashed()->where('idempotency_key', $key)->first();
    }

    /**
     * Calculate and update totals from items.
     */
    public function recalculateTotals(): void
    {
        $this->subtotal = $this->items->sum(function ($item) {
            return $item->unit_price * $item->quantity;
        });

        $this->tax_total = $this->items->sum('tax_amount');
        
        // Apply discount
        if ($this->discount_type === self::DISCOUNT_TYPE_PERCENTAGE && $this->discount_amount > 0) {
            $discountValue = ($this->subtotal * $this->discount_amount) / 100;
        } else {
            $discountValue = $this->discount_amount ?? 0;
        }

        $this->total = $this->subtotal + $this->tax_total - $discountValue;
        $this->save();
    }

    /**
     * Get total paid amount.
     */
    public function getTotalPaidAttribute(): float
    {
        return (float) $this->payments()->where('status', 'completed')->sum('amount');
    }

    /**
     * Get remaining balance.
     */
    public function getBalanceDueAttribute(): float
    {
        return (float) $this->total - $this->total_paid;
    }

    /**
     * Check if fully paid.
     */
    public function getIsFullyPaidAttribute(): bool
    {
        return $this->balance_due <= 0;
    }

    /**
     * Check if can be refunded.
     */
    public function getCanRefundAttribute(): bool
    {
        return $this->status === self::STATUS_COMPLETED && $this->is_fully_paid;
    }

    /**
     * Scope to completed sales.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope to unsynced offline sales.
     */
    public function scopeUnsynced($query)
    {
        return $query->where('is_synced', false);
    }

    /**
     * Scope to sales with stock conflicts.
     */
    public function scopeWithConflicts($query)
    {
        return $query->where('has_stock_conflict', true);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope to filter by user.
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}
