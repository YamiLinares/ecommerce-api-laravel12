<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => is_string($this->email) ? strtolower(trim($this->email)) : $this->email,
            'name' => is_string($this->name) ? trim($this->name) : $this->name,
        ]);
    }

    /**
     * Solo se aceptan estos campos; "role" u otros campos enviados se ignoran.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120', "regex:/^[\pL\pM\s'.-]+$/u"],
            'email' => ['required', 'string', 'email:rfc,strict', 'max:191', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9\s-]{7,20}$/'],
            'password' => ['required', 'string', 'confirmed', 'max:72', Password::min(8)->letters()->mixedCase()->numbers()],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'email' => 'correo electrónico',
            'phone' => 'teléfono',
            'password' => 'contraseña',
        ];
    }
}
