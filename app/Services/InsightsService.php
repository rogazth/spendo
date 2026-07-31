<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Budget;
use App\Models\BudgetCycle;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class InsightsService
{
    public function __construct(private readonly BudgetCycleService $budgetCycles) {}

    /**
     * Income, expense and net per month for the last $months months, keyed by
     * currency. Aggregated in PHP so the query stays portable across drivers.
     *
     * @return array<string, list<array{month: string, income: float, expense: float, net: float}>>
     */
    public function monthlyCashFlow(User $user, CarbonImmutable $now, int $months = 12): array
    {
        $firstMonth = $now->startOfMonth()->subMonths($months - 1);

        $transactions = $user->transactions()
            ->where('type', TransactionType::Regular)
            ->whereDate('transaction_date', '>=', $firstMonth->toDateString())
            ->whereDate('transaction_date', '<=', $now->endOfMonth()->toDateString())
            ->get(['currency', 'amount', 'transaction_date']);

        $buckets = [];
        foreach ($transactions as $transaction) {
            $month = $transaction->transaction_date->format('Y-m');
            $currency = $transaction->currency;
            $buckets[$currency][$month]['income'] ??= 0.0;
            $buckets[$currency][$month]['expense'] ??= 0.0;

            if ($transaction->amount >= 0) {
                $buckets[$currency][$month]['income'] += $transaction->amount;
            } else {
                $buckets[$currency][$month]['expense'] += abs($transaction->amount);
            }
        }

        $series = [];
        foreach ($buckets as $currency => $months_) {
            $cursor = $firstMonth;
            $rows = [];

            while ($cursor->lessThanOrEqualTo($now)) {
                $key = $cursor->format('Y-m');
                $income = round($months_[$key]['income'] ?? 0, 2);
                $expense = round($months_[$key]['expense'] ?? 0, 2);

                $rows[] = [
                    'month' => $key,
                    'income' => $income,
                    'expense' => $expense,
                    'net' => round($income - $expense, 2),
                ];

                $cursor = $cursor->addMonth();
            }

            $series[$currency] = $rows;
        }

        return $series;
    }

    /**
     * One row per budget with the outcome of each closed cycle, oldest first.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function complianceGrid(User $user, CarbonImmutable $now, int $cycles = 12): array
    {
        $grid = [];

        foreach ($user->budgets()->with('items.category.children')->get() as $budget) {
            $rows = $this->budgetCycles->closedCycleRanges($budget, $now, $cycles)
                ->map(function (array $range) use ($budget, $now) {
                    $cycle = $this->budgetCycles->ensureMaterialized($budget, $range['start'], $now);

                    if ($cycle === null) {
                        return null;
                    }

                    $budgeted = (float) $cycle->total_budgeted;
                    $spent = $this->budgetCycles->spentFor($cycle);

                    return [
                        'start' => $range['start']->toDateString(),
                        'end' => $range['end']->toDateString(),
                        'budgeted' => $budgeted,
                        'spent' => $spent,
                        'status' => $this->statusFor($budgeted, $spent),
                        'percentage' => $budgeted > 0 ? round(($spent / $budgeted) * 100, 1) : 0,
                    ];
                })
                ->filter()
                ->reverse()
                ->values()
                ->all();

            if ($rows === []) {
                continue;
            }

            $grid[$budget->currency][] = [
                'id' => $budget->id,
                'uuid' => $budget->uuid,
                'name' => $budget->name,
                'emoji' => $budget->emoji ?? '💰',
                'cycles' => $rows,
                'kept' => collect($rows)->where('status', '!=', 'over')->count(),
                'total' => count($rows),
            ];
        }

        return $grid;
    }

    /**
     * Expense per category over the last $months months, ranked by how much the
     * latest month deviates from the earlier average.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function categoryTrends(User $user, CarbonImmutable $now, int $months = 6, int $limit = 6): array
    {
        $firstMonth = $now->startOfMonth()->subMonths($months - 1);

        $transactions = $user->transactions()
            ->where('type', TransactionType::Regular)
            ->where('amount', '<', 0)
            ->whereDate('transaction_date', '>=', $firstMonth->toDateString())
            ->with('category')
            ->get(['category_id', 'currency', 'amount', 'transaction_date']);

        $monthKeys = [];
        $cursor = $firstMonth;
        while ($cursor->lessThanOrEqualTo($now)) {
            $monthKeys[] = $cursor->format('Y-m');
            $cursor = $cursor->addMonth();
        }

        $buckets = [];
        foreach ($transactions as $transaction) {
            $category = $transaction->category;

            if ($category === null) {
                continue;
            }

            $key = $transaction->currency.'|'.$category->id;
            $buckets[$key]['currency'] = $transaction->currency;
            $buckets[$key]['name'] = $category->name;
            $buckets[$key]['emoji'] = $category->emoji ?? '💰';
            $month = $transaction->transaction_date->format('Y-m');
            $buckets[$key]['months'][$month] = ($buckets[$key]['months'][$month] ?? 0) + abs($transaction->amount);
        }

        $trends = [];
        foreach ($buckets as $bucket) {
            $points = array_map(
                fn (string $month) => round($bucket['months'][$month] ?? 0, 2),
                $monthKeys,
            );
            $latest = end($points);
            $earlier = array_slice($points, 0, -1);
            $baseline = $earlier === [] ? 0.0 : array_sum($earlier) / count($earlier);

            $trends[$bucket['currency']][] = [
                'name' => $bucket['name'],
                'emoji' => $bucket['emoji'],
                'points' => $points,
                'latest' => $latest,
                'baseline' => round($baseline, 2),
                'delta' => round($latest - $baseline, 2),
                'total' => round(array_sum($points), 2),
            ];
        }

        return collect($trends)
            ->map(fn (array $rows) => collect($rows)
                ->sortByDesc(fn (array $row) => $row['delta'])
                ->take($limit)
                ->values()
                ->all())
            ->all();
    }

    /**
     * Per budget item, the average spend across closed cycles next to the cap in
     * force today, so out-of-touch caps surface.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function capSuggestions(User $user, CarbonImmutable $now, int $minCycles = 2): array
    {
        $suggestions = [];

        $cycles = BudgetCycle::query()
            ->where('user_id', $user->id)
            ->with('items')
            ->orderByDesc('starts_at')
            ->get()
            ->groupBy('budget_id');

        $budgets = Budget::query()
            ->whereIn('id', $cycles->keys())
            ->with('items.category')
            ->get()
            ->keyBy('id');

        foreach ($cycles as $budgetId => $budgetCycles) {
            $budget = $budgets->get($budgetId);

            if ($budget === null || $budgetCycles->count() < $minCycles) {
                continue;
            }

            $spentByCategory = $this->spentByCategoryAcross($budgetCycles);

            foreach ($budget->items as $item) {
                $samples = $spentByCategory[$item->category_id] ?? [];

                if (count($samples) < $minCycles) {
                    continue;
                }

                $average = array_sum($samples) / count($samples);
                $cap = (float) $item->amount;
                $gap = $average - $cap;

                if ($cap <= 0 || abs($gap) < $cap * 0.15) {
                    continue;
                }

                $suggestions[$budget->currency][] = [
                    'budget_name' => $budget->name,
                    'budget_uuid' => $budget->uuid,
                    'category_name' => $item->category?->name ?? 'Sin categoría',
                    'category_emoji' => $item->category?->emoji ?? '💰',
                    'cap' => $cap,
                    'average' => round($average, 2),
                    'gap' => round($gap, 2),
                    'cycles' => count($samples),
                ];
            }
        }

        return collect($suggestions)
            ->map(fn (array $rows) => collect($rows)
                ->sortByDesc(fn (array $row) => abs($row['gap']))
                ->values()
                ->all())
            ->all();
    }

    /**
     * @param  Collection<int, BudgetCycle>  $cycles
     * @return array<int, list<float>>
     */
    private function spentByCategoryAcross(Collection $cycles): array
    {
        $samples = [];

        foreach ($cycles as $cycle) {
            $transactions = $this->budgetCycles->transactionsQuery($cycle)
                ->get(['category_id', 'amount']);

            $spentByCategory = [];
            foreach ($transactions as $transaction) {
                $spentByCategory[$transaction->category_id] =
                    ($spentByCategory[$transaction->category_id] ?? 0) + abs($transaction->amount);
            }

            foreach ($cycle->items as $item) {
                $spent = collect($item->category_ids)
                    ->sum(fn ($categoryId) => $spentByCategory[(int) $categoryId] ?? 0);
                $samples[$item->category_id][] = (float) $spent;
            }
        }

        return $samples;
    }

    private function statusFor(float $budgeted, float $spent): string
    {
        if ($budgeted <= 0) {
            return 'empty';
        }

        $ratio = $spent / $budgeted;

        if ($ratio > 1) {
            return 'over';
        }

        if ($ratio >= 0.9) {
            return 'tight';
        }

        return 'kept';
    }
}
