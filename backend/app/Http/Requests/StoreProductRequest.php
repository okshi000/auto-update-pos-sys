<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
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
        // Laravel's ConvertEmptyStringsToNull middleware already converts empty strings to null
        // But we need to ensure it happens for all nullable fields
        $data = $this->all();
        
        foreach (['slug', 'sku', 'barcode', 'description', 'category_id', 'tax_class_id'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }
        
        $this->replace($data);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('products', 'slug')->whereNull('deleted_at'),
            ],
            'sku' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('products', 'sku')->whereNull('deleted_at'),
            ],
            'barcode' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('products', 'barcode')->whereNull('deleted_at'),
            ],
            'description' => 'nullable|string|max:5000',
            'category_id' => 'nullable|exists:categories,id',
            'tax_class_id' => 'nullable|exists:tax_classes,id',
            'cost_price' => 'numeric|min:0',
            'price' => 'required|numeric|min:0',
            'stock_tracked' => 'boolean',
            'min_stock_level' => 'integer|min:0',
            'is_active' => 'boolean',
        ];
    }
}
