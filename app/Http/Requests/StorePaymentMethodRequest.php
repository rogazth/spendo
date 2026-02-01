<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethodType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::enum(PaymentMethodType::class)],
            'linked_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'currency' => ['nullable', 'string', 'size:3'],
            'credit_limit' => ['nullable', 'integer', 'min:0'],
            'billing_cycle_day' => ['nullable', 'integer', 'min:1', 'max:28'],
            'payment_due_day' => ['nullable', 'integer', 'min:1', 'max:28'],
            'color' => ['nullable', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icon' => ['nullable', 'string', 'max:50'],
            'last_four_digits' => ['nullable', 'string', 'size:4'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es requerido.',
            'name.max' => 'El nombre no puede exceder 255 caracteres.',
            'type.required' => 'El tipo es requerido.',
            'type.enum' => 'El tipo no es válido.',
            'linked_account_id.exists' => 'La cuenta seleccionada no existe.',
            'currency.size' => 'La moneda debe tener 3 caracteres.',
            'credit_limit.integer' => 'El límite de crédito debe ser un número entero.',
            'credit_limit.min' => 'El límite de crédito debe ser mayor a 0.',
            'billing_cycle_day.integer' => 'El día de corte debe ser un número entero.',
            'billing_cycle_day.min' => 'El día de corte debe ser al menos 1.',
            'billing_cycle_day.max' => 'El día de corte no puede ser mayor a 28.',
            'payment_due_day.integer' => 'El día de pago debe ser un número entero.',
            'payment_due_day.min' => 'El día de pago debe ser al menos 1.',
            'payment_due_day.max' => 'El día de pago no puede ser mayor a 28.',
            'color.regex' => 'El color debe ser un código hexadecimal válido (ej: #FF5733).',
            'last_four_digits.size' => 'Los últimos 4 dígitos deben tener exactamente 4 caracteres.',
        ];
    }
}
