<?php

namespace App\Http\Requests\Settings;

use App\Models\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserSettingsUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'default_currency' => ['required', 'string', 'size:3', Rule::in(Currency::codes())],
            'budget_cycle_start_day' => ['required', 'integer', 'min:1', 'max:28'],
            'timezone' => ['required', 'string', 'timezone:all'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'default_currency.required' => 'La moneda es requerida.',
            'default_currency.in' => 'La moneda seleccionada no es válida.',
            'budget_cycle_start_day.required' => 'El día de inicio del ciclo es requerido.',
            'budget_cycle_start_day.min' => 'El día debe ser al menos 1.',
            'budget_cycle_start_day.max' => 'El día no puede ser mayor a 28.',
            'timezone.required' => 'La zona horaria es requerida.',
            'timezone.timezone' => 'La zona horaria no es válida.',
        ];
    }
}
