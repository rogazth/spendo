<?php

namespace App\Mcp\Tools;

use App\Enums\CategoryType;
use App\Enums\PaymentMethodType;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class CreateTransactionTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Create a new transaction (expense, income, transfer, or settlement).

        **For expenses:**
        - Requires: payment_method_id, category_id, amount, description
        - If payment method is a credit card, the expense adds to credit card debt
        - If payment method is debit/cash/transfer, provide account_id (uses linked account if not provided)

        **For income:**
        - Requires: account_id, category_id (income type), amount, description

        **For transfers:**
        - Requires: origin_account_id, destination_account_id, amount, description
        - Creates linked transfer_out + transfer_in transactions

        **For settlements (credit card payments):**
        - Requires: account_id, payment_method_id (credit card), amount, description

        **Amount**: Provide in major currency units (e.g., 572000 for 572,000 CLP)
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
            'type' => ['required', 'string', 'in:expense,income,transfer,settlement'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer'],
            'payment_method_id' => ['nullable', 'integer'],
            'account_id' => ['nullable', 'integer'],
            'origin_account_id' => ['nullable', 'integer'],
            'destination_account_id' => ['nullable', 'integer'],
            'transaction_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'exclude_from_budget' => ['nullable', 'boolean'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ], [
            'type.required' => 'Transaction type is required (expense, income, transfer, or settlement).',
            'type.in' => 'Transaction type must be expense, income, transfer, or settlement.',
            'amount.required' => 'Amount is required in major currency units (e.g., 572000 for CLP).',
            'amount.gt' => 'Amount must be greater than zero.',
            'description.required' => 'Description is required.',
        ]);

        // Idempotency check
        if (! empty($validated['idempotency_key'])) {
            $idempotencyTag = 'idempotency:'.str_replace(['%', '_'], ['\%', '\_'], $validated['idempotency_key']);
            $existing = $user->transactions()
                ->whereRaw("notes LIKE ? ESCAPE '\\'", ['%'.$idempotencyTag.'%'])
                ->first();

            if ($existing) {
                $existing->load(['category', 'account', 'paymentMethod']);

                // For transfers, return both legs
                if (in_array($existing->type, [TransactionType::TransferOut, TransactionType::TransferIn])) {
                    $linked = $existing->linked_transaction_id
                        ? $user->transactions()->with(['category', 'account', 'paymentMethod'])->find($existing->linked_transaction_id)
                        : null;

                    $out = $existing->type === TransactionType::TransferOut ? $existing : $linked;
                    $in = $existing->type === TransactionType::TransferIn ? $existing : $linked;

                    return Response::text(json_encode([
                        'success' => true,
                        'message' => 'Transfer already exists (idempotent).',
                        'transfer_out' => $out ? $this->formatTransaction($out) : null,
                        'transfer_in' => $in ? $this->formatTransaction($in) : null,
                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                }

                return Response::text(json_encode([
                    'success' => true,
                    'message' => 'Transaction already exists (idempotent).',
                    'transaction' => $this->formatTransaction($existing),
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        }

        $type = $validated['type'];

        return match ($type) {
            'expense' => $this->createExpense($user, $validated),
            'income' => $this->createIncome($user, $validated),
            'transfer' => $this->createTransfer($user, $validated),
            'settlement' => $this->createSettlement($user, $validated),
        };
    }

    private function createExpense(mixed $user, array $validated): Response
    {
        if (empty($validated['category_id'])) {
            return Response::error('Category is required for expenses. Use GetCategoriesTool to find expense categories.');
        }

        if (empty($validated['payment_method_id'])) {
            return Response::error('Payment method is required for expenses. Use GetPaymentMethodsTool to find available methods.');
        }

        $category = $this->validateCategory($user, $validated['category_id'], CategoryType::Expense);
        if ($category instanceof Response) {
            return $category;
        }

        $paymentMethod = $user->paymentMethods()->find($validated['payment_method_id']);
        if (! $paymentMethod) {
            return Response::error('Payment method not found.');
        }

        $accountId = null;
        if ($paymentMethod->type !== PaymentMethodType::CreditCard) {
            if (! empty($validated['account_id'])) {
                $account = $user->accounts()->find($validated['account_id']);
                if (! $account) {
                    return Response::error('Account not found.');
                }
                $accountId = $account->id;
            } elseif ($paymentMethod->linked_account_id) {
                $accountId = $paymentMethod->linked_account_id;
            } else {
                return Response::error('Account is required for this payment method type.');
            }
        }

        // Derive currency from account or user's default
        $currency = 'CLP';
        if ($accountId) {
            $resolvedAccount = $user->accounts()->find($accountId);
            $currency = $resolvedAccount?->currency ?? 'CLP';
        }

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'type' => TransactionType::Expense,
            'account_id' => $accountId,
            'payment_method_id' => $paymentMethod->id,
            'category_id' => $category->id,
            'amount' => $validated['amount'],
            'currency' => $currency,
            'description' => $validated['description'],
            'notes' => $this->buildNotes($validated),
            'exclude_from_budget' => $validated['exclude_from_budget'] ?? false,
            'transaction_date' => $validated['transaction_date'] ?? now(),
        ]);

        $transaction->load(['category', 'account', 'paymentMethod']);

        return Response::text(json_encode([
            'success' => true,
            'message' => 'Expense created successfully.',
            'transaction' => $this->formatTransaction($transaction),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function createIncome(mixed $user, array $validated): Response
    {
        if (empty($validated['category_id'])) {
            return Response::error('Category is required for income. Use GetCategoriesTool to find income categories.');
        }

        if (empty($validated['account_id'])) {
            return Response::error('Account is required for income. Use GetAccountsTool to find available accounts.');
        }

        $category = $this->validateCategory($user, $validated['category_id'], CategoryType::Income);
        if ($category instanceof Response) {
            return $category;
        }

        $account = $user->accounts()->find($validated['account_id']);
        if (! $account) {
            return Response::error('Account not found.');
        }

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'type' => TransactionType::Income,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => $validated['amount'],
            'currency' => $account->currency ?? 'CLP',
            'description' => $validated['description'],
            'notes' => $this->buildNotes($validated),
            'transaction_date' => $validated['transaction_date'] ?? now(),
        ]);

        $transaction->load(['category', 'account']);

        return Response::text(json_encode([
            'success' => true,
            'message' => 'Income created successfully.',
            'transaction' => $this->formatTransaction($transaction),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function createTransfer(mixed $user, array $validated): Response
    {
        if (empty($validated['origin_account_id'])) {
            return Response::error('Origin account is required for transfers.');
        }

        if (empty($validated['destination_account_id'])) {
            return Response::error('Destination account is required for transfers.');
        }

        if ($validated['origin_account_id'] === $validated['destination_account_id']) {
            return Response::error('Origin and destination accounts must be different.');
        }

        $originAccount = $user->accounts()->find($validated['origin_account_id']);
        if (! $originAccount) {
            return Response::error('Origin account not found.');
        }

        $destinationAccount = $user->accounts()->find($validated['destination_account_id']);
        if (! $destinationAccount) {
            return Response::error('Destination account not found.');
        }

        // Find or use system transfer category
        $transferCategory = Category::where('is_system', true)
            ->where('type', CategoryType::System)
            ->where('name', 'Transferencia')
            ->first();

        $result = DB::transaction(function () use ($user, $validated, $originAccount, $destinationAccount, $transferCategory) {
            $transferOut = Transaction::create([
                'user_id' => $user->id,
                'type' => TransactionType::TransferOut,
                'account_id' => $originAccount->id,
                'category_id' => $transferCategory?->id,
                'amount' => $validated['amount'],
                'currency' => $originAccount->currency ?? 'CLP',
                'description' => $validated['description'],
                'notes' => $this->buildNotes($validated),
                'exclude_from_budget' => true,
                'transaction_date' => $validated['transaction_date'] ?? now(),
            ]);

            $transferIn = Transaction::create([
                'user_id' => $user->id,
                'type' => TransactionType::TransferIn,
                'account_id' => $destinationAccount->id,
                'category_id' => $transferCategory?->id,
                'linked_transaction_id' => $transferOut->id,
                'amount' => $validated['amount'],
                'currency' => $destinationAccount->currency ?? 'CLP',
                'description' => $validated['description'],
                'notes' => $this->buildNotes($validated),
                'exclude_from_budget' => true,
                'transaction_date' => $validated['transaction_date'] ?? now(),
            ]);

            $transferOut->update(['linked_transaction_id' => $transferIn->id]);

            return [$transferOut, $transferIn];
        });

        [$transferOut, $transferIn] = $result;
        $transferOut->load(['account']);
        $transferIn->load(['account']);

        return Response::text(json_encode([
            'success' => true,
            'message' => 'Transfer created successfully.',
            'transfer_out' => $this->formatTransaction($transferOut),
            'transfer_in' => $this->formatTransaction($transferIn),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function createSettlement(mixed $user, array $validated): Response
    {
        if (empty($validated['account_id'])) {
            return Response::error('Account is required for settlements (the account paying the credit card).');
        }

        if (empty($validated['payment_method_id'])) {
            return Response::error('Payment method (credit card) is required for settlements.');
        }

        $account = $user->accounts()->find($validated['account_id']);
        if (! $account) {
            return Response::error('Account not found.');
        }

        $paymentMethod = $user->paymentMethods()->find($validated['payment_method_id']);
        if (! $paymentMethod) {
            return Response::error('Payment method not found.');
        }

        if (! $paymentMethod->isCreditCard()) {
            return Response::error('Settlement is only for credit card payments. The payment method must be a credit card.');
        }

        // Find or use system settlement category
        $settlementCategory = Category::where('is_system', true)
            ->where('type', CategoryType::System)
            ->where('name', 'Liquidación TDC')
            ->first();

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'type' => TransactionType::Settlement,
            'account_id' => $account->id,
            'payment_method_id' => $paymentMethod->id,
            'category_id' => $settlementCategory?->id,
            'amount' => $validated['amount'],
            'currency' => $account->currency ?? 'CLP',
            'description' => $validated['description'],
            'notes' => $this->buildNotes($validated),
            'exclude_from_budget' => true,
            'transaction_date' => $validated['transaction_date'] ?? now(),
        ]);

        $transaction->load(['account', 'paymentMethod']);

        return Response::text(json_encode([
            'success' => true,
            'message' => 'Settlement created successfully. Credit card debt reduced.',
            'transaction' => $this->formatTransaction($transaction),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
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
            'payment_method' => $transaction->paymentMethod?->name,
        ];
    }

    private function validateCategory(mixed $user, int $categoryId, CategoryType $expectedType): Category|Response
    {
        $category = Category::where(function ($q) use ($user) {
            $q->whereNull('user_id')
                ->orWhere('user_id', $user->id);
        })->find($categoryId);

        if (! $category) {
            return Response::error('Category not found or not accessible.');
        }

        if ($category->type !== $expectedType) {
            return Response::error("For {$expectedType->value} transactions, use a {$expectedType->value} category.");
        }

        return $category;
    }

    private function buildNotes(array $validated): ?string
    {
        $notes = $validated['notes'] ?? '';

        if (! empty($validated['idempotency_key'])) {
            $idempotencyTag = 'idempotency:'.$validated['idempotency_key'];
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
        return [
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
            'payment_method_id' => $schema->integer()
                ->description('Payment method ID. Required for expense and settlement. Use GetPaymentMethodsTool to find methods.'),
            'account_id' => $schema->integer()
                ->description('Account ID. Required for income and settlement. For expenses with non-credit card, optional (uses linked account).'),
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
                ->description('Optional idempotency key to prevent duplicate transactions from repeated submissions.'),
        ];
    }
}
