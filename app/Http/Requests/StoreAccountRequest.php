<?php

namespace App\Http\Requests;

use App\Enums\AccountType;
use App\Models\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreAccountRequest extends FormRequest
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
        $userId = Auth::id();

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('accounts', 'name')->where(fn ($query) => $query
                    ->where('user_id', $userId)
                    ->whereNull('deleted_at')),
            ],
            'type' => ['required', 'string', Rule::enum(AccountType::class)],
            'currency' => ['required', 'string', 'size:3', Rule::in(Currency::codes())],
            'initial_balance' => ['nullable', 'numeric', 'min:0'],
            'color' => ['nullable', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icon' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
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
            'name.unique' => 'Ya existe una cuenta con este nombre.',
            'type.required' => 'El tipo de cuenta es requerido.',
            'type.enum' => 'El tipo de cuenta no es valido.',
            'currency.required' => 'La moneda es requerida.',
            'currency.size' => 'La moneda debe tener 3 caracteres.',
            'initial_balance.numeric' => 'El balance inicial debe ser un numero.',
            'initial_balance.min' => 'El balance inicial no puede ser negativo.',
        ];
    }
}
