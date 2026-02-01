<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
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
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'amount' => ['required', 'integer'],
            'currency' => ['required', 'string', 'size:3'],
            'description' => ['required', 'string', 'max:255'],
            'transaction_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payment_method_id.required' => 'El metodo de pago es requerido.',
            'payment_method_id.exists' => 'El metodo de pago seleccionado no existe.',
            'category_id.exists' => 'La categoria seleccionada no existe.',
            'amount.required' => 'El monto es requerido.',
            'amount.integer' => 'El monto debe ser un numero entero.',
            'currency.required' => 'La moneda es requerida.',
            'currency.size' => 'La moneda debe tener 3 caracteres.',
            'description.required' => 'La descripcion es requerida.',
            'description.max' => 'La descripcion no puede exceder 255 caracteres.',
            'transaction_date.required' => 'La fecha es requerida.',
            'transaction_date.date' => 'La fecha no es valida.',
            'notes.max' => 'Las notas no pueden exceder 1000 caracteres.',
        ];
    }
}
