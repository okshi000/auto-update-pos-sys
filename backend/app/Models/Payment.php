<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'sale_id',
        'payment_method_id',
        'amount',
        'tendered',
        'change',
        'reference',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:3',
        'tendered' => 'decimal:3',
        'change' => 'decimal:3',
    ];

    /**
     * Sale relationship.
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * Payment method relationship.
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    /**
     * Check if this is a cash payment.
     */
    public function getIsCashAttribute(): bool
    {
        return $this->paymentMethod?->type === PaymentMethod::TYPE_CASH;
    }

    /**
     * Calculate change for cash payments.
     */
    public function calculateChange(): float
    {
        if ($this->is_cash && $this->tendered) {
            $this->change = max(0, $this->tendered - $this->amount);
            return (float) $this->change;
        }
        return 0;
    }

    /**
     * Scope to completed payments.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope to refunded payments.
     */
    public function scopeRefunded($query)
    {
        return $query->where('status', self::STATUS_REFUNDED);
    }
}
