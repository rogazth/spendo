<?php

namespace App\Actions\Budgets;

use App\Models\Budget;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateBudgetAction
{
    public function handle(Budget $budget, User $user, array $data): Budget
    {
        return DB::transaction(function () use ($budget, $data) {
            $budget->update(array_filter([
                'account_id' => $data['account_id'] ?? null,
                'name' => $data['name'] ?? null,
                'color' => $data['color'] ?? null,
                'description' => $data['description'] ?? null,
                'currency' => $data['currency'] ?? null,
                'frequency' => $data['frequency'] ?? null,
                'anchor_date' => $data['anchor_date'] ?? null,
                'ends_at' => $data['ends_at'] ?? null,
                'is_active' => $data['is_active'] ?? null,
            ], fn ($value) => $value !== null));

            // Emoji is nullable and clearable, so persist it whenever the key is
            // present rather than dropping nulls like the other fields above.
            if (array_key_exists('emoji', $data)) {
                $budget->update(['emoji' => $data['emoji']]);
            }

            if (array_key_exists('items', $data)) {
                $budget->items()->delete();

                foreach ($data['items'] as $item) {
                    $budget->items()->create([
                        'category_id' => $item['category_id'],
                        'amount' => $item['amount'],
                    ]);
                }
            }

            return $budget;
        });
    }
}
