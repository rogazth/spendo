<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class InsightsService
{
    /**
     * The user's own cycle periods, oldest first, ending with the one that
     * contains $now. Driven by budget_cycle_start_day, never by calendar months:
     * with a cut-off on the 28th, July 31 belongs to the cycle that opened Jul 28.
     *
     * @return list<array{start: CarbonImmutable, end: CarbonImmutable}>
     */
    public function cyclePeriods(User $user, CarbonImmutable $now, int $count): array
    {
        $day = $this->cycleStartDay($user);
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
     * The cycles that close inside $year, oldest first, stopping at the one in
     * progress. A cycle belongs to the month it ends in, so with a cut-off on
     * the 28th "enero" runs Dec 28 – Jan 27.
     *
     * @return list<array{start: CarbonImmutable, end: CarbonImmutable}>
     */
    public function yearPeriods(User $user, int $year, CarbonImmutable $now): array
    {
        $day = $this->cycleStartDay($user);
        [$start, $end] = User::resolveMonthlyCycleForDay(
            CarbonImmutable::create($year, 1, 1)->startOfDay(),
            $day,
        );

        $periods = [];

        while ($end->year === $year && $start->lessThanOrEqualTo($now)) {
            $periods[] = ['start' => $start, 'end' => $end];
            [$start, $end] = User::resolveMonthlyCycleForDay($end->addDay(), $day);
        }

        return $periods;
    }

    /**
     * Years with something to show, newest first. The current one is always
     * offered, even on an empty account.
     *
     * @return list<int>
     */
    public function availableYears(User $user, CarbonImmutable $now): array
    {
        $earliest = $user->transactions()->min('transaction_date');
        $firstYear = $earliest === null
            ? $now->year
            : CarbonImmutable::parse($earliest)->year;

        return collect(range($now->year, min($firstYear, $now->year)))
            ->values()
            ->all();
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
     * Income, expense and net for a single date range, keyed by currency.
     *
     * @return array<string, array{income: float, expense: float, net: float}>
     */
    public function totalsFor(User $user, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $series = $this->cashFlowByPeriod($user, [['start' => $start, 'end' => $end]]);

        return collect($series)
            ->map(fn (array $points) => [
                'income' => $points[0]['income'],
                'expense' => $points[0]['expense'],
                'net' => $points[0]['net'],
            ])
            ->all();
    }

    /**
     * Expense per category for a date range, keyed by currency: root categories
     * ranked by amount, each with its subcategories. Spending excluded from
     * budgets still counts — this answers where the money went, not whether a
     * cap held. When a comparison range is given, every row carries its delta.
     *
     * @return array<string, array{total: float, rows: list<array<string, mixed>>}>
     */
    public function categoryBreakdown(
        User $user,
        CarbonImmutable $start,
        CarbonImmutable $end,
        ?CarbonImmutable $compareStart = null,
        ?CarbonImmutable $compareEnd = null,
    ): array {
        $current = $this->expenseByCategory($user, $start, $end);
        $previous = $compareStart !== null && $compareEnd !== null
            ? $this->expenseByCategory($user, $compareStart, $compareEnd)
            : [];

        $categories = $this->categoriesFor($current, $previous);

        $breakdown = [];
        foreach ($current as $currency => $byCategory) {
            $rows = $this->groupByRoot($byCategory, $categories);
            $previousRows = $this->groupByRoot($previous[$currency] ?? [], $categories);
            $total = array_sum(array_column($rows, 'amount'));

            $breakdown[$currency] = [
                'total' => round($total, 2),
                'rows' => collect($rows)
                    ->sortByDesc('amount')
                    ->map(fn (array $row, string|int $key) => [
                        'key' => (string) $key,
                        'id' => $row['id'],
                        'name' => $row['name'],
                        'emoji' => $row['emoji'],
                        'amount' => round($row['amount'], 2),
                        'count' => $row['count'],
                        'share' => $total > 0 ? round($row['amount'] / $total * 100, 1) : 0.0,
                        'delta' => $compareStart === null
                            ? null
                            : round($row['amount'] - ($previousRows[$key]['amount'] ?? 0), 2),
                        'children' => $this->childRows(
                            $row,
                            $previousRows[$key]['children'] ?? [],
                            $compareStart !== null,
                        ),
                    ])
                    ->values()
                    ->all(),
            ];
        }

        return $breakdown;
    }

    /**
     * Subcategory rows for a root, ranked by amount. A root that was also
     * charged directly only shows that slice when there is something to
     * compare it against.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, array{amount: float, count: int}>  $previousChildren
     * @return list<array<string, mixed>>
     */
    private function childRows(array $row, array $previousChildren, bool $withDelta): array
    {
        $children = $row['children'];

        if (count($children) === 1 && array_key_first($children) === 'direct') {
            return [];
        }

        return collect($children)
            ->sortByDesc('amount')
            ->map(fn (array $child, string|int $key) => [
                'key' => (string) $key,
                'name' => $child['name'],
                'emoji' => $child['emoji'],
                'amount' => round($child['amount'], 2),
                'count' => $child['count'],
                'share' => $row['amount'] > 0
                    ? round($child['amount'] / $row['amount'] * 100, 1)
                    : 0.0,
                'delta' => $withDelta
                    ? round($child['amount'] - ($previousChildren[$key]['amount'] ?? 0), 2)
                    : null,
            ])
            ->values()
            ->all();
    }

    /**
     * Expense totals per currency and category id ('none' for uncategorised).
     *
     * @return array<string, array<string, array{amount: float, count: int}>>
     */
    private function expenseByCategory(User $user, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $transactions = $user->transactions()
            ->where('type', TransactionType::Regular)
            ->where('amount', '<', 0)
            ->withinDateRange($start, $end)
            ->get(['category_id', 'currency', 'amount']);

        $totals = [];
        foreach ($transactions as $transaction) {
            $key = (string) ($transaction->category_id ?? 'none');
            $currency = $transaction->currency;

            $totals[$currency][$key]['amount'] = ($totals[$currency][$key]['amount'] ?? 0) + abs($transaction->amount);
            $totals[$currency][$key]['count'] = ($totals[$currency][$key]['count'] ?? 0) + 1;
        }

        return $totals;
    }

    /**
     * Every category touched by either range, plus their parents. Trashed ones
     * are included so old transactions keep their name.
     *
     * @param  array<string, array<string, array{amount: float, count: int}>>  $current
     * @param  array<string, array<string, array{amount: float, count: int}>>  $previous
     * @return Collection<int, Category>
     */
    private function categoriesFor(array $current, array $previous): Collection
    {
        $ids = collect([$current, $previous])
            ->flatMap(fn (array $byCurrency) => collect($byCurrency)->flatMap(fn (array $rows) => array_keys($rows)))
            ->filter(fn (string|int $key) => $key !== 'none')
            ->map(fn (string|int $key) => (int) $key)
            ->unique();

        $categories = Category::withTrashed()->whereIn('id', $ids)->get();

        $missingParents = $categories
            ->pluck('parent_id')
            ->filter()
            ->reject(fn (int $id) => $categories->contains('id', $id))
            ->unique();

        if ($missingParents->isNotEmpty()) {
            $categories = $categories->concat(
                Category::withTrashed()->whereIn('id', $missingParents)->get(),
            );
        }

        // keyBy last: merging two id-keyed collections would renumber the keys.
        return $categories->keyBy('id');
    }

    /**
     * Fold per-category totals into their root category, keeping each
     * subcategory as a child. Spending charged straight to a root lands in a
     * 'direct' child so the parts always add up to the whole.
     *
     * @param  array<string, array{amount: float, count: int}>  $byCategory
     * @param  Collection<int, Category>  $categories
     * @return array<string, array<string, mixed>>
     */
    private function groupByRoot(array $byCategory, Collection $categories): array
    {
        $rows = [];

        foreach ($byCategory as $key => $totals) {
            $category = $key === 'none' ? null : $categories->get((int) $key);
            $root = $category?->parent_id !== null
                ? $categories->get($category->parent_id) ?? $category
                : $category;

            $rootKey = $root === null ? 'none' : (string) $root->id;
            $childKey = $category === null || $root === null || $category->id === $root->id
                ? 'direct'
                : (string) $category->id;

            $rows[$rootKey]['id'] ??= $root?->id;
            $rows[$rootKey]['name'] ??= $root?->name ?? 'Sin categoría';
            $rows[$rootKey]['emoji'] ??= $root?->emoji ?? '💸';
            $rows[$rootKey]['amount'] = ($rows[$rootKey]['amount'] ?? 0) + $totals['amount'];
            $rows[$rootKey]['count'] = ($rows[$rootKey]['count'] ?? 0) + $totals['count'];

            $rows[$rootKey]['children'][$childKey]['name'] ??= $childKey === 'direct'
                ? 'Sin subcategoría'
                : $category->name;
            $rows[$rootKey]['children'][$childKey]['emoji'] ??= $childKey === 'direct'
                ? ($root?->emoji ?? '💸')
                : ($category->emoji ?? '💸');
            $rows[$rootKey]['children'][$childKey]['amount'] = ($rows[$rootKey]['children'][$childKey]['amount'] ?? 0) + $totals['amount'];
            $rows[$rootKey]['children'][$childKey]['count'] = ($rows[$rootKey]['children'][$childKey]['count'] ?? 0) + $totals['count'];
        }

        return $rows;
    }

    /**
     * The biggest single expenses of a range, per currency.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function largestExpenses(User $user, CarbonImmutable $start, CarbonImmutable $end, int $limit = 5): array
    {
        return $user->transactions()
            ->where('type', TransactionType::Regular)
            ->where('amount', '<', 0)
            ->withinDateRange($start, $end)
            ->with('category:id,name,emoji')
            ->orderBy('amount')
            ->get()
            ->groupBy('currency')
            ->map(fn (Collection $transactions) => $transactions
                ->take($limit)
                ->map(fn (Transaction $transaction) => [
                    'uuid' => $transaction->uuid,
                    'description' => $transaction->description,
                    'category' => $transaction->category?->name ?? 'Sin categoría',
                    'emoji' => $transaction->category?->emoji ?? '💸',
                    'amount' => round(abs($transaction->amount), 2),
                    'date' => $transaction->transaction_date->toDateString(),
                ])
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

    private function cycleStartDay(User $user): int
    {
        return (int) ($user->settings?->budget_cycle_start_day ?? 1);
    }
}
