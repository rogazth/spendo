<?php

namespace App\Http\Requests;

use App\Models\Currency;
use App\Models\User;
use Carbon\CarbonImmutable;
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
     * Monthly budgets follow the user's global cycle start day, so the form does
     * not collect an anchor date. Derive one (the current cycle start) when absent
     * to satisfy the not-null column and keep the budget within the active range.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('frequency') === 'monthly' && ! $this->filled('anchor_date')) {
            $day = (int) (Auth::user()?->settings?->budget_cycle_start_day ?? 1);
            [$cycleStart] = User::resolveMonthlyCycleForDay(CarbonImmutable::now()->startOfDay(), $day);

            $this->merge(['anchor_date' => $cycleStart->toDateString()]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'emoji' => ['nullable', 'string', 'max:16'],
            'description' => ['nullable', 'string', 'max:2000'],
            'currency' => ['required', 'string', 'size:3', Rule::in(Currency::codes())],
            'frequency' => ['required', 'string', Rule::in(['weekly', 'biweekly', 'monthly', 'bimonthly'])],
            'anchor_date' => ['required_unless:frequency,monthly', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:anchor_date'],
            'account_ids' => ['required', 'array', 'min:1'],
            'account_ids.*' => ['integer'],
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
            'color.regex' => 'El color debe ser un código hexadecimal válido (ej: #FF5733).',
            'currency.required' => 'La moneda del budget es requerida.',
            'frequency.required' => 'La frecuencia del budget es requerida.',
            'frequency.in' => 'La frecuencia seleccionada no es válida.',
            'anchor_date.required' => 'La fecha de inicio es requerida.',
            'anchor_date.required_unless' => 'La fecha de inicio es requerida.',
            'ends_at.after_or_equal' => 'La fecha de finalización debe ser posterior o igual a la fecha de anclaje.',
            'account_ids.required' => 'Debes asociar al menos una cuenta al budget.',
            'account_ids.min' => 'Debes asociar al menos una cuenta al budget.',
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

            $this->validateAccounts($validator, $user);

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

            $categories = $user->categories()
                ->whereIn('id', $selectedCategoryIds->all())
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

    /**
     * Budgets draw spending from an explicit set of accounts. Every account must
     * belong to the user, share the budget currency, and (for now) belong to at
     * most one budget — enforced here so the pivot can later relax to many.
     */
    private function validateAccounts(Validator $validator, User $user): void
    {
        $accountIds = collect($this->input('account_ids', []))
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values();

        if ($accountIds->isEmpty()) {
            return;
        }

        $accounts = $user->accounts()
            ->whereIn('id', $accountIds->all())
            ->get(['id', 'currency']);

        if ($accounts->count() !== $accountIds->count()) {
            $validator->errors()->add('account_ids', 'Algunas cuentas no son válidas.');

            return;
        }

        $currency = $this->input('currency');
        if ($currency !== null && $accounts->contains(fn ($account) => $account->currency !== $currency)) {
            $validator->errors()->add('account_ids', 'Todas las cuentas deben ser de la misma moneda que el budget.');

            return;
        }

        $currentBudgetId = $this->route('budget')?->id;
        $takenAccountIds = $user->accounts()
            ->whereIn('accounts.id', $accountIds->all())
            ->whereHas('budgets', function ($query) use ($currentBudgetId) {
                if ($currentBudgetId !== null) {
                    $query->where('budgets.id', '!=', $currentBudgetId);
                }
            })
            ->pluck('accounts.id');

        if ($takenAccountIds->isNotEmpty()) {
            $validator->errors()->add('account_ids', 'Una o más cuentas ya pertenecen a otro budget.');
        }
    }
}
