<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert empty strings to null for nullable fields
        if ($this->has('slug') && $this->slug === '') {
            $this->merge(['slug' => null]);
        }
        if ($this->has('sku') && $this->sku === '') {
            $this->merge(['sku' => null]);
        }
        if ($this->has('barcode') && $this->barcode === '') {
            $this->merge(['barcode' => null]);
        }
        if ($this->has('description') && $this->description === '') {
            $this->merge(['description' => null]);
        }
        if ($this->has('category_id') && $this->category_id === '') {
            $this->merge(['category_id' => null]);
        }
        if ($this->has('tax_class_id') && $this->tax_class_id === '') {
            $this->merge(['tax_class_id' => null]);
        }
    }

    public function rules(): array
    {
        $productId = $this->route('product')->id ?? $this->route('product');

        return [
            'name' => 'sometimes|required|string|max:255',
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('products', 'slug')->ignore($productId),
            ],
            'sku' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('products', 'sku')->ignore($productId),
            ],
            'barcode' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('products', 'barcode')->ignore($productId),
            ],
            'description' => 'nullable|string|max:5000',
            'category_id' => 'nullable|exists:categories,id',
            'tax_class_id' => 'nullable|exists:tax_classes,id',
            'cost_price' => 'numeric|min:0',
            'price' => 'sometimes|required|numeric|min:0',
            'stock_tracked' => 'boolean',
            'min_stock_level' => 'integer|min:0',
            'is_active' => 'boolean',
        ];
    }
}
