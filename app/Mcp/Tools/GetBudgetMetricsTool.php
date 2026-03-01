<?php

namespace App\Mcp\Tools;

use App\Models\Budget;
use App\Models\BudgetItem;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetBudgetMetricsTool extends Tool
{
    protected string $description = <<<'MARKDOWN'
        Get detailed budget metrics including overall progress and per-category breakdown.

        **Scope**:
        - `current`: Current cycle only (default)
        - `custom`: Custom date range (provide start_date and end_date)

        Returns budget-level totals and category-level progress (budgeted, spent, remaining, percentages).
    MARKDOWN;

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user) {
            return Response::error('User not authenticated.');
        }

        $validated = $request->validate([
            'budget_id' => ['required', 'integer'],
            'scope' => ['nullable', 'string', 'in:current,custom'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ], [
            'budget_id.required' => 'Budget ID is required. Use GetBudgetsTool to find budgets.',
        ]);

        $budget = $user->budgets()->with(['account', 'items.category.children'])->find($validated['budget_id']);

        if (! $budget) {
            return Response::error('Budget not found.');
        }

        $scope = $validated['scope'] ?? 'current';
        $referenceDate = CarbonImmutable::now()->startOfDay();

        [$cycleStart, $cycleEnd] = $this->resolveCycleRange($budget, $referenceDate);

        if ($scope === 'custom') {
            if (empty($validated['start_date']) || empty($validated['end_date'])) {
                return Response::error('start_date and end_date are required for custom scope.');
            }
            $cycleStart = CarbonImmutable::parse($validated['start_date'])->startOfDay();
            $cycleEnd = CarbonImmutable::parse($validated['end_date'])->startOfDay();
        }

        // Build category groups (budget item -> category IDs including children)
        $categoryGroups = $this->budgetItemCategoryGroups($budget);
        $allCategoryIds = collect($categoryGroups)->flatten()->unique()->values()->all();

        // Get all transactions in range for these categories
        $transactionsByCategory = $this->getTransactionsByCategory($budget, $cycleStart, $cycleEnd, $allCategoryIds);

        // Build per-category progress
        $categoryProgress = $budget->items->map(function (BudgetItem $item) use ($categoryGroups, $transactionsByCategory) {
            $groupCategoryIds = $categoryGroups[$item->id] ?? [];
            $spent = collect($groupCategoryIds)
                ->sum(fn ($catId) => $transactionsByCategory[$catId] ?? 0);

            $budgeted = $item->amount;
            $remaining = $budgeted - $spent;
            $spentPct = $budgeted > 0 ? min(100, round(($spent / $budgeted) * 100, 2)) : 0;

            return [
                'category_id' => $item->category_id,
                'category_name' => $item->category?->name ?? 'Unknown',
                'budgeted' => $budgeted,
                'spent' => $spent,
                'remaining' => $remaining,
                'spent_percentage' => $spentPct,
                'remaining_percentage' => round(100 - $spentPct, 2),
            ];
        })->values();

        $totalBudgeted = $budget->total_budgeted;
        $totalSpent = $categoryProgress->sum('spent');
        $totalRemaining = $totalBudgeted - $totalSpent;
        $totalSpentPct = $totalBudgeted > 0
            ? min(100, round(($totalSpent / $totalBudgeted) * 100, 2))
            : 0;

        return Response::text(json_encode([
            'budget' => [
                'id' => $budget->id,
                'uuid' => $budget->uuid,
                'name' => $budget->name,
                'currency' => $budget->currency,
                'frequency' => $budget->frequency,
            ],
            'cycle' => [
                'scope' => $scope,
                'start' => $cycleStart->toDateString(),
                'end' => $cycleEnd->toDateString(),
            ],
            'summary' => [
                'budgeted_amount' => $totalBudgeted,
                'spent_amount' => $totalSpent,
                'remaining_amount' => $totalRemaining,
                'spent_percentage' => $totalSpentPct,
                'remaining_percentage' => round(100 - $totalSpentPct, 2),
            ],
            'categories' => $categoryProgress,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    private function resolveCycleRange(Budget $budget, CarbonImmutable $referenceDate): array
    {
        $anchorDate = CarbonImmutable::parse($budget->anchor_date)->startOfDay();
        $effectiveReference = $referenceDate->startOfDay();
        $budgetEndDate = $budget->ends_at
            ? CarbonImmutable::parse($budget->ends_at)->startOfDay()
            : null;

        if ($budgetEndDate !== null && $effectiveReference->greaterThan($budgetEndDate)) {
            $effectiveReference = $budgetEndDate;
        }

        if ($effectiveReference->lessThan($anchorDate)) {
            $effectiveReference = $anchorDate;
        }

        if (in_array($budget->frequency, ['weekly', 'biweekly'], true)) {
            $stepInDays = $budget->frequency === 'weekly' ? 7 : 14;
            $daysSinceAnchor = max(0, $anchorDate->diffInDays($effectiveReference, false));
            $cycleIndex = intdiv($daysSinceAnchor, $stepInDays);
            $cycleStart = $anchorDate->addDays($cycleIndex * $stepInDays);
            $cycleEnd = $cycleStart->addDays($stepInDays - 1);
        } else {
            $stepInMonths = $budget->frequency === 'bimonthly' ? 2 : 1;
            $monthsSinceAnchor = max(0, $anchorDate->diffInMonths($effectiveReference, false));
            $cycleIndex = intdiv($monthsSinceAnchor, $stepInMonths);
            $cycleStart = $anchorDate->addMonthsNoOverflow($cycleIndex * $stepInMonths);
            $cycleEnd = $cycleStart->addMonthsNoOverflow($stepInMonths)->subDay();
        }

        if ($budgetEndDate !== null && $cycleEnd->greaterThan($budgetEndDate)) {
            $cycleEnd = $budgetEndDate;
        }

        return [$cycleStart, $cycleEnd];
    }

    /**
     * @return array<int, array<int, int>>
     */
    private function budgetItemCategoryGroups(Budget $budget): array
    {
        return $budget->items->mapWithKeys(function (BudgetItem $item) {
            $category = $item->category;
            if (! $category) {
                return [$item->id => []];
            }

            $categoryIds = [$category->id];
            $childrenIds = $category->relationLoaded('children')
                ? $category->children->pluck('id')->all()
                : $category->children()->pluck('id')->all();

            return [$item->id => array_values(array_unique(array_merge($categoryIds, $childrenIds)))];
        })->all();
    }

    /**
     * @return array<int, float>
     */
    private function getTransactionsByCategory(
        Budget $budget,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
        array $categoryIds,
    ): array {
        if ($categoryIds === []) {
            return [];
        }

        $query = $budget->user->transactions()
            ->where('type', 'expense')
            ->where('exclude_from_budget', false)
            ->whereDate('transaction_date', '>=', $startDate->toDateString())
            ->whereDate('transaction_date', '<=', $endDate->toDateString())
            ->whereIn('category_id', $categoryIds);

        if ($budget->account_id !== null) {
            $query->where('account_id', $budget->account_id);
        }

        $transactions = $query->get(['category_id', 'amount']);

        $result = [];
        foreach ($transactions as $t) {
            // Raw DB amount is in cents, but the Attribute accessor returns major units
            $result[$t->category_id] = ($result[$t->category_id] ?? 0) + $t->amount;
        }

        return $result;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'budget_id' => $schema->integer()
                ->description('Budget ID. Use GetBudgetsTool to find budgets.')
                ->required(),
            'scope' => $schema->string()
                ->description('Scope: current (default) or custom')
                ->enum(['current', 'custom']),
            'start_date' => $schema->string()
                ->description('Start date for custom scope (YYYY-MM-DD)'),
            'end_date' => $schema->string()
                ->description('End date for custom scope (YYYY-MM-DD)'),
        ];
    }
}
