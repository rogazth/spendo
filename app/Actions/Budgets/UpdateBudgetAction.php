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
                'name' => $data['name'] ?? null,
                'description' => $data['description'] ?? null,
                'currency' => $data['currency'] ?? null,
                'frequency' => $data['frequency'] ?? null,
                'anchor_date' => $data['anchor_date'] ?? null,
                'ends_at' => $data['ends_at'] ?? null,
                'is_active' => $data['is_active'] ?? null,
            ], fn ($value) => $value !== null));

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
