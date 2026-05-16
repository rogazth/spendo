<?php

namespace App\Mcp\Tools;

use App\Actions\Transactions\UpdateTransactionAction;
use App\Http\Resources\TransactionResource;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class UpdateTransactionTool extends Tool
{
    protected string $description = <<<'MARKDOWN'
        Update an existing transaction. Only provided fields will be changed.
        Use GetTransactionsTool first to find the transaction ID.

        **Supports**: income/expense transactions (non-transfer).
        Transfers cannot be updated — delete and recreate them via CreateTransferTool.

        **Amount sign determines direction:**
        - Negative amount → expense
        - Positive amount → income
        - Do not send a `type` field; transactions no longer have one.

        **Amount**: Signed value in major currency units (e.g., -572000 for a 572,000 CLP expense).
    MARKDOWN;

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user) {
            return Response::error('User not authenticated.');
        }

        $validated = $request->validate([
            'transaction_id' => ['required', 'integer'],
            'type' => ['prohibited'],
            'description' => ['nullable', 'string', 'max:255'],
            'amount' => ['nullable', 'numeric', 'not_in:0'],
            'category_id' => ['nullable', 'integer'],
            'account_id' => ['nullable', 'integer'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer'],
            'transaction_date' => ['nullable', 'date'],
            'exclude_from_budget' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ], [
            'type.prohibited' => 'Transaction type is no longer used. Use a negative amount for expenses and a positive amount for income.',
        ]);

        $transaction = $user->transactions()->find($validated['transaction_id']);

        if (! $transaction) {
            return Response::error('Transaction not found.');
        }

        $data = array_filter($validated, fn ($v, $k) => $k !== 'transaction_id', ARRAY_FILTER_USE_BOTH);

        // Preserve tag_ids key even if empty so sync is triggered when explicitly passed
        if (array_key_exists('tag_ids', $validated)) {
            $data['tag_ids'] = $validated['tag_ids'] ?? [];
        }

        $hasUpdatableFields = array_filter($data, fn ($v, $k) => $k !== 'tag_ids' && $v !== null, ARRAY_FILTER_USE_BOTH);

        if (empty($hasUpdatableFields) && ! array_key_exists('tag_ids', $data)) {
            return Response::error('No fields provided to update.');
        }

        try {
            $transaction = app(UpdateTransactionAction::class)->handle($transaction, $user, $data);
        } catch (\InvalidArgumentException $e) {
            return Response::error($e->getMessage());
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return Response::error($e->getMessage());
        }

        $transaction->load(['category', 'account', 'tags']);

        return Response::text(json_encode([
            'success' => true,
            'message' => 'Transaction updated successfully.',
            'transaction' => (new TransactionResource($transaction))->resolve(),
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
                ->description('New signed amount in major currency units. Negative = expense, positive = income. Do not send a type field.'),
            'category_id' => $schema->integer()
                ->description('New category ID. Use GetCategoriesTool to find categories.'),
            'account_id' => $schema->integer()
                ->description('New account ID. Use GetAccountsTool to find accounts.'),
            'tag_ids' => $schema->array()
                ->description('Array of tag IDs to set on the transaction. Pass an empty array to remove all tags.'),
            'transaction_date' => $schema->string()
                ->description('New transaction date (YYYY-MM-DD).'),
            'exclude_from_budget' => $schema->boolean()
                ->description('Exclude from budget calculations.'),
            'notes' => $schema->string()
                ->description('Notes for the transaction.'),
        ];
    }
}
