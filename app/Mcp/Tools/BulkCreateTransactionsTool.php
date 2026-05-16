<?php

namespace App\Mcp\Tools;

use App\Actions\Transactions\CreateTransactionAction;
use App\Http\Resources\TransactionResource;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class BulkCreateTransactionsTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Create multiple income/expense transactions in a single request. Each item follows the same rules as CreateTransactionTool.

        - Requires per item: account_id, category_id, amount, description
        - Optional per item: tag_ids, transaction_date, notes, exclude_from_budget, idempotency_key

        **Amount sign determines direction:**
        - Negative amount → expense
        - Positive amount → income
        - Do not send a `type` field; transactions no longer have one.

        **Amount**: Major currency units (e.g., 572000 for 572,000 CLP).

        Transfers between accounts are NOT supported in bulk; use CreateTransferTool instead.

        Request-level validation errors reject the whole batch before processing. After validation, transactions are processed independently and business-rule failures are reported per item with an index matching the input array. Use `idempotency_key` per transaction to safely retry the entire batch without duplicates.
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

        $validated = $request->validate([
            'transactions' => ['required', 'array', 'min:1', 'max:100'],
            'transactions.*.type' => ['prohibited'],
            'transactions.*.amount' => ['required', 'numeric', 'not_in:0'],
            'transactions.*.description' => ['required', 'string', 'max:255'],
            'transactions.*.account_id' => ['required', 'integer'],
            'transactions.*.category_id' => ['required', 'integer'],
            'transactions.*.tag_ids' => ['nullable', 'array'],
            'transactions.*.tag_ids.*' => ['integer'],
            'transactions.*.transaction_date' => ['nullable', 'date'],
            'transactions.*.notes' => ['nullable', 'string'],
            'transactions.*.exclude_from_budget' => ['nullable', 'boolean'],
            'transactions.*.idempotency_key' => ['nullable', 'string', 'max:255'],
        ], [
            'transactions.required' => 'Transactions array is required.',
            'transactions.min' => 'At least one transaction is required.',
            'transactions.max' => 'Maximum 100 transactions per request.',
            'transactions.*.type.prohibited' => 'Transaction type is no longer used. Use negative amounts for expenses and positive amounts for income.',
            'transactions.*.amount.required' => 'Amount is required for each transaction.',
            'transactions.*.amount.not_in' => 'Amount cannot be zero.',
            'transactions.*.description.required' => 'Description is required for each transaction.',
            'transactions.*.account_id.required' => 'Account ID is required for each transaction.',
            'transactions.*.category_id.required' => 'Category ID is required for each transaction.',
        ]);

        $results = [];
        $successCount = 0;
        $failureCount = 0;

        foreach ($validated['transactions'] as $index => $item) {
            $result = $this->processTransaction($user, $item, $index);

            if ($result['success']) {
                $successCount++;
            } else {
                $failureCount++;
            }

            $results[] = $result;
        }

        return Response::text(json_encode([
            'success' => $failureCount === 0,
            'summary' => [
                'total' => count($results),
                'succeeded' => $successCount,
                'failed' => $failureCount,
            ],
            'results' => $results,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array<string, mixed>
     */
    private function processTransaction(mixed $user, array $item, int $index): array
    {
        if (! empty($item['idempotency_key'])) {
            $idempotencyTag = 'idempotency:'.str_replace(['%', '_'], ['\\%', '\\_'], $item['idempotency_key']);
            $existing = $user->transactions()
                ->whereRaw("notes LIKE ? ESCAPE '\\'", ['%'.$idempotencyTag.'%'])
                ->first();

            if ($existing) {
                $existing->load(['category', 'account', 'tags']);

                return [
                    'index' => $index,
                    'success' => true,
                    'idempotent' => true,
                    'message' => 'Transaction already exists (idempotent).',
                    'transaction' => (new TransactionResource($existing))->resolve(),
                ];
            }
        }

        $data = $item;
        $data['notes'] = $this->buildNotes($item);

        try {
            $transaction = app(CreateTransactionAction::class)->handle($user, $data);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ['index' => $index, 'success' => false, 'message' => $e->getMessage()];
        } catch (\InvalidArgumentException $e) {
            return ['index' => $index, 'success' => false, 'message' => $e->getMessage()];
        } catch (\Throwable $e) {
            return ['index' => $index, 'success' => false, 'message' => $e->getMessage()];
        }

        $transaction->load(['category', 'account', 'tags']);

        return [
            'index' => $index,
            'success' => true,
            'message' => 'Transaction created successfully.',
            'transaction' => (new TransactionResource($transaction))->resolve(),
        ];
    }

    private function buildNotes(array $item): ?string
    {
        $notes = $item['notes'] ?? '';

        if (! empty($item['idempotency_key'])) {
            $idempotencyTag = 'idempotency:'.$item['idempotency_key'];
            $notes = $notes ? $notes."\n".$idempotencyTag : $idempotencyTag;
        }

        return $notes ?: null;
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        $transactionItem = $schema->object([
            'amount' => $schema->number()
                ->description('Signed amount in major currency units. Negative = expense, positive = income.')
                ->required(),
            'description' => $schema->string()
                ->description('Transaction description (e.g., "Supermercado Líder").')
                ->required(),
            'account_id' => $schema->integer()
                ->description('Account ID. Use GetAccountsTool to find accounts.')
                ->required(),
            'category_id' => $schema->integer()
                ->description('Category ID. Use GetCategoriesTool to find categories.')
                ->required(),
            'tag_ids' => $schema->array()
                ->description('Optional array of tag IDs to attach to the transaction.'),
            'transaction_date' => $schema->string()
                ->description('Transaction date (YYYY-MM-DD). Defaults to today.'),
            'notes' => $schema->string()
                ->description('Optional notes for the transaction.'),
            'exclude_from_budget' => $schema->boolean()
                ->description('Exclude this transaction from budget calculations (default: false).'),
            'idempotency_key' => $schema->string()
                ->description('Optional idempotency key to prevent duplicate transactions on retry.'),
        ]);

        return [
            'transactions' => $schema->array()
                ->description('Array of transactions to create (1–100 items). Each follows the same rules as CreateTransactionTool: signed amount, no type field. Transfers are not supported in bulk.')
                ->min(1)
                ->max(100)
                ->items($transactionItem)
                ->required(),
        ];
    }
}
