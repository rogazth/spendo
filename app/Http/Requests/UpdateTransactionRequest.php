<?php

namespace App\Http\Requests;

use App\Models\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTransactionRequest extends FormRequest
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
            'type' => ['required', 'string', 'in:expense,income,transfer'],
            'account_id' => ['nullable', 'required_if:type,expense,income', 'integer', 'exists:accounts,id'],
            'origin_account_id' => ['nullable', 'required_if:type,transfer', 'integer', 'exists:accounts,id', 'different:destination_account_id'],
            'destination_account_id' => ['nullable', 'required_if:type,transfer', 'integer', 'exists:accounts,id', 'different:origin_account_id'],
            'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id', 'required_if:type,expense'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'amount' => ['required', 'numeric'],
            'currency' => ['required', 'string', 'size:3', Rule::in(Currency::codes())],
            'description' => ['nullable', 'string', 'max:255'],
            'transaction_date' => ['required', 'date'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'account_id.required' => 'La cuenta es requerida.',
            'account_id.exists' => 'La cuenta seleccionada no existe.',
            'origin_account_id.required_if' => 'La cuenta de origen es requerida.',
            'origin_account_id.exists' => 'La cuenta de origen seleccionada no existe.',
            'origin_account_id.different' => 'La cuenta de origen debe ser distinta a la de destino.',
            'destination_account_id.required_if' => 'La cuenta de destino es requerida.',
            'destination_account_id.exists' => 'La cuenta de destino seleccionada no existe.',
            'destination_account_id.different' => 'La cuenta de destino debe ser distinta a la de origen.',
            'payment_method_id.required_if' => 'El metodo de pago es requerido para gastos.',
            'payment_method_id.exists' => 'El metodo de pago seleccionado no existe.',
            'category_id.exists' => 'La categoria seleccionada no existe.',
            'amount.required' => 'El monto es requerido.',
            'amount.numeric' => 'El monto debe ser un numero.',
            'currency.required' => 'La moneda es requerida.',
            'currency.size' => 'La moneda debe tener 3 caracteres.',
            'description.max' => 'La descripcion no puede exceder 255 caracteres.',
            'transaction_date.required' => 'La fecha es requerida.',
            'transaction_date.date' => 'La fecha no es valida.',
            'attachments.array' => 'Los adjuntos no son válidos.',
            'attachments.max' => 'Puedes subir hasta 5 archivos.',
            'attachments.*.file' => 'Cada adjunto debe ser un archivo válido.',
            'attachments.*.max' => 'Cada adjunto no puede superar 5 MB.',
        ];
    }
}
