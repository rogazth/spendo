<?php

namespace App\Mcp\Tools;

use App\Models\Budget;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetBudgetsTool extends Tool
{
    protected string $description = <<<'MARKDOWN'
        List all budgets with their current cycle progress.
        Returns budget details including total budgeted, spent amount, and remaining percentage.
    MARKDOWN;

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user) {
            return Response::error('User not authenticated.');
        }

        $query = $user->budgets()->with(['account', 'items.category.children']);

        $includeInactive = $request->get('include_inactive', false);
        if (! $includeInactive) {
            $query->where('is_active', true);
        }

        $budgets = $query->latest()->get();
        $referenceDate = CarbonImmutable::now()->startOfDay();

        $result = $budgets->map(function (Budget $budget) use ($referenceDate) {
            [$cycleStart, $cycleEnd] = $this->resolveCycleRange($budget, $referenceDate);
            $categoryIds = $this->collectBudgetCategoryIds($budget);
            $spent = $this->calculateBudgetSpent($budget, $cycleStart, $cycleEnd, $categoryIds);
            $totalBudgeted = $budget->total_budgeted;
            $remaining = $totalBudgeted - $spent;
            $percentage = $totalBudgeted > 0
                ? min(100, round(($spent / $totalBudgeted) * 100, 2))
                : 0;

            return [
                'id' => $budget->id,
                'uuid' => $budget->uuid,
                'name' => $budget->name,
                'description' => $budget->description,
                'currency' => $budget->currency,
                'frequency' => $budget->frequency,
                'anchor_date' => $budget->anchor_date->format('Y-m-d'),
                'ends_at' => $budget->ends_at?->format('Y-m-d'),
                'is_active' => $budget->is_active,
                'account' => $budget->account ? [
                    'id' => $budget->account->id,
                    'name' => $budget->account->name,
                ] : null,
                'total_budgeted' => $totalBudgeted,
                'current_cycle' => [
                    'start' => $cycleStart->toDateString(),
                    'end' => $cycleEnd->toDateString(),
                    'spent' => $spent,
                    'remaining' => $remaining,
                    'spent_percentage' => $percentage,
                    'remaining_percentage' => round(100 - $percentage, 2),
                ],
                'items_count' => $budget->items->count(),
            ];
        });

        return Response::text(json_encode([
            'count' => $result->count(),
            'budgets' => $result,
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
     * @return array<int, int>
     */
    private function collectBudgetCategoryIds(Budget $budget): array
    {
        return $budget->items->flatMap(function ($item) {
            $ids = [$item->category_id];
            if ($item->category && $item->category->relationLoaded('children')) {
                $ids = array_merge($ids, $item->category->children->pluck('id')->all());
            }

            return $ids;
        })->unique()->values()->all();
    }

    private function calculateBudgetSpent(
        Budget $budget,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
        array $categoryIds,
    ): float {
        if ($categoryIds === []) {
            return 0;
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

        return $query->sum('amount') / 100;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'include_inactive' => $schema->boolean()
                ->description('Include inactive budgets (default: false)'),
        ];
    }
}
