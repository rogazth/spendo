<?php

namespace App\Mcp\Tools;

use App\Http\Resources\BudgetResource;
use App\Models\Budget;
use App\Models\User;
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
        Spending excludes transactions with exclude_from_budget=true and accounts with include_in_budget=false.
    MARKDOWN;

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user) {
            return Response::error('User not authenticated.');
        }

        $query = $user->budgets()->with(['items.category.children']);

        $includeInactive = $request->get('include_inactive', false);
        if (! $includeInactive) {
            $query->where('is_active', true);
        }

        $budgets = $query->latest()->get();
        $referenceDate = CarbonImmutable::now()->startOfDay();
        $cycleStartDay = (int) ($user->settings?->budget_cycle_start_day ?? 1);

        $result = $budgets->map(function (Budget $budget) use ($referenceDate, $user, $cycleStartDay) {
            [$cycleStart, $cycleEnd] = $budget->resolveCycleRange($referenceDate, $cycleStartDay);
            $spent = $this->calculateBudgetSpent($user, $budget, $cycleStart, $cycleEnd);
            $totalBudgeted = $budget->total_budgeted;
            $remaining = $totalBudgeted - $spent;
            $percentage = $totalBudgeted > 0
                ? min(100, round(($spent / $totalBudgeted) * 100, 2))
                : 0;

            $budgetData = (new BudgetResource($budget))->resolve();
            unset($budgetData['items']);
            $budgetData['items_count'] = $budget->items->count();
            $budgetData['current_cycle'] = [
                'start' => $cycleStart->toDateString(),
                'end' => $cycleEnd->toDateString(),
                'spent' => $spent,
                'remaining' => $remaining,
                'spent_percentage' => $percentage,
                'remaining_percentage' => round(100 - $percentage, 2),
            ];

            return $budgetData;
        });

        return Response::text(json_encode([
            'count' => $result->count(),
            'budgets' => $result,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function calculateBudgetSpent(
        User $user,
        Budget $budget,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
    ): float {
        $totalInCents = (int) ($user->transactions()
            ->forBudgetSpending($budget, $startDate, $endDate)
            ->selectRaw('COALESCE(SUM(-amount), 0) as total')
            ->value('total') ?? 0);

        return $totalInCents / 100;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'include_inactive' => $schema->boolean()
                ->description('Include inactive budgets (default: false)'),
        ];
    }
}
