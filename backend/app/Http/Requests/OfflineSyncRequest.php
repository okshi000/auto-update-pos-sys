<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OfflineSyncRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'client_uuid' => 'required|string|max:100',
            'sales' => 'required|array|min:1',
            
            'sales.*.idempotency_key' => 'required|string|max:100',
            'sales.*.user_id' => 'nullable|exists:users,id',
            'sales.*.warehouse_id' => 'nullable|exists:warehouses,id',
            'sales.*.created_at' => 'nullable|date',
            
            'sales.*.items' => 'required|array|min:1',
            'sales.*.items.*.product_id' => 'required|exists:products,id',
            'sales.*.items.*.quantity' => 'required|integer|min:1',
            'sales.*.items.*.discount_amount' => 'nullable|numeric|min:0',
            
            'sales.*.discount_amount' => 'nullable|numeric|min:0',
            'sales.*.discount_type' => 'nullable|in:fixed,percentage',
            
            'sales.*.payment' => 'nullable|array',
            'sales.*.payment.payment_method_id' => 'nullable|exists:payment_methods,id',
            'sales.*.payment.amount' => 'nullable|numeric|min:0',
            'sales.*.payment.tendered' => 'nullable|numeric|min:0',
            
            'sales.*.notes' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'client_uuid.required' => 'Client UUID is required for sync identification.',
            'sales.required' => 'At least one sale is required for sync.',
            'sales.*.idempotency_key.required' => 'Each sale must have a unique idempotency key.',
            'sales.*.items.required' => 'Each sale must have at least one item.',
        ];
    }
}
