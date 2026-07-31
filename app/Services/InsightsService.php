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
     * The user's own cycle periods, oldest first, ending with the one that
     * contains $now. Driven by budget_cycle_start_day, never by calendar months:
     * with a cut-off on the 28th, July 31 belongs to the cycle that opened Jul 28.
     *
     * @return list<array{start: CarbonImmutable, end: CarbonImmutable}>
     */
    public function cyclePeriods(User $user, CarbonImmutable $now, int $count): array
    {
        $day = (int) ($user->settings?->budget_cycle_start_day ?? 1);
        $periods = [];
        $cursor = $now;

        for ($i = 0; $i < $count; $i++) {
            [$start, $end] = User::resolveMonthlyCycleForDay($cursor, $day);
            array_unshift($periods, ['start' => $start, 'end' => $end]);
            $cursor = $start->subDay();
        }

        return $periods;
    }

    /**
     * Income, expense and net per cycle period, keyed by currency. Aggregated in
     * PHP so the query stays portable across drivers.
     *
     * @param  list<array{start: CarbonImmutable, end: CarbonImmutable}>  $periods
     * @return array<string, list<array{start: string, end: string, income: float, expense: float, net: float}>>
     */
    public function cashFlowByPeriod(User $user, array $periods): array
    {
        if ($periods === []) {
            return [];
        }

        $firstStart = $periods[0]['start'];
        $lastEnd = $periods[count($periods) - 1]['end'];

        $transactions = $user->transactions()
            ->where('type', TransactionType::Regular)
            ->whereDate('transaction_date', '>=', $firstStart->toDateString())
            ->whereDate('transaction_date', '<=', $lastEnd->toDateString())
            ->get(['currency', 'amount', 'transaction_date']);

        $buckets = [];
        foreach ($transactions as $transaction) {
            $key = $this->periodKeyFor($periods, $transaction->transaction_date->toDateString());

            if ($key === null) {
                continue;
            }

            $currency = $transaction->currency;
            $buckets[$currency][$key]['income'] ??= 0.0;
            $buckets[$currency][$key]['expense'] ??= 0.0;

            if ($transaction->amount >= 0) {
                $buckets[$currency][$key]['income'] += $transaction->amount;
            } else {
                $buckets[$currency][$key]['expense'] += abs($transaction->amount);
            }
        }

        $series = [];
        foreach ($buckets as $currency => $periodTotals) {
            $series[$currency] = array_map(function (array $period) use ($periodTotals) {
                $key = $period['start']->toDateString();
                $income = round($periodTotals[$key]['income'] ?? 0, 2);
                $expense = round($periodTotals[$key]['expense'] ?? 0, 2);

                return [
                    'start' => $period['start']->toDateString(),
                    'end' => $period['end']->toDateString(),
                    'income' => $income,
                    'expense' => $expense,
                    'net' => round($income - $expense, 2),
                ];
            }, $periods);
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
     * Expense per category across cycle periods, ranked by how far the last
     * *closed* period sits from the average of the ones before it. The period in
     * progress is excluded: three days into a cycle is not a trend.
     *
     * @param  list<array{start: CarbonImmutable, end: CarbonImmutable}>  $periods
     * @return array<string, list<array<string, mixed>>>
     */
    public function categoryTrends(User $user, CarbonImmutable $now, array $periods, int $limit = 6): array
    {
        $closedPeriods = array_values(array_filter(
            $periods,
            fn (array $period) => $period['end']->lessThan($now),
        ));

        if (count($closedPeriods) < 2) {
            return [];
        }

        $firstStart = $closedPeriods[0]['start'];
        $lastEnd = $closedPeriods[count($closedPeriods) - 1]['end'];

        $transactions = $user->transactions()
            ->where('type', TransactionType::Regular)
            ->where('amount', '<', 0)
            ->whereDate('transaction_date', '>=', $firstStart->toDateString())
            ->whereDate('transaction_date', '<=', $lastEnd->toDateString())
            ->with('category')
            ->get(['category_id', 'currency', 'amount', 'transaction_date']);

        $buckets = [];
        foreach ($transactions as $transaction) {
            $category = $transaction->category;
            $periodKey = $this->periodKeyFor($closedPeriods, $transaction->transaction_date->toDateString());

            if ($category === null || $periodKey === null) {
                continue;
            }

            $key = $transaction->currency.'|'.$category->id;
            $buckets[$key]['currency'] = $transaction->currency;
            $buckets[$key]['name'] = $category->name;
            $buckets[$key]['emoji'] = $category->emoji ?? '💰';
            $buckets[$key]['periods'][$periodKey] =
                ($buckets[$key]['periods'][$periodKey] ?? 0) + abs($transaction->amount);
        }

        $trends = [];
        foreach ($buckets as $bucket) {
            $points = array_map(
                fn (array $period) => round($bucket['periods'][$period['start']->toDateString()] ?? 0, 2),
                $closedPeriods,
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
                'period_start' => $closedPeriods[count($closedPeriods) - 1]['start']->toDateString(),
                'period_end' => $lastEnd->toDateString(),
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
     * The start date of the period containing $date, used as its bucket key.
     *
     * @param  list<array{start: CarbonImmutable, end: CarbonImmutable}>  $periods
     */
    private function periodKeyFor(array $periods, string $date): ?string
    {
        foreach ($periods as $period) {
            if ($date >= $period['start']->toDateString() && $date <= $period['end']->toDateString()) {
                return $period['start']->toDateString();
            }
        }

        return null;
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
