<?php

namespace App\Actions\Budgets;

use App\Models\Budget;

class DeleteBudgetAction
{
    public function handle(Budget $budget): void
    {
        $budget->delete();
    }
}
