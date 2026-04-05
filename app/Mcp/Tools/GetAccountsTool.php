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

        $activeBudgets = Budget::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->where('anchor_date', '<=', $today->toDateString())
            ->where(function ($q) use ($today) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $today->toDateString());
            })
            ->with(['items.category.children'])
            ->get();

        // For each active budget item, compute how much is still unspent (reserved).
        // Money already spent has already left the account balance, so only the unspent
        // portion of each item should be counted: max(0, budgeted - spent).
        $reservedPerCurrency = [];
        foreach ($activeBudgets as $budget) {
            $currency = $budget->currency;
            [$cycleStart, $cycleEnd] = $budget->resolveCycleRange($today);

            $allCategoryIds = $budget->items->flatMap(function ($item) {
                $ids = [$item->category_id];
                if ($item->category && $item->category->relationLoaded('children')) {
                    $ids = array_merge($ids, $item->category->children->pluck('id')->all());
                }

                return $ids;
            })->filter()->unique()->values()->all();

            // Spending per category in this cycle (accessor returns major units)
            $spentByCategory = [];
            if ($allCategoryIds !== []) {
                $transactions = $user->transactions()
                    ->where('type', 'expense')
                    ->where('exclude_from_budget', false)
                    ->whereDate('transaction_date', '>=', $cycleStart->toDateString())
                    ->whereDate('transaction_date', '<=', $cycleEnd->toDateString())
                    ->whereIn('category_id', $allCategoryIds)
                    ->where('currency', $currency)
                    ->get(['category_id', 'amount']);

                foreach ($transactions as $t) {
                    $spentByCategory[$t->category_id] = ($spentByCategory[$t->category_id] ?? 0) + $t->amount;
                }
            }

            // Sum remaining per item: max(0, budgeted - spent_across_item_categories)
            $reserved = 0;
            foreach ($budget->items as $item) {
                $itemCategoryIds = [$item->category_id];
                if ($item->category && $item->category->relationLoaded('children')) {
                    $itemCategoryIds = array_merge($itemCategoryIds, $item->category->children->pluck('id')->all());
                }

                $itemSpent = collect($itemCategoryIds)->sum(fn ($catId) => $spentByCategory[$catId] ?? 0);
                $reserved += max(0, $item->amount - $itemSpent);
            }

            $reservedPerCurrency[$currency] = ($reservedPerCurrency[$currency] ?? 0) + $reserved;
        }

        // Build per-currency summary using only include_in_budget accounts
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
