<?php

namespace App\Mcp\Tools;

use App\Enums\CategoryType;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
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

        **For expenses:** Requires account_id, category_id, amount, description. Optionally instrument_id.
        **For income:** Requires account_id, category_id (income type), amount, description
        **For transfers:** Requires origin_account_id, destination_account_id, amount, description
        **For settlements:** Requires instrument_id (CC), from_instrument_id (bank), amount, description

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
            'transactions.*.type' => ['required', 'string', 'in:expense,income,transfer,settlement'],
            'transactions.*.amount' => ['required', 'numeric', 'gt:0'],
            'transactions.*.description' => ['required', 'string', 'max:255'],
            'transactions.*.category_id' => ['nullable', 'integer'],
            'transactions.*.instrument_id' => ['nullable', 'integer'],
            'transactions.*.from_instrument_id' => ['nullable', 'integer'],
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
            'transactions.*.type.in' => 'Transaction type must be expense, income, transfer, or settlement.',
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
                $existing->load(['category', 'account', 'instrument', 'fromInstrument']);

                if (in_array($existing->type, [TransactionType::TransferOut, TransactionType::TransferIn])) {
                    $linked = $existing->linked_transaction_id
                        ? $user->transactions()->with(['category', 'account', 'instrument', 'fromInstrument'])->find($existing->linked_transaction_id)
                        : null;

                    $out = $existing->type === TransactionType::TransferOut ? $existing : $linked;
                    $in = $existing->type === TransactionType::TransferIn ? $existing : $linked;

                    return [
                        'index' => $index,
                        'success' => true,
                        'idempotent' => true,
                        'message' => 'Transfer already exists (idempotent).',
                        'transfer_out' => $out ? $this->formatTransaction($out) : null,
                        'transfer_in' => $in ? $this->formatTransaction($in) : null,
                    ];
                }

                return [
                    'index' => $index,
                    'success' => true,
                    'idempotent' => true,
                    'message' => 'Transaction already exists (idempotent).',
                    'transaction' => $this->formatTransaction($existing),
                ];
            }
        }

        try {
            return match ($item['type']) {
                'expense' => ['index' => $index, ...$this->createExpense($user, $item)],
                'income' => ['index' => $index, ...$this->createIncome($user, $item)],
                'transfer' => ['index' => $index, ...$this->createTransfer($user, $item)],
                'settlement' => ['index' => $index, ...$this->createSettlement($user, $item)],
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

        $category = $this->validateCategory($user, $item['category_id'], CategoryType::Expense);
        if (is_string($category)) {
            return ['success' => false, 'message' => $category];
        }

        $account = $user->accounts()->find($item['account_id']);
        if (! $account) {
            return ['success' => false, 'message' => 'Account not found.'];
        }

        $instrumentId = null;
        if (! empty($item['instrument_id'])) {
            $instrument = $user->instruments()->find($item['instrument_id']);
            if (! $instrument) {
                return ['success' => false, 'message' => 'Instrument not found.'];
            }
            $instrumentId = $instrument->id;
        }

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'type' => TransactionType::Expense,
            'account_id' => $account->id,
            'instrument_id' => $instrumentId,
            'category_id' => $category->id,
            'amount' => $item['amount'],
            'currency' => $account->currency ?? 'CLP',
            'description' => $item['description'],
            'notes' => $this->buildNotes($item),
            'exclude_from_budget' => $item['exclude_from_budget'] ?? false,
            'transaction_date' => $item['transaction_date'] ?? now(),
        ]);

        $transaction->load(['category', 'account', 'instrument']);

        return [
            'success' => true,
            'message' => 'Expense created successfully.',
            'transaction' => $this->formatTransaction($transaction),
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

        $category = $this->validateCategory($user, $item['category_id'], CategoryType::Income);
        if (is_string($category)) {
            return ['success' => false, 'message' => $category];
        }

        $account = $user->accounts()->find($item['account_id']);
        if (! $account) {
            return ['success' => false, 'message' => 'Account not found.'];
        }

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'type' => TransactionType::Income,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => $item['amount'],
            'currency' => $account->currency ?? 'CLP',
            'description' => $item['description'],
            'notes' => $this->buildNotes($item),
            'transaction_date' => $item['transaction_date'] ?? now(),
        ]);

        $transaction->load(['category', 'account']);

        return [
            'success' => true,
            'message' => 'Income created successfully.',
            'transaction' => $this->formatTransaction($transaction),
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

        if ($item['origin_account_id'] === $item['destination_account_id']) {
            return ['success' => false, 'message' => 'Origin and destination accounts must be different.'];
        }

        $originAccount = $user->accounts()->find($item['origin_account_id']);
        if (! $originAccount) {
            return ['success' => false, 'message' => 'Origin account not found.'];
        }

        $destinationAccount = $user->accounts()->find($item['destination_account_id']);
        if (! $destinationAccount) {
            return ['success' => false, 'message' => 'Destination account not found.'];
        }

        $transferCategory = Category::where('is_system', true)
            ->where('type', CategoryType::System)
            ->where('name', 'Transferencia')
            ->first();

        [$transferOut, $transferIn] = DB::transaction(function () use ($user, $item, $originAccount, $destinationAccount, $transferCategory) {
            $transferOut = Transaction::create([
                'user_id' => $user->id,
                'type' => TransactionType::TransferOut,
                'account_id' => $originAccount->id,
                'category_id' => $transferCategory?->id,
                'amount' => $item['amount'],
                'currency' => $originAccount->currency ?? 'CLP',
                'description' => $item['description'],
                'notes' => $this->buildNotes($item),
                'exclude_from_budget' => true,
                'transaction_date' => $item['transaction_date'] ?? now(),
            ]);

            $transferIn = Transaction::create([
                'user_id' => $user->id,
                'type' => TransactionType::TransferIn,
                'account_id' => $destinationAccount->id,
                'category_id' => $transferCategory?->id,
                'linked_transaction_id' => $transferOut->id,
                'amount' => $item['amount'],
                'currency' => $destinationAccount->currency ?? 'CLP',
                'description' => $item['description'],
                'notes' => $this->buildNotes($item),
                'exclude_from_budget' => true,
                'transaction_date' => $item['transaction_date'] ?? now(),
            ]);

            $transferOut->update(['linked_transaction_id' => $transferIn->id]);

            return [$transferOut, $transferIn];
        });

        $transferOut->load(['account']);
        $transferIn->load(['account']);

        return [
            'success' => true,
            'message' => 'Transfer created successfully.',
            'transfer_out' => $this->formatTransaction($transferOut),
            'transfer_in' => $this->formatTransaction($transferIn),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function createSettlement(mixed $user, array $item): array
    {
        if (empty($item['instrument_id'])) {
            return ['success' => false, 'message' => 'instrument_id (credit card being paid) is required for settlements.'];
        }

        if (empty($item['from_instrument_id'])) {
            return ['success' => false, 'message' => 'from_instrument_id (bank instrument making the payment) is required for settlements.'];
        }

        $creditCard = $user->instruments()->find($item['instrument_id']);
        if (! $creditCard) {
            return ['success' => false, 'message' => 'Credit card instrument not found.'];
        }

        if (! $creditCard->isCreditCard()) {
            return ['success' => false, 'message' => 'Settlement is only for credit card payments. The instrument_id must be a credit card or prepaid card.'];
        }

        $fromInstrument = $user->instruments()->find($item['from_instrument_id']);
        if (! $fromInstrument) {
            return ['success' => false, 'message' => 'From instrument not found.'];
        }

        $settlementCategory = Category::where('is_system', true)
            ->where('type', CategoryType::System)
            ->where('name', 'Liquidación TDC')
            ->first();

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'type' => TransactionType::Settlement,
            'account_id' => null,
            'instrument_id' => $creditCard->id,
            'from_instrument_id' => $fromInstrument->id,
            'category_id' => $settlementCategory?->id,
            'amount' => $item['amount'],
            'currency' => $creditCard->currency ?? 'CLP',
            'description' => $item['description'],
            'notes' => $this->buildNotes($item),
            'exclude_from_budget' => true,
            'transaction_date' => $item['transaction_date'] ?? now(),
        ]);

        $transaction->load(['instrument', 'fromInstrument']);

        return [
            'success' => true,
            'message' => 'Settlement created successfully. Credit card debt reduced.',
            'transaction' => $this->formatTransaction($transaction),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatTransaction(Transaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'uuid' => $transaction->uuid,
            'type' => $transaction->type->value,
            'type_label' => $transaction->type->label(),
            'amount' => $transaction->amount,
            'amount_formatted' => $transaction->formatted_amount,
            'currency' => $transaction->currency,
            'description' => $transaction->description,
            'transaction_date' => $transaction->transaction_date->format('Y-m-d'),
            'category' => $transaction->category?->full_name,
            'account' => $transaction->account?->name,
            'instrument' => $transaction->instrument?->name,
            'from_instrument' => $transaction->fromInstrument?->name,
        ];
    }

    private function validateCategory(mixed $user, int $categoryId, CategoryType $expectedType): Category|string
    {
        $category = Category::where(function ($q) use ($user) {
            $q->whereNull('user_id')
                ->orWhere('user_id', $user->id);
        })->find($categoryId);

        if (! $category) {
            return 'Category not found or not accessible.';
        }

        if ($category->type !== $expectedType) {
            return "For {$expectedType->value} transactions, use a {$expectedType->value} category.";
        }

        return $category;
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
                ->description('Transaction type: expense, income, transfer, or settlement')
                ->enum(['expense', 'income', 'transfer', 'settlement'])
                ->required(),
            'amount' => $schema->number()
                ->description('Amount in major currency units (e.g., 572000 for 572,000 CLP)')
                ->required(),
            'description' => $schema->string()
                ->description('Transaction description (e.g., "Supermercado Líder")')
                ->required(),
            'category_id' => $schema->integer()
                ->description('Category ID. Required for expense and income. Use GetCategoriesTool to find categories.'),
            'instrument_id' => $schema->integer()
                ->description('Instrument ID. Optional for expenses. Required for settlements (CC being paid). Use GetInstrumentsTool.'),
            'from_instrument_id' => $schema->integer()
                ->description('Instrument ID of bank paying the CC. Required for settlements. Use GetInstrumentsTool.'),
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
