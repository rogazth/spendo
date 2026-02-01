<?php

namespace App\Mcp\Tools;

use App\Enums\CategoryType;
use App\Enums\PaymentMethodType;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class CreateTransactionTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Create a new transaction (expense, income, etc.).

        **For expenses:**
        - Requires: payment_method_id, category_id, amount, description
        - If payment method is a credit card, the expense adds to credit card debt
        - If payment method is debit/cash/transfer, provide account_id (uses linked account if not provided)

        **For income:**
        - Requires: account_id, category_id (income type), amount, description

        **Amount**: Provide in centavos (e.g., $15.50 = 1550)
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

        // Validate required fields
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:expense,income'],
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer'],
            'payment_method_id' => ['required_if:type,expense', 'nullable', 'integer'],
            'account_id' => ['required_if:type,income', 'nullable', 'integer'],
            'transaction_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ], [
            'type.required' => 'Transaction type is required (expense or income).',
            'type.in' => 'Transaction type must be expense or income.',
            'amount.required' => 'Amount is required.',
            'amount.integer' => 'Amount must be an integer in centavos.',
            'amount.min' => 'Amount must be at least 1 centavo.',
            'description.required' => 'Description is required.',
            'category_id.required' => 'Category is required.',
            'payment_method_id.required_if' => 'Payment method is required for expenses.',
            'account_id.required_if' => 'Account is required for income.',
        ]);

        $type = TransactionType::from($validated['type']);

        // Validate category exists and belongs to user (or is system)
        $category = Category::where(function ($q) use ($user) {
            $q->whereNull('user_id')
                ->orWhere('user_id', $user->id);
        })->find($validated['category_id']);

        if (! $category) {
            return Response::error('Category not found or not accessible.');
        }

        // Validate category type matches transaction type
        if ($type === TransactionType::Expense && $category->type !== CategoryType::Expense) {
            return Response::error('For expense transactions, use an expense category.');
        }

        if ($type === TransactionType::Income && $category->type !== CategoryType::Income) {
            return Response::error('For income transactions, use an income category.');
        }

        $accountId = null;
        $paymentMethodId = null;

        if ($type === TransactionType::Expense) {
            // Validate payment method
            $paymentMethod = $user->paymentMethods()->find($validated['payment_method_id']);
            if (! $paymentMethod) {
                return Response::error('Payment method not found.');
            }
            $paymentMethodId = $paymentMethod->id;

            // For non-credit card payments, determine account
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
        } else {
            // Income - validate account
            $account = $user->accounts()->find($validated['account_id']);
            if (! $account) {
                return Response::error('Account not found.');
            }
            $accountId = $account->id;
        }

        // Create the transaction
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'type' => $type,
            'account_id' => $accountId,
            'payment_method_id' => $paymentMethodId,
            'category_id' => $category->id,
            'amount' => $validated['amount'],
            'currency' => 'CLP',
            'description' => $validated['description'],
            'notes' => $validated['notes'] ?? null,
            'transaction_date' => $validated['transaction_date'] ?? now(),
        ]);

        $transaction->load(['category', 'account', 'paymentMethod']);

        return Response::text(json_encode([
            'success' => true,
            'message' => 'Transaction created successfully.',
            'transaction' => [
                'id' => $transaction->id,
                'uuid' => $transaction->uuid,
                'type' => $transaction->type->value,
                'type_label' => $transaction->type->label(),
                'amount' => $transaction->amount,
                'amount_formatted' => $transaction->formatted_amount,
                'description' => $transaction->description,
                'transaction_date' => $transaction->transaction_date->format('Y-m-d'),
                'category' => $transaction->category->full_name,
                'account' => $transaction->account?->name,
                'payment_method' => $transaction->paymentMethod?->name,
            ],
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
                ->description('Transaction type: expense or income')
                ->enum(['expense', 'income'])
                ->required(),
            'amount' => $schema->integer()
                ->description('Amount in centavos (e.g., $15.50 = 1550)')
                ->required(),
            'description' => $schema->string()
                ->description('Transaction description (e.g., "Supermercado Líder")')
                ->required(),
            'category_id' => $schema->integer()
                ->description('Category ID. Use GetCategoriesTool to find available categories.')
                ->required(),
            'payment_method_id' => $schema->integer()
                ->description('Payment method ID. Required for expenses. Use GetPaymentMethodsTool to find available methods.'),
            'account_id' => $schema->integer()
                ->description('Account ID. Required for income. For expenses with non-credit card payment, optional (uses linked account if not provided).'),
            'transaction_date' => $schema->string()
                ->description('Transaction date (YYYY-MM-DD). Defaults to today.'),
            'notes' => $schema->string()
                ->description('Optional notes for the transaction'),
        ];
    }
}
