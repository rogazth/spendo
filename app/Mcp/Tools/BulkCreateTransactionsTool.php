<?php

namespace App\Mcp\Tools;

use App\Actions\Transactions\CreateExpenseAction;
use App\Actions\Transactions\CreateIncomeAction;
use App\Actions\Transactions\CreateTransferAction;
use App\Enums\TransactionType;
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
        Create multiple transactions in a single request. Each item follows the same rules as CreateTransactionTool.

        **For expenses:** Requires account_id, category_id, amount, description. Optionally tag_ids.
        **For income:** Requires account_id, category_id (income type), amount, description
        **For transfers:** Requires origin_account_id, destination_account_id, amount, description

        **Amount**: Major currency units (e.g., 572000 for 572,000 CLP)

        Transactions are processed independently — partial success is possible.
        Results include per-item success/failure details with an index matching the input array.
        Use `idempotency_key` per transaction to safely retry the entire batch without duplicates.
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
            'transactions.*.type' => ['required', 'string', 'in:expense,income,transfer'],
            'transactions.*.amount' => ['required', 'numeric', 'gt:0'],
            'transactions.*.description' => ['required', 'string', 'max:255'],
            'transactions.*.category_id' => ['nullable', 'integer'],
            'transactions.*.tag_ids' => ['nullable', 'array'],
            'transactions.*.tag_ids.*' => ['integer'],
            'transactions.*.account_id' => ['nullable', 'integer'],
            'transactions.*.origin_account_id' => ['nullable', 'integer'],
            'transactions.*.destination_account_id' => ['nullable', 'integer'],
            'transactions.*.transaction_date' => ['nullable', 'date'],
            'transactions.*.notes' => ['nullable', 'string'],
            'transactions.*.exclude_from_budget' => ['nullable', 'boolean'],
            'transactions.*.idempotency_key' => ['nullable', 'string', 'max:255'],
        ], [
            'transactions.required' => 'Transactions array is required.',
            'transactions.min' => 'At least one transaction is required.',
            'transactions.max' => 'Maximum 100 transactions per request.',
            'transactions.*.type.required' => 'Transaction type is required for each item.',
            'transactions.*.type.in' => 'Transaction type must be expense, income, or transfer.',
            'transactions.*.amount.required' => 'Amount is required for each transaction.',
            'transactions.*.amount.gt' => 'Amount must be greater than zero.',
            'transactions.*.description.required' => 'Description is required for each transaction.',
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
        // Idempotency check
        if (! empty($item['idempotency_key'])) {
            $idempotencyTag = 'idempotency:'.str_replace(['%', '_'], ['\\%', '\\_'], $item['idempotency_key']);
            $existing = $user->transactions()
                ->whereRaw("notes LIKE ? ESCAPE '\\'", ['%'.$idempotencyTag.'%'])
                ->first();

            if ($existing) {
                $existing->load(['category', 'account', 'tags']);

                if (in_array($existing->type, [TransactionType::TransferOut, TransactionType::TransferIn])) {
                    $linked = $existing->linked_transaction_id
                        ? $user->transactions()->with(['category', 'account', 'tags'])->find($existing->linked_transaction_id)
                        : null;

                    $out = $existing->type === TransactionType::TransferOut ? $existing : $linked;
                    $in = $existing->type === TransactionType::TransferIn ? $existing : $linked;

                    return [
                        'index' => $index,
                        'success' => true,
                        'idempotent' => true,
                        'message' => 'Transfer already exists (idempotent).',
                        'transfer_out' => $out ? (new TransactionResource($out))->resolve() : null,
                        'transfer_in' => $in ? (new TransactionResource($in))->resolve() : null,
                    ];
                }

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
            return match ($item['type']) {
                'expense' => ['index' => $index, ...$this->createExpense($user, $data)],
                'income' => ['index' => $index, ...$this->createIncome($user, $data)],
                'transfer' => ['index' => $index, ...$this->createTransfer($user, $data)],
            };
        } catch (\Throwable $e) {
            return [
                'index' => $index,
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function createExpense(mixed $user, array $item): array
    {
        if (empty($item['category_id'])) {
            return ['success' => false, 'message' => 'Category is required for expenses. Use GetCategoriesTool to find expense categories.'];
        }

        if (empty($item['account_id'])) {
            return ['success' => false, 'message' => 'Account is required for expenses. Use GetAccountsTool to find available accounts.'];
        }

        try {
            $transaction = app(CreateExpenseAction::class)->handle($user, $item);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        } catch (\InvalidArgumentException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $transaction->load(['category', 'account', 'tags']);

        return [
            'success' => true,
            'message' => 'Expense created successfully.',
            'transaction' => (new TransactionResource($transaction))->resolve(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function createIncome(mixed $user, array $item): array
    {
        if (empty($item['category_id'])) {
            return ['success' => false, 'message' => 'Category is required for income. Use GetCategoriesTool to find income categories.'];
        }

        if (empty($item['account_id'])) {
            return ['success' => false, 'message' => 'Account is required for income. Use GetAccountsTool to find available accounts.'];
        }

        try {
            $transaction = app(CreateIncomeAction::class)->handle($user, $item);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        } catch (\InvalidArgumentException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $transaction->load(['category', 'account', 'tags']);

        return [
            'success' => true,
            'message' => 'Income created successfully.',
            'transaction' => (new TransactionResource($transaction))->resolve(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function createTransfer(mixed $user, array $item): array
    {
        if (empty($item['origin_account_id'])) {
            return ['success' => false, 'message' => 'Origin account is required for transfers.'];
        }

        if (empty($item['destination_account_id'])) {
            return ['success' => false, 'message' => 'Destination account is required for transfers.'];
        }

        try {
            [$transferOut, $transferIn] = app(CreateTransferAction::class)->handle($user, $item);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        } catch (\InvalidArgumentException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $transferOut->load(['account', 'tags']);
        $transferIn->load(['account', 'tags']);

        return [
            'success' => true,
            'message' => 'Transfer created successfully.',
            'transfer_out' => (new TransactionResource($transferOut))->resolve(),
            'transfer_in' => (new TransactionResource($transferIn))->resolve(),
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
            'type' => $schema->string()
                ->description('Transaction type: expense, income, or transfer')
                ->enum(['expense', 'income', 'transfer'])
                ->required(),
            'amount' => $schema->number()
                ->description('Amount in major currency units (e.g., 572000 for 572,000 CLP)')
                ->required(),
            'description' => $schema->string()
                ->description('Transaction description (e.g., "Supermercado Líder")')
                ->required(),
            'category_id' => $schema->integer()
                ->description('Category ID. Required for expense and income. Use GetCategoriesTool to find categories.'),
            'tag_ids' => $schema->array()
                ->description('Optional array of tag IDs to attach to the transaction.'),
            'account_id' => $schema->integer()
                ->description('Account ID. Required for expense and income. Use GetAccountsTool to find accounts.'),
            'origin_account_id' => $schema->integer()
                ->description('Origin account ID. Required for transfers.'),
            'destination_account_id' => $schema->integer()
                ->description('Destination account ID. Required for transfers.'),
            'transaction_date' => $schema->string()
                ->description('Transaction date (YYYY-MM-DD). Defaults to today.'),
            'notes' => $schema->string()
                ->description('Optional notes for the transaction.'),
            'exclude_from_budget' => $schema->boolean()
                ->description('Exclude this expense from budget calculations (default: false).'),
            'idempotency_key' => $schema->string()
                ->description('Optional idempotency key to prevent duplicate transactions on retry.'),
        ]);

        return [
            'transactions' => $schema->array()
                ->description('Array of transactions to create (1–100 items). Each follows the same rules as CreateTransactionTool.')
                ->min(1)
                ->max(100)
                ->items($transactionItem)
                ->required(),
        ];
    }
}
