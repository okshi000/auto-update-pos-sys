<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePosSaleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => 'required|string|max:100',
            'client_uuid' => 'nullable|string|max:100',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            
            'discount_amount' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:fixed,percentage',
            
            'payments' => 'nullable|array',
            'payments.*.payment_method_id' => 'required|exists:payment_methods,id',
            'payments.*.amount' => 'required|numeric|min:0',
            'payments.*.reference' => 'nullable|string|max:255',

            'payment' => 'nullable|array',
            'payment.payment_method_id' => 'nullable|exists:payment_methods,id',
            'payment.amount' => 'nullable|numeric|min:0',
            'payment.tendered' => 'nullable|numeric|min:0',
            'payment.reference' => 'nullable|string|max:255',
            
            'notes' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'idempotency_key.required' => 'A unique transaction key is required to prevent duplicate sales.',
            'items.required' => 'At least one item is required for a sale.',
            'items.*.product_id.exists' => 'One or more products do not exist.',
            'items.*.quantity.min' => 'Quantity must be at least 1.',
        ];
    }
}
