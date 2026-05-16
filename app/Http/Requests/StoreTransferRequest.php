<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransferRequest extends FormRequest
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
            'origin_account_id' => ['required', 'integer', 'exists:accounts,id', 'different:destination_account_id'],
            'destination_account_id' => ['required', 'integer', 'exists:accounts,id', 'different:origin_account_id'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
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
            'origin_account_id.required' => 'La cuenta de origen es requerida.',
            'origin_account_id.exists' => 'La cuenta de origen seleccionada no existe.',
            'origin_account_id.different' => 'La cuenta de origen debe ser distinta a la de destino.',
            'destination_account_id.required' => 'La cuenta de destino es requerida.',
            'destination_account_id.exists' => 'La cuenta de destino seleccionada no existe.',
            'destination_account_id.different' => 'La cuenta de destino debe ser distinta a la de origen.',
            'amount.required' => 'El monto es requerido.',
            'amount.numeric' => 'El monto debe ser un numero.',
            'amount.gt' => 'El monto debe ser mayor a cero.',
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
