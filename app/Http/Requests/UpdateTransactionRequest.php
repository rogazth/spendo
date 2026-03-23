<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'type' => ['required', 'string', 'in:expense,income'],
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer'],
            'amount' => ['required', 'numeric'],
            'description' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'exclude_from_budget' => ['nullable', 'boolean'],
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
            'account_id.exists' => 'La cuenta seleccionada no existe.',
            'category_id.exists' => 'La categoria seleccionada no existe.',
            'amount.required' => 'El monto es requerido.',
            'amount.numeric' => 'El monto debe ser un numero.',
            'description.max' => 'La descripcion no puede exceder 255 caracteres.',
            'exclude_from_budget.boolean' => 'El indicador de exclusión del budget no es válido.',
            'transaction_date.required' => 'La fecha es requerida.',
            'transaction_date.date' => 'La fecha no es valida.',
            'attachments.array' => 'Los adjuntos no son válidos.',
            'attachments.max' => 'Puedes subir hasta 5 archivos.',
            'attachments.*.file' => 'Cada adjunto debe ser un archivo válido.',
            'attachments.*.max' => 'Cada adjunto no puede superar 5 MB.',
        ];
    }
}
