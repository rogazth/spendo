<?php

namespace App\Mcp\Tools;

use App\Enums\CategoryType;
use App\Models\Category;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class UpdateTransactionTool extends Tool
{
    protected string $description = <<<'MARKDOWN'
        Update an existing transaction. Only provided fields will be changed.
        Use GetTransactionsTool first to find the transaction ID.

        **Supported types**: expense, income, settlement.
        Transfers (transfer_out / transfer_in) cannot be updated — delete and recreate them instead.

        **Amount**: Provide in major currency units (e.g., 572000 for 572,000 CLP).
    MARKDOWN;

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user) {
            return Response::error('User not authenticated.');
        }

        $validated = $request->validate([
            'transaction_id' => ['required', 'integer'],
            'description' => ['nullable', 'string', 'max:255'],
            'amount' => ['nullable', 'numeric', 'gt:0'],
            'category_id' => ['nullable', 'integer'],
            'account_id' => ['nullable', 'integer'],
            'instrument_id' => ['nullable', 'integer'],
            'transaction_date' => ['nullable', 'date'],
            'exclude_from_budget' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $transaction = $user->transactions()->find($validated['transaction_id']);

        if (! $transaction) {
            return Response::error('Transaction not found.');
        }

        if (in_array($transaction->type->value, ['transfer_out', 'transfer_in'])) {
            return Response::error('Transfer transactions cannot be updated. Delete and recreate them instead.');
        }

        $updates = [];

        if (array_key_exists('description', $validated) && $validated['description'] !== null) {
            $updates['description'] = $validated['description'];
        }

        if (array_key_exists('amount', $validated) && $validated['amount'] !== null) {
            $updates['amount'] = $validated['amount'];
        }

        if (array_key_exists('transaction_date', $validated) && $validated['transaction_date'] !== null) {
            $updates['transaction_date'] = $validated['transaction_date'];
        }

        if (array_key_exists('exclude_from_budget', $validated) && $validated['exclude_from_budget'] !== null) {
            $updates['exclude_from_budget'] = $validated['exclude_from_budget'];
        }

        if (array_key_exists('notes', $validated) && $validated['notes'] !== null) {
            $updates['notes'] = $validated['notes'];
        }

        if (! empty($validated['category_id'])) {
            $expectedType = $transaction->type->value === 'income' ? CategoryType::Income : CategoryType::Expense;

            $category = Category::where(function ($q) use ($user) {
                $q->whereNull('user_id')->orWhere('user_id', $user->id);
            })->find($validated['category_id']);

            if (! $category) {
                return Response::error('Category not found or not accessible.');
            }

            if ($category->type !== $expectedType) {
                return Response::error("Use a {$expectedType->value} category for this transaction type.");
            }

            $updates['category_id'] = $category->id;
        }

        if (! empty($validated['account_id'])) {
            $account = $user->accounts()->find($validated['account_id']);
            if (! $account) {
                return Response::error('Account not found.');
            }
            $updates['account_id'] = $account->id;
        }

        if (array_key_exists('instrument_id', $validated)) {
            if ($validated['instrument_id'] !== null) {
                $instrument = $user->instruments()->find($validated['instrument_id']);
                if (! $instrument) {
                    return Response::error('Instrument not found.');
                }
                $updates['instrument_id'] = $instrument->id;
            } else {
                $updates['instrument_id'] = null;
            }
        }

        if (empty($updates)) {
            return Response::error('No fields provided to update.');
        }

        $transaction->update($updates);
        $transaction->load(['category', 'account', 'instrument', 'fromInstrument']);

        return Response::text(json_encode([
            'success' => true,
            'message' => 'Transaction updated successfully.',
            'transaction' => [
                'id' => $transaction->id,
                'uuid' => $transaction->uuid,
                'type' => $transaction->type->value,
                'amount' => $transaction->amount,
                'currency' => $transaction->currency,
                'description' => $transaction->description,
                'transaction_date' => $transaction->transaction_date->format('Y-m-d'),
                'exclude_from_budget' => $transaction->exclude_from_budget,
                'notes' => $transaction->notes,
                'category' => $transaction->category?->full_name,
                'account' => $transaction->account?->name,
                'instrument' => $transaction->instrument?->name,
                'from_instrument' => $transaction->fromInstrument?->name,
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'transaction_id' => $schema->integer()
                ->description('The ID of the transaction to update.')
                ->required(),
            'description' => $schema->string()
                ->description('New description.'),
            'amount' => $schema->number()
                ->description('New amount in major currency units (e.g., 572000 for 572,000 CLP).'),
            'category_id' => $schema->integer()
                ->description('New category ID. Use GetCategoriesTool to find categories.'),
            'account_id' => $schema->integer()
                ->description('New account ID. Use GetAccountsTool to find accounts.'),
            'instrument_id' => $schema->integer()
                ->description('New instrument ID, or null to remove. Use GetInstrumentsTool.'),
            'transaction_date' => $schema->string()
                ->description('New transaction date (YYYY-MM-DD).'),
            'exclude_from_budget' => $schema->boolean()
                ->description('Exclude from budget calculations.'),
            'notes' => $schema->string()
                ->description('Notes for the transaction.'),
        ];
    }
}
