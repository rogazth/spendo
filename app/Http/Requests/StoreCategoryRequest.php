<?php

namespace App\Http\Requests;

use App\Enums\CategoryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
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
        $user = Auth::user();

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'name')->where(function ($query) use ($user) {
                    $query->where(function ($q) use ($user) {
                        $q->whereNull('user_id')
                            ->orWhere('user_id', $user->id);
                    })
                        ->whereNull('deleted_at');
                }),
            ],
            'type' => [
                'required_without:parent_id',
                'nullable',
                'string',
                Rule::enum(CategoryType::class),
                Rule::notIn([CategoryType::System->value]),
            ],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where(function ($query) use ($user) {
                    $query->where(function ($q) use ($user) {
                        $q->whereNull('user_id')
                            ->orWhere('user_id', $user->id);
                    })
                        ->whereNull('parent_id')
                        ->where('is_system', false);
                }),
            ],
            'icon' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
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
            'name.unique' => 'Ya existe una categoría con este nombre.',
            'type.required_without' => 'El tipo es requerido cuando no se selecciona una categoría padre.',
            'type.enum' => 'El tipo de categoría no es válido.',
            'type.not_in' => 'No puedes crear categorías de sistema.',
            'parent_id.exists' => 'La categoría padre seleccionada no es válida.',
            'color.regex' => 'El color debe ser un código hexadecimal válido (ej: #FF5733).',
            'sort_order.integer' => 'El orden debe ser un número entero.',
            'sort_order.min' => 'El orden debe ser un número positivo.',
        ];
    }
}
