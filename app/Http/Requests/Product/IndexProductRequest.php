<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class IndexProductRequest extends FormRequest
{
    public const SORTABLE = ['name', '-name', 'price', '-price', 'created_at', '-created_at'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'min_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'gte:min_price'],
            'in_stock' => ['nullable', 'boolean'],
            'include_inactive' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'string', 'in:'.implode(',', self::SORTABLE)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function attributes(): array
    {
        return [
            'search' => 'búsqueda',
            'min_price' => 'precio mínimo',
            'max_price' => 'precio máximo',
            'in_stock' => 'en stock',
            'sort' => 'orden',
            'per_page' => 'elementos por página',
        ];
    }
}
