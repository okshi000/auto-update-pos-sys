<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_method_id' => $this->payment_method_id,
            'payment_method' => $this->whenLoaded('paymentMethod', function () {
                return [
                    'id' => $this->paymentMethod->id,
                    'name' => $this->paymentMethod->name,
                    'code' => $this->paymentMethod->code,
                    'type' => $this->paymentMethod->type,
                ];
            }),
            'amount' => (float) $this->amount,
            'tendered' => $this->tendered ? (float) $this->tendered : null,
            'change' => $this->change ? (float) $this->change : null,
            'reference' => $this->reference,
            'status' => $this->status,
            'is_cash' => $this->is_cash,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
