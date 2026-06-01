<?php

namespace App\Mcp\Tools;

use App\Enums\TransactionType;
use App\Http\Resources\TransactionResource;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetTransactionsTool extends Tool
{
    protected string $description = <<<'MARKDOWN'
        Get transactions with optional filters.

        Filter by date range, signed direction, category, account, tag, transfer flag, or budget.
        Budget filters use budget-eligible expenses and default to the current budget cycle when no date range is provided.
        Results are ordered by transaction date (most recent first).

        Includes a totals block with count, total debit (sum of outflows), and total credit (sum of inflows) for the filtered dataset.
        Transfers are excluded from totals by default. Direction filters use the signed amount and include transfer legs unless include_transfers=false.
    MARKDOWN;

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user) {
            return Response::error('User not authenticated.');
        }

        $query = $user->transactions()
            ->with(['category', 'account', 'tags']);

        if ($direction = $request->get('direction')) {
            if ($direction === 'in') {
                $query->where('amount', '>', 0);
            } elseif ($direction === 'out') {
                $query->where('amount', '<', 0);
            }
        }

        if (! $request->get('include_transfers', true)) {
            $query->where('type', '!=', TransactionType::Transfer);
        }

        if ($transfersOnly = $request->get('transfers_only')) {
            if ($transfersOnly) {
                $query->where('type', TransactionType::Transfer);
            }
        }

        if ($categoryIds = $request->get('category_ids')) {
            if (is_array($categoryIds)) {
                $query->whereIn('category_id', $categoryIds);
            }
        } elseif ($categoryId = $request->get('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if ($accountIds = $request->get('account_ids')) {
            if (is_array($accountIds)) {
                $query->whereIn('account_id', $accountIds);
            }
        } elseif ($accountId = $request->get('account_id')) {
            $query->where('account_id', $accountId);
        }

        if ($tagIds = $request->get('tag_ids')) {
            if (is_array($tagIds) && count($tagIds) > 0) {
                $query->whereHas('tags', fn ($q) => $q->whereIn('tags.id', $tagIds));
            }
        }

        $budgetDateRangeApplied = false;

        if ($budgetId = $request->get('budget_id')) {
            $budget = $user->budgets()->with('items.category.children')->find($budgetId);
            if ($budget) {
                $startDate = $request->get('start_date');
                $endDate = $request->get('end_date');

                if (! $startDate && ! $endDate) {
                    [$cycleStart, $cycleEnd] = $budget->resolveCycleRange(
                        CarbonImmutable::now()->startOfDay(),
                        (int) ($user->settings?->budget_cycle_start_day ?? 1),
                    );
                    $startDate = $cycleStart;
                    $endDate = $cycleEnd;
                    $budgetDateRangeApplied = true;
                }

                $query->forBudgetSpending($budget, $startDate, $endDate);
            }
        }

        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        if (! $budgetDateRangeApplied && $startDate) {
            $query->whereDate('transaction_date', '>=', $startDate);
        }

        if (! $budgetDateRangeApplied && $endDate) {
            $query->whereDate('transaction_date', '<=', $endDate);
        }

        $totalsQuery = clone $query;
        $nonTransferTotals = (clone $totalsQuery)
            ->where('type', '!=', TransactionType::Transfer)
            ->selectRaw('COALESCE(SUM(CASE WHEN amount < 0 THEN -amount ELSE 0 END), 0) as total_debit')
            ->selectRaw('COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END), 0) as total_credit')
            ->first();

        $totalDebitCents = (int) ($nonTransferTotals->total_debit ?? 0);
        $totalCreditCents = (int) ($nonTransferTotals->total_credit ?? 0);
        $totalCount = $totalsQuery->count();

        $page = max(1, (int) $request->get('page', 1));
        $perPage = max(1, min((int) $request->get('per_page', 50), 100));

        $transactions = $query
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        $result = TransactionResource::collection($transactions)->resolve();

        return Response::text(json_encode([
            'totals' => [
                'count' => $totalCount,
                'total_debit' => $totalDebitCents / 100,
                'total_credit' => $totalCreditCents / 100,
                'net' => ($totalCreditCents - $totalDebitCents) / 100,
            ],
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => (int) ceil($totalCount / $perPage),
            ],
            'count' => count($result),
            'transactions' => $result,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'direction' => $schema->string()
                ->description('Filter by signed direction: "in" (positive amounts, inflows) or "out" (negative amounts, outflows). Transfer legs are included unless include_transfers=false.')
                ->enum(['in', 'out']),
            'include_transfers' => $schema->boolean()
                ->description('Include transfer transactions in results (default: true).'),
            'transfers_only' => $schema->boolean()
                ->description('Return only transfer transactions (default: false).'),
            'category_id' => $schema->integer()
                ->description('Filter by single category ID'),
            'category_ids' => $schema->array()
                ->description('Filter by multiple category IDs'),
            'account_id' => $schema->integer()
                ->description('Filter by single account ID'),
            'account_ids' => $schema->array()
                ->description('Filter by multiple account IDs'),
            'tag_ids' => $schema->array()
                ->description('Filter by tag IDs — returns transactions that have any of the given tags'),
            'budget_id' => $schema->integer()
                ->description('Filter transactions belonging to a specific budget (by its category scope)'),
            'start_date' => $schema->string()
                ->description('Filter transactions on or after this date (YYYY-MM-DD)'),
            'end_date' => $schema->string()
                ->description('Filter transactions on or before this date (YYYY-MM-DD)'),
            'page' => $schema->integer()
                ->description('Page number (default: 1)'),
            'per_page' => $schema->integer()
                ->description('Results per page (default: 50, max: 100)'),
        ];
    }
}
