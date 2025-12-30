<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'location' => $this->location,
            'address' => $this->address,
            'phone' => $this->phone,
            'is_active' => $this->is_active,
            'is_default' => $this->is_default,
            'product_count' => $this->when($request->has('include_stats'), fn() => $this->product_count),
            'total_stock_value' => $this->when($request->has('include_stats'), fn() => $this->total_stock_value),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
