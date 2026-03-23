<?php

namespace App\Http\Requests;

use App\Models\Category;
use App\Models\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBudgetRequest extends FormRequest
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
            'description' => ['nullable', 'string', 'max:2000'],
            'currency' => ['required', 'string', 'size:3', Rule::in(Currency::codes())],
            'frequency' => ['required', 'string', Rule::in(['weekly', 'biweekly', 'monthly', 'bimonthly'])],
            'anchor_date' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:anchor_date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.category_id' => ['required', 'integer', 'exists:categories,id', 'distinct'],
            'items.*.amount' => ['required', 'numeric', 'gt:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del budget es requerido.',
            'currency.required' => 'La moneda del budget es requerida.',
            'frequency.required' => 'La frecuencia del budget es requerida.',
            'frequency.in' => 'La frecuencia seleccionada no es válida.',
            'anchor_date.required' => 'La fecha de anclaje es requerida.',
            'ends_at.after_or_equal' => 'La fecha de finalización debe ser posterior o igual a la fecha de anclaje.',
            'items.required' => 'Debes agregar al menos una categoría al budget.',
            'items.min' => 'Debes agregar al menos una categoría al budget.',
            'items.*.category_id.required' => 'La categoría es requerida.',
            'items.*.category_id.distinct' => 'No puedes repetir categorías dentro del mismo budget.',
            'items.*.amount.required' => 'El monto máximo por categoría es requerido.',
            'items.*.amount.gt' => 'El monto máximo por categoría debe ser mayor a cero.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $user = Auth::user();
            if (! $user) {
                return;
            }

            $items = $this->input('items', []);
            if (! is_array($items) || $items === []) {
                return;
            }

            $selectedCategoryIds = collect($items)
                ->pluck('category_id')
                ->filter(fn ($value) => $value !== null && $value !== '')
                ->map(fn ($value) => (int) $value)
                ->unique()
                ->values();

            if ($selectedCategoryIds->isEmpty()) {
                return;
            }

            $categories = Category::query()
                ->whereIn('id', $selectedCategoryIds->all())
                ->where(function ($query) use ($user) {
                    $query->whereNull('user_id')
                        ->orWhere('user_id', $user->id);
                })
                ->where('type', 'expense')
                ->get(['id', 'parent_id']);

            if ($categories->count() !== $selectedCategoryIds->count()) {
                $validator->errors()->add('items', 'Algunas categorías no son válidas para este budget.');

                return;
            }

            $categoryIdsSet = $selectedCategoryIds->flip();
            foreach ($categories as $category) {
                if (
                    $category->parent_id !== null &&
                    $categoryIdsSet->has((int) $category->parent_id)
                ) {
                    $validator->errors()->add(
                        'items',
                        'No puedes mezclar una categoría padre con una de sus subcategorías en el mismo budget.'
                    );
                    break;
                }
            }
        });
    }
}
