<?php

namespace App\Mcp\Tools;

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
        Filter by date range, type, category, account, payment method, or budget.
        Results are ordered by transaction date (most recent first).
        Includes a totals block with count, total debit, and total credit for the filtered dataset.
    MARKDOWN;

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user) {
            return Response::error('User not authenticated.');
        }

        $query = $user->transactions()
            ->with(['category', 'account', 'paymentMethod']);

        // Filter by type
        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        // Filter by category (single or multiple)
        if ($categoryIds = $request->get('category_ids')) {
            if (is_array($categoryIds)) {
                $query->whereIn('category_id', $categoryIds);
            }
        } elseif ($categoryId = $request->get('category_id')) {
            $query->where('category_id', $categoryId);
        }

        // Filter by account (single or multiple)
        if ($accountIds = $request->get('account_ids')) {
            if (is_array($accountIds)) {
                $query->whereIn('account_id', $accountIds);
            }
        } elseif ($accountId = $request->get('account_id')) {
            $query->where('account_id', $accountId);
        }

        // Filter by payment method (single or multiple)
        if ($paymentMethodIds = $request->get('payment_method_ids')) {
            if (is_array($paymentMethodIds)) {
                $query->whereIn('payment_method_id', $paymentMethodIds);
            }
        } elseif ($paymentMethodId = $request->get('payment_method_id')) {
            $query->where('payment_method_id', $paymentMethodId);
        }

        // Filter by budget (resolve budget category IDs)
        if ($budgetId = $request->get('budget_id')) {
            $budget = $user->budgets()->with('items.category.children')->find($budgetId);
            if ($budget) {
                $categoryIds = $budget->items->flatMap(function ($item) {
                    $ids = [$item->category_id];
                    if ($item->category && $item->category->relationLoaded('children')) {
                        $ids = array_merge($ids, $item->category->children->pluck('id')->all());
                    }

                    return $ids;
                })->unique()->values()->all();

                $query->where('type', 'expense')
                    ->where('exclude_from_budget', false)
                    ->whereIn('category_id', $categoryIds);

                if ($budget->account_id !== null) {
                    $query->where('account_id', $budget->account_id);
                }
            }
        }

        // Filter by date range
        if ($startDate = $request->get('start_date')) {
            $query->whereDate('transaction_date', '>=', $startDate);
        }

        if ($endDate = $request->get('end_date')) {
            $query->whereDate('transaction_date', '<=', $endDate);
        }

        // Calculate totals before pagination
        $totalsQuery = clone $query;
        $totalDebitCents = (clone $totalsQuery)
            ->whereIn('type', ['expense', 'transfer_out', 'settlement'])
            ->sum('amount');
        $totalCreditCents = (clone $totalsQuery)
            ->whereIn('type', ['income', 'transfer_in'])
            ->sum('amount');
        $totalCount = $totalsQuery->count();

        // Pagination
        $page = max(1, (int) $request->get('page', 1));
        $perPage = max(1, min((int) $request->get('per_page', 50), 100));

        $transactions = $query
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        $result = $transactions->map(fn ($t) => [
            'id' => $t->id,
            'uuid' => $t->uuid,
            'type' => $t->type->value,
            'type_label' => $t->type->label(),
            'amount' => $t->amount,
            'amount_formatted' => $t->formatted_amount,
            'currency' => $t->currency,
            'description' => $t->description,
            'notes' => $t->notes,
            'exclude_from_budget' => $t->exclude_from_budget,
            'transaction_date' => $t->transaction_date->format('Y-m-d'),
            'category' => $t->category ? [
                'id' => $t->category->id,
                'uuid' => $t->category->uuid,
                'name' => $t->category->full_name,
                'type' => $t->category->type->value,
            ] : null,
            'account' => $t->account ? [
                'id' => $t->account->id,
                'uuid' => $t->account->uuid,
                'name' => $t->account->name,
            ] : null,
            'payment_method' => $t->paymentMethod ? [
                'id' => $t->paymentMethod->id,
                'uuid' => $t->paymentMethod->uuid,
                'name' => $t->paymentMethod->name,
                'type' => $t->paymentMethod->type->value,
            ] : null,
        ]);

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
            'count' => $result->count(),
            'transactions' => $result,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()
                ->description('Filter by transaction type: expense, income, transfer_out, transfer_in, settlement')
                ->enum(['expense', 'income', 'transfer_out', 'transfer_in', 'settlement']),
            'category_id' => $schema->integer()
                ->description('Filter by single category ID'),
            'category_ids' => $schema->array()
                ->description('Filter by multiple category IDs'),
            'account_id' => $schema->integer()
                ->description('Filter by single account ID'),
            'account_ids' => $schema->array()
                ->description('Filter by multiple account IDs'),
            'payment_method_id' => $schema->integer()
                ->description('Filter by single payment method ID'),
            'payment_method_ids' => $schema->array()
                ->description('Filter by multiple payment method IDs'),
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
