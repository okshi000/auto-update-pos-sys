<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'image_path',
        'alt_text',
        'is_primary',
        'sort_order',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (ProductImage $image) {
            // Ensure only one primary image per product
            if ($image->is_primary) {
                static::where('product_id', $image->product_id)
                    ->where('id', '!=', $image->id)
                    ->update(['is_primary' => false]);
            }
        });

        static::deleting(function (ProductImage $image) {
            // Delete the file when the record is deleted
            if (Storage::disk('public')->exists($image->image_path)) {
                Storage::disk('public')->delete($image->image_path);
            }
        });
    }

    /**
     * Product relationship.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get full URL for the image.
     */
    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->image_path);
    }
}
