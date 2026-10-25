<?php

namespace App\Http\Requests\Order;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /** "paid" no es asignable manualmente: solo lo establece el webhook firmado de Stripe. */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(OrderStatus::adminAssignable())],
        ];
    }

    public function attributes(): array
    {
        return ['status' => 'estado'];
    }

    public function status(): OrderStatus
    {
        return OrderStatus::from($this->validated('status'));
    }
}
