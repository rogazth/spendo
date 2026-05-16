<?php

namespace App\Mcp\Tools;

use App\Http\Resources\AccountResource;
use App\Services\BudgetMetricsService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetAccountsTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Get all user accounts with their current balances.
        Account balances are the signed sum of transactions, including transfer legs.
        Includes currency_summaries showing budget_balance, total_reserved, and ready_to_assign per currency.
        total_reserved = sum of unspent budget item amounts in the current cycle (max(0, budgeted - spent) per item).
        Accounts with include_in_budget=false (e.g. savings) are excluded from the budget summary.
        Optionally filter by active status.
    MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user) {
            return Response::error('User not authenticated.');
        }

        $query = $user->accounts();

        $includeInactive = $request->get('include_inactive', false);
        if (! $includeInactive) {
            $query->where('is_active', true);
        }

        $accounts = $query->orderBy('sort_order')->orderBy('name')->get();

        $result = AccountResource::collection($accounts)->resolve();

        $currencySummaries = $this->buildCurrencySummaries($user, $accounts);

        return Response::text(json_encode([
            'count' => count($result),
            'currency_summaries' => $currencySummaries,
            'accounts' => $result,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, \App\Models\Account>  $accounts
     * @return array<string, array{budget_balance: float, total_reserved: float, ready_to_assign: float}>
     */
    private function buildCurrencySummaries(\App\Models\User $user, \Illuminate\Database\Eloquent\Collection $accounts): array
    {
        $today = CarbonImmutable::now()->startOfDay();

        $reservedPerCurrency = app(BudgetMetricsService::class)
            ->forActiveBudgets($user, $today)
            ->groupBy(fn (array $metrics) => $metrics['budget']->currency)
            ->map(fn ($group) => (float) $group->sum('reserved'))
            ->all();

        $summaries = [];
        foreach ($accounts->groupBy('currency') as $currency => $currencyAccounts) {
            $budgetBalance = $currencyAccounts
                ->where('include_in_budget', true)
                ->sum('current_balance');

            $totalReserved = $reservedPerCurrency[$currency] ?? 0;

            $summaries[$currency] = [
                'budget_balance' => $budgetBalance,
                'total_reserved' => $totalReserved,
                'ready_to_assign' => $budgetBalance - $totalReserved,
            ];
        }

        return $summaries;
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'include_inactive' => $schema->boolean()
                ->description('Include inactive accounts (default: false)'),
        ];
    }
}
