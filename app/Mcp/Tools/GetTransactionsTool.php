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
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Get transactions with optional filters.
        You can filter by date range, transaction type, category, account, or payment method.
        Results are ordered by transaction date (most recent first).
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

        $query = $user->transactions()
            ->with(['category', 'account', 'paymentMethod']);

        // Filter by type
        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        // Filter by category
        if ($categoryId = $request->get('category_id')) {
            $query->where('category_id', $categoryId);
        }

        // Filter by account
        if ($accountId = $request->get('account_id')) {
            $query->where('account_id', $accountId);
        }

        // Filter by payment method
        if ($paymentMethodId = $request->get('payment_method_id')) {
            $query->where('payment_method_id', $paymentMethodId);
        }

        // Filter by date range
        if ($startDate = $request->get('start_date')) {
            $query->whereDate('transaction_date', '>=', $startDate);
        }

        if ($endDate = $request->get('end_date')) {
            $query->whereDate('transaction_date', '<=', $endDate);
        }

        // Limit results
        $limit = min($request->get('limit', 50), 100);

        $transactions = $query
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->limit($limit)
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
            'count' => $result->count(),
            'transactions' => $result,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()
                ->description('Filter by transaction type: expense, income, transfer_out, transfer_in, settlement')
                ->enum(['expense', 'income', 'transfer_out', 'transfer_in', 'settlement']),
            'category_id' => $schema->integer()
                ->description('Filter by category ID'),
            'account_id' => $schema->integer()
                ->description('Filter by account ID'),
            'payment_method_id' => $schema->integer()
                ->description('Filter by payment method ID'),
            'start_date' => $schema->string()
                ->description('Filter transactions on or after this date (YYYY-MM-DD)'),
            'end_date' => $schema->string()
                ->description('Filter transactions on or before this date (YYYY-MM-DD)'),
            'limit' => $schema->integer()
                ->description('Maximum number of transactions to return (default: 50, max: 100)'),
        ];
    }
}
