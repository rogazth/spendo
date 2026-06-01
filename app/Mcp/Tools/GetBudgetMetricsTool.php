<?php

namespace App\Mcp\Tools;

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
        Spending excludes transactions with exclude_from_budget=true and accounts with include_in_budget=false.
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

        $budget = $user->budgets()->with(['items.category.children'])->find($validated['budget_id']);

        if (! $budget) {
            return Response::error('Budget not found.');
        }

        $scope = $validated['scope'] ?? 'current';
        $referenceDate = CarbonImmutable::now()->startOfDay();

        [$cycleStart, $cycleEnd] = $budget->resolveCycleRange(
            $referenceDate,
            (int) ($user->settings?->budget_cycle_start_day ?? 1),
        );

        if ($scope === 'custom') {
            if (empty($validated['start_date']) || empty($validated['end_date'])) {
                return Response::error('start_date and end_date are required for custom scope.');
            }
            $cycleStart = CarbonImmutable::parse($validated['start_date'])->startOfDay();
            $cycleEnd = CarbonImmutable::parse($validated['end_date'])->startOfDay();
        }

        $categoryGroups = $budget->budgetCategoryGroups();
        $transactions = $user->transactions()
            ->forBudgetSpending($budget, $cycleStart, $cycleEnd)
            ->get(['category_id', 'amount']);

        $transactionsByCategory = [];
        foreach ($transactions as $transaction) {
            $transactionsByCategory[$transaction->category_id] = ($transactionsByCategory[$transaction->category_id] ?? 0) + abs($transaction->amount);
        }

        // Build per-category progress
        $categoryProgress = $budget->items->map(function ($item) use ($categoryGroups, $transactionsByCategory) {
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
