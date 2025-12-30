<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'receipt_number' => $this->invoice_number, // Alias for frontend compatibility
            'client_uuid' => $this->client_uuid,
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                ];
            }),
            'warehouse_id' => $this->warehouse_id,
            'warehouse' => $this->whenLoaded('warehouse', function () {
                return [
                    'id' => $this->warehouse->id,
                    'name' => $this->warehouse->name,
                    'code' => $this->warehouse->code,
                ];
            }),
            'customer_id' => null, // Not implemented yet
            'customer' => null, // Not implemented yet
            'subtotal' => (float) $this->subtotal,
            'discount_amount' => (float) $this->discount_amount,
            'discount_type' => $this->discount_type,
            'discount_percent' => $this->discount_type === 'percentage' ? (float) $this->discount_amount : null,
            'total' => (float) $this->total,
            'total_amount' => (float) $this->total, // Alias for frontend compatibility
            'grand_total' => (float) $this->total, // Alias for frontend compatibility
            'status' => $this->status,
            'is_synced' => $this->is_synced,
            'has_stock_conflict' => $this->has_stock_conflict,
            'notes' => $this->notes,
            'total_paid' => $this->total_paid,
            'balance_due' => $this->balance_due,
            'is_fully_paid' => $this->is_fully_paid,
            'can_refund' => $this->can_refund,
            'items_count' => $this->when(isset($this->items_count), $this->items_count),
            'items' => SaleItemResource::collection($this->whenLoaded('items')),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'completed_at' => $this->completed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Currency info
            'currency' => 'LYD',
            'currency_decimals' => 3,
        ];
    }
}
