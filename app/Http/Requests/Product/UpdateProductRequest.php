<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('product')) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->sku)) {
            $this->merge(['sku' => strtoupper(trim($this->sku))]);
        }
    }

    /** Actualización parcial: solo se validan los campos enviados. */
    public function rules(): array
    {
        return [
            'sku' => [
                'sometimes', 'required', 'string', 'max:64', 'regex:/^[A-Z0-9-]+$/',
                Rule::unique('products', 'sku')->ignore($this->route('product')),
            ],
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'price' => ['sometimes', 'required', 'decimal:0,2', 'min:0.50', 'max:99999999.99'],
            'stock' => ['sometimes', 'required', 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return (new StoreProductRequest)->attributes();
    }
}
