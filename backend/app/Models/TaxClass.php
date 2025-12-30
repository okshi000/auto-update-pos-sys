<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxClass extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'rate',
        'description',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (TaxClass $taxClass) {
            // Ensure only one default tax class
            if ($taxClass->is_default) {
                static::where('id', '!=', $taxClass->id)->update(['is_default' => false]);
            }
        });
    }

    /**
     * Products with this tax class.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get the default tax class.
     */
    public static function getDefault(): ?self
    {
        return static::where('is_default', true)->first();
    }

    /**
     * Calculate tax amount for a given price.
     */
    public function calculateTax(float $price): float
    {
        return round($price * ($this->rate / 100), 2);
    }

    /**
     * Get price including tax.
     */
    public function getPriceWithTax(float $price): float
    {
        return round($price + $this->calculateTax($price), 2);
    }

    /**
     * Scope for active tax classes.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
