<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'description' => $this->description,
            'category_id' => $this->category_id,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'tax_class_id' => $this->tax_class_id,
            'tax_class' => new TaxClassResource($this->whenLoaded('taxClass')),
            'cost_price' => (float) $this->cost_price,
            'price' => (float) $this->price,
            'price_with_tax' => $this->price_with_tax,
            'tax_amount' => $this->tax_amount,
            'profit_margin' => $this->profit_margin,
            'stock_tracked' => $this->stock_tracked,
            'min_stock_level' => $this->min_stock_level,
            'total_stock' => $this->when($this->relationLoaded('stockLevels'), fn() => $this->total_stock),
            'available_stock' => $this->when($this->relationLoaded('stockLevels'), fn() => $this->available_stock),
            'is_low_stock' => $this->when($this->relationLoaded('stockLevels'), fn() => $this->is_low_stock),
            'is_active' => $this->is_active,
            'primary_image' => new ProductImageResource($this->whenLoaded('primaryImage')),
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
            'stock_levels' => StockLevelResource::collection($this->whenLoaded('stockLevels')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
