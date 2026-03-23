<?php

namespace App\Actions\Budgets;

use App\Models\Budget;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateBudgetAction
{
    public function handle(User $user, array $data): Budget
    {
        return DB::transaction(function () use ($user, $data) {
            $budget = $user->budgets()->create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'currency' => $data['currency'],
                'frequency' => $data['frequency'],
                'anchor_date' => $data['anchor_date'],
                'ends_at' => $data['ends_at'] ?? null,
                'is_active' => true,
            ]);

            foreach ($data['items'] ?? [] as $item) {
                $budget->items()->create([
                    'category_id' => $item['category_id'],
                    'amount' => $item['amount'],
                ]);
            }

            return $budget;
        });
    }
}
