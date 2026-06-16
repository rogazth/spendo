<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class BudgetMetricsService
{
    /**
     * Compute current-cycle metrics for every active budget belonging to $user
     * at $referenceDate.
     *
     * @return Collection<int, array{
     *   budget: Budget,
     *   cycle_start: CarbonImmutable,
     *   cycle_end: CarbonImmutable,
     *   budgeted: float,
     *   spent: float,
     *   reserved: float,
     *   overspend_amount: float,
     *   has_overspend: bool,
     *   daily_spent: list<float>
     * }>
     */
    public function forActiveBudgets(User $user, CarbonImmutable $referenceDate): Collection
    {
        $activeBudgets = Budget::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->whereDate('anchor_date', '<=', $referenceDate->toDateString())
            ->where(function ($q) use ($referenceDate) {
                $q->whereNull('ends_at')->orWhereDate('ends_at', '>=', $referenceDate->toDateString());
            })
            ->with(['items.category.children', 'account'])
            ->get();

        return $activeBudgets->map(fn (Budget $budget) => $this->compute($user, $budget, $referenceDate));
    }

    /**
     * @return array{
     *   budget: Budget,
     *   cycle_start: CarbonImmutable,
     *   cycle_end: CarbonImmutable,
     *   budgeted: float,
     *   spent: float,
     *   reserved: float,
     *   overspend_amount: float,
     *   has_overspend: bool,
     *   daily_spent: list<float>
     * }
     */
    public function forBudget(User $user, Budget $budget, CarbonImmutable $referenceDate): array
    {
        if (! $budget->relationLoaded('items')) {
            $budget->load('items.category.children');
        }

        if (! $budget->relationLoaded('account')) {
            $budget->load('account');
        }

        return $this->compute($user, $budget, $referenceDate);
    }

    /**
     * @return array{
     *   budget: Budget,
     *   cycle_start: CarbonImmutable,
     *   cycle_end: CarbonImmutable,
     *   budgeted: float,
     *   spent: float,
     *   reserved: float,
     *   overspend_amount: float,
     *   has_overspend: bool,
     *   daily_spent: list<float>
     * }
     */
    private function compute(User $user, Budget $budget, CarbonImmutable $referenceDate): array
    {
        [$cycleStart, $cycleEnd] = $budget->resolveCycleRange(
            $referenceDate,
            (int) ($user->settings?->budget_cycle_start_day ?? 1),
        );
        $categoryGroups = $budget->budgetCategoryGroups();

        $spentByCategory = [];
        $spentByDate = [];
        $transactions = $user->transactions()
            ->forBudgetSpending($budget, $cycleStart, $cycleEnd)
            ->get(['category_id', 'amount', 'transaction_date']);

        foreach ($transactions as $transaction) {
            $abs = abs($transaction->amount);
            $spentByCategory[$transaction->category_id] =
                ($spentByCategory[$transaction->category_id] ?? 0) + $abs;
            $dateKey = $transaction->transaction_date->toDateString();
            $spentByDate[$dateKey] = ($spentByDate[$dateKey] ?? 0) + $abs;
        }

        $totalSpent = 0.0;
        $reserved = 0.0;
        $overspend = 0.0;

        foreach ($budget->items as $item) {
            $itemCategoryIds = $categoryGroups[$item->id] ?? [];
            $itemSpent = collect($itemCategoryIds)
                ->sum(fn ($categoryId) => $spentByCategory[$categoryId] ?? 0);

            $totalSpent += $itemSpent;
            $reserved += max(0, $item->amount - $itemSpent);
            $overspend += max(0, $itemSpent - $item->amount);
        }

        // Cumulative daily spend from cycle_start through min(referenceDate, cycle_end).
        // Future-dated transactions (after $endDay) are intentionally excluded so the
        // last sparkline point reflects spend "as of today", not scheduled future activity.
        $endDay = $referenceDate->lt($cycleEnd) ? $referenceDate : $cycleEnd;
        $endDateStr = $endDay->toDateString();
        $dailySpent = [];
        $cumulative = 0.0;
        $cursor = $cycleStart;
        while ($cursor->lte($endDay)) {
            $dateStr = $cursor->toDateString();
            if ($dateStr <= $endDateStr) {
                $cumulative += $spentByDate[$dateStr] ?? 0;
            }
            $dailySpent[] = round($cumulative, 2);
            $cursor = $cursor->addDay();
        }

        return [
            'budget' => $budget,
            'cycle_start' => $cycleStart,
            'cycle_end' => $cycleEnd,
            'budgeted' => (float) $budget->total_budgeted,
            'spent' => (float) $totalSpent,
            'reserved' => (float) $reserved,
            'overspend_amount' => (float) $overspend,
            'has_overspend' => $overspend > 0,
            'daily_spent' => $dailySpent,
        ];
    }
}
