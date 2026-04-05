<?php

namespace App\Mcp\Tools;

use App\Http\Resources\AccountResource;
use App\Models\Budget;
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
        Account balances reflect income and expenses only — settlements do not affect account balance.
        Includes currency_summaries showing budget_balance, total_budgeted, and ready_to_assign per currency.
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
     * @return array<string, array{budget_balance: float, total_budgeted: float, ready_to_assign: float}>
     */
    private function buildCurrencySummaries(\App\Models\User $user, \Illuminate\Database\Eloquent\Collection $accounts): array
    {
        $today = CarbonImmutable::now()->toDateString();

        // Sum of active budget item amounts per currency (raw per-cycle allocation)
        $activeBudgets = Budget::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->where('anchor_date', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $today);
            })
            ->with('items')
            ->get();

        $budgetedPerCurrency = [];
        foreach ($activeBudgets as $budget) {
            $currency = $budget->currency;
            $budgetedPerCurrency[$currency] = ($budgetedPerCurrency[$currency] ?? 0)
                + $budget->items->sum('amount') / 100;
        }

        // Build per-currency summary using only include_in_budget accounts
        $summaries = [];
        foreach ($accounts->groupBy('currency') as $currency => $currencyAccounts) {
            $budgetBalance = $currencyAccounts
                ->where('include_in_budget', true)
                ->sum('current_balance');

            $totalBudgeted = $budgetedPerCurrency[$currency] ?? 0;

            $summaries[$currency] = [
                'budget_balance' => $budgetBalance,
                'total_budgeted' => $totalBudgeted,
                'ready_to_assign' => $budgetBalance - $totalBudgeted,
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
