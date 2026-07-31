<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\BudgetCycle;
use App\Models\BudgetItem;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BudgetCycleService
{
    /**
     * Snapshot every cycle that has already closed and is not stored yet,
     * walking back up to $lookback cycles per budget.
     */
    public function materializeClosedCycles(User $user, CarbonImmutable $now, int $lookback = 1): int
    {
        $budgets = $user->budgets()->with(['items.category.children'])->get();
        $cycleStartDay = $this->cycleStartDay($user);
        $created = 0;

        foreach ($budgets as $budget) {
            [$currentStart] = $budget->resolveCycleRange($now, $cycleStartDay);
            $cursor = $currentStart->subDay();

            for ($i = 0; $i < $lookback; $i++) {
                [$start, $end] = $budget->resolveCycleRange($cursor, $cycleStartDay);

                if ($end->greaterThanOrEqualTo($now)) {
                    break;
                }

                if ($this->snapshot($budget, $start, $end, $now, false) !== null) {
                    $created++;
                }

                $cursor = $start->subDay();

                if ($cursor->lessThan(CarbonImmutable::parse($budget->anchor_date))) {
                    break;
                }
            }
        }

        return $created;
    }

    /**
     * Fetch the stored snapshot for a closed cycle, creating it from today's
     * caps if the cycle predates snapshotting. Returns null while the cycle is
     * still open.
     */
    public function ensureMaterialized(Budget $budget, CarbonImmutable $cycleStart, CarbonImmutable $now): ?BudgetCycle
    {
        [$start, $end] = $budget->resolveCycleRange($cycleStart, $this->cycleStartDay($budget->user));

        if ($end->greaterThanOrEqualTo($now)) {
            return null;
        }

        $existing = BudgetCycle::query()
            ->where('budget_id', $budget->id)
            ->whereDate('starts_at', $start->toDateString())
            ->with('items')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $this->snapshot($budget, $start, $end, $now, true);

        return BudgetCycle::query()
            ->where('budget_id', $budget->id)
            ->whereDate('starts_at', $start->toDateString())
            ->with('items')
            ->first();
    }

    /**
     * Closed cycle ranges for a budget, most recent first, whether or not they
     * have been snapshotted yet.
     *
     * @return Collection<int, array{start: CarbonImmutable, end: CarbonImmutable}>
     */
    public function closedCycleRanges(Budget $budget, CarbonImmutable $now, int $limit = 12): Collection
    {
        $cycleStartDay = $this->cycleStartDay($budget->user);
        $anchor = CarbonImmutable::parse($budget->anchor_date)->startOfDay();
        [$currentStart] = $budget->resolveCycleRange($now, $cycleStartDay);

        $ranges = collect();
        $cursor = $currentStart->subDay();

        while ($ranges->count() < $limit && $cursor->greaterThanOrEqualTo($anchor)) {
            [$start, $end] = $budget->resolveCycleRange($cursor, $cycleStartDay);

            if ($end->greaterThanOrEqualTo($now)) {
                break;
            }

            $ranges->push(['start' => $start, 'end' => $end]);
            $cursor = $start->subDay();
        }

        return $ranges;
    }

    /**
     * Post-mortem metrics for a closed cycle. Spending is recomputed from
     * transactions on every call, so late or edited entries are reflected.
     *
     * @return array{
     *   budgeted: float,
     *   spent: float,
     *   leftover: float,
     *   overspend: float,
     *   over_items: int,
     *   items: list<array<string, mixed>>,
     *   burn: list<array{date: string, spent: float, ideal: float}>,
     *   top_transactions: list<array<string, mixed>>
     * }
     */
    public function review(BudgetCycle $cycle): array
    {
        $cycle->loadMissing('items');

        $transactions = $this->transactionsQuery($cycle)
            ->with('category')
            ->get(['id', 'uuid', 'category_id', 'amount', 'description', 'transaction_date']);

        $spentByCategory = [];
        $spentByDate = [];

        foreach ($transactions as $transaction) {
            $absolute = abs($transaction->amount);
            $spentByCategory[$transaction->category_id] = ($spentByCategory[$transaction->category_id] ?? 0) + $absolute;
            $dateKey = $transaction->transaction_date->toDateString();
            $spentByDate[$dateKey] = ($spentByDate[$dateKey] ?? 0) + $absolute;
        }

        $items = $cycle->items->map(function ($item) use ($spentByCategory) {
            $spent = collect($item->category_ids)
                ->sum(fn ($categoryId) => $spentByCategory[(int) $categoryId] ?? 0);
            $cap = (float) $item->cap_amount;

            return [
                'id' => $item->id,
                'category_id' => $item->category_id,
                'category_name' => $item->category_name,
                'category_color' => $item->category_color ?? '#6B7280',
                'category_emoji' => $item->category_emoji ?? '💰',
                'budgeted' => $cap,
                'spent' => (float) $spent,
                'delta' => (float) ($cap - $spent),
                'percentage' => $cap > 0 ? round(($spent / $cap) * 100, 2) : 0,
            ];
        })->sortByDesc(fn (array $item) => abs($item['delta']))->values()->all();

        $budgeted = (float) collect($items)->sum('budgeted');
        $spent = (float) collect($items)->sum('spent');
        $overspend = (float) collect($items)->sum(fn (array $item) => max(0, -$item['delta']));

        return [
            'budgeted' => $budgeted,
            'spent' => $spent,
            'leftover' => $budgeted - $spent,
            'overspend' => $overspend,
            'over_items' => collect($items)->filter(fn (array $item) => $item['delta'] < 0)->count(),
            'items' => $items,
            'burn' => $this->buildBurnCurve($cycle, $spentByDate, $budgeted),
            'top_transactions' => $transactions
                ->sortByDesc(fn (Transaction $transaction) => abs($transaction->amount))
                ->take(5)
                ->map(fn (Transaction $transaction) => [
                    'id' => $transaction->id,
                    'uuid' => $transaction->uuid,
                    'description' => $transaction->description,
                    'amount' => abs($transaction->amount),
                    'category_name' => $transaction->category?->name ?? 'Sin categoría',
                    'category_emoji' => $transaction->category?->emoji ?? '💰',
                    'transaction_date' => $transaction->transaction_date->toDateString(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Total spent in a closed cycle, without the per-item breakdown.
     */
    public function spentFor(BudgetCycle $cycle): float
    {
        $total = (int) ($this->transactionsQuery($cycle)
            ->selectRaw('COALESCE(SUM(-amount), 0) as total')
            ->value('total') ?? 0);

        return $total / 100;
    }

    /**
     * Budget-eligible transactions for a closed cycle, scoped by the snapshot
     * rather than by the budget's current configuration.
     */
    public function transactionsQuery(BudgetCycle $cycle): Builder
    {
        $categoryIds = $cycle->trackedCategoryIds();

        $query = Transaction::query()
            ->where('user_id', $cycle->user_id)
            ->budgetEligible()
            ->where('currency', $cycle->currency)
            ->withinDateRange($cycle->starts_at, $cycle->ends_at);

        if ($categoryIds === []) {
            return $query->whereRaw('1 = 0');
        }

        $query->whereIn('category_id', $categoryIds);

        if ($cycle->account_id !== null) {
            $query->where('account_id', $cycle->account_id);
        }

        return $query;
    }

    private function snapshot(
        Budget $budget,
        CarbonImmutable $start,
        CarbonImmutable $end,
        CarbonImmutable $now,
        bool $isBackfilled,
    ): ?BudgetCycle {
        $alreadyStored = BudgetCycle::query()
            ->where('budget_id', $budget->id)
            ->whereDate('starts_at', $start->toDateString())
            ->exists();

        if ($alreadyStored) {
            return null;
        }

        $budget->loadMissing('items.category.children');
        $categoryGroups = $budget->budgetCategoryGroups();

        return DB::transaction(function () use ($budget, $start, $end, $now, $isBackfilled, $categoryGroups) {
            $cycle = BudgetCycle::create([
                'budget_id' => $budget->id,
                'user_id' => $budget->user_id,
                'account_id' => $budget->account_id,
                'currency' => $budget->currency,
                'budget_name' => $budget->name,
                'starts_at' => $start->toDateString(),
                'ends_at' => $end->toDateString(),
                'closed_at' => $now,
                'is_backfilled' => $isBackfilled,
            ]);

            foreach ($budget->items as $item) {
                $cycle->items()->create([
                    'category_id' => $item->category_id,
                    'category_name' => $item->category?->name ?? 'Sin categoría',
                    'category_color' => $item->category?->color,
                    'category_emoji' => $item->category?->emoji,
                    'category_ids' => array_values($categoryGroups[$item->id] ?? []),
                    'cap_amount' => $this->capOf($item),
                ]);
            }

            return $cycle;
        });
    }

    /**
     * @param  array<string, float>  $spentByDate
     * @return list<array{date: string, spent: float, ideal: float}>
     */
    private function buildBurnCurve(BudgetCycle $cycle, array $spentByDate, float $budgeted): array
    {
        $start = CarbonImmutable::parse($cycle->starts_at);
        $end = CarbonImmutable::parse($cycle->ends_at);
        $days = max(1, $start->diffInDays($end) + 1);

        $curve = [];
        $cumulative = 0.0;
        $cursor = $start;
        $dayIndex = 1;

        while ($cursor->lessThanOrEqualTo($end)) {
            $cumulative += $spentByDate[$cursor->toDateString()] ?? 0;
            $curve[] = [
                'date' => $cursor->toDateString(),
                'spent' => round($cumulative, 2),
                'ideal' => round($budgeted * ($dayIndex / $days), 2),
            ];
            $cursor = $cursor->addDay();
            $dayIndex++;
        }

        return $curve;
    }

    private function capOf(BudgetItem $item): float
    {
        return (float) $item->amount;
    }

    private function cycleStartDay(?User $user): int
    {
        return (int) ($user?->settings?->budget_cycle_start_day ?? 1);
    }
}
