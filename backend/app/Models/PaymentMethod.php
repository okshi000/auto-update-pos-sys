<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMethod extends Model
{
    use HasFactory;

    public const TYPE_CASH = 'cash';
    public const TYPE_CARD = 'card';
    public const TYPE_DIGITAL = 'digital';
    public const TYPE_OTHER = 'other';

    protected $fillable = [
        'name',
        'code',
        'type',
        'description',
        'is_active',
        'requires_reference',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'requires_reference' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get all types.
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_CASH,
            self::TYPE_CARD,
            self::TYPE_DIGITAL,
            self::TYPE_OTHER,
        ];
    }

    /**
     * Payments relationship.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get the default payment method (cash).
     */
    public static function getDefault(): ?self
    {
        return static::where('code', 'cash')->first();
    }

    /**
     * Scope to active payment methods.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to order by sort order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
