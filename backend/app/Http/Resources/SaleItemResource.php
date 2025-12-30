<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'product_sku' => $this->product_sku,
            'quantity' => $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'cost_price' => (float) $this->cost_price,
            'discount' => $this->discount_amount > 0 ? (($this->discount_amount / ($this->unit_price * $this->quantity)) * 100) : 0, // Calculate discount percentage
            'discount_amount' => (float) $this->discount_amount,
            'line_total' => (float) $this->line_total,
            'subtotal' => (float) $this->line_total, // Alias for frontend compatibility
            'total' => (float) $this->line_total, // Alias for frontend compatibility
            'gross_margin' => $this->when($request->user()?->can('reports.view'), $this->gross_margin),
            'product' => new ProductResource($this->whenLoaded('product')),
        ];
    }
}
