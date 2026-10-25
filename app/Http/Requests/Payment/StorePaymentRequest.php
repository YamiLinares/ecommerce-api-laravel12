<?php

namespace App\Http\Requests\Payment;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * El importe nunca se acepta del cliente: se calcula a partir de la orden.
 * Opcionalmente puede enviarse un PaymentMethod de Stripe (p. ej. "pm_card_visa"
 * en modo de pruebas) para confirmar el pago desde el servidor sin frontend.
 */
class StorePaymentRequest extends FormRequest
{
    /** Devuelve 404 si la orden pertenece a otro usuario (no revela su existencia). */
    public function authorize(): Response
    {
        return Gate::inspect('pay', $this->route('order'));
    }

    public function rules(): array
    {
        return [
            'payment_method' => ['nullable', 'string', 'max:100', 'regex:/^pm_[A-Za-z0-9_]+$/'],
        ];
    }

    public function attributes(): array
    {
        return ['payment_method' => 'método de pago'];
    }
}
