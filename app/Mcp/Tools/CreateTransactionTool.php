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

class CreateTransactionTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Create a new transaction (expense, income, or transfer).

        **For expenses:**
        - Requires: account_id, category_id, amount, description
        - Optionally: tag_ids, exclude_from_budget

        **For income:**
        - Requires: account_id, category_id (income type), amount, description

        **For transfers:**
        - Requires: origin_account_id, destination_account_id, amount, description
        - Creates linked transfer_out + transfer_in transactions

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
            'type' => ['required', 'string', 'in:expense,income,transfer'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer'],
            'account_id' => ['nullable', 'integer'],
            'origin_account_id' => ['nullable', 'integer'],
            'destination_account_id' => ['nullable', 'integer'],
            'transaction_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'exclude_from_budget' => ['nullable', 'boolean'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ], [
            'type.required' => 'Transaction type is required (expense, income, or transfer).',
            'type.in' => 'Transaction type must be expense, income, or transfer.',
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
                $existing->load(['category', 'account', 'tags']);

                // For transfers, return both legs
                if (in_array($existing->type, [TransactionType::TransferOut, TransactionType::TransferIn])) {
                    $linked = $existing->linked_transaction_id
                        ? $user->transactions()->with(['category', 'account', 'tags'])->find($existing->linked_transaction_id)
                        : null;

                    $out = $existing->type === TransactionType::TransferOut ? $existing : $linked;
                    $in = $existing->type === TransactionType::TransferIn ? $existing : $linked;

                    return Response::text(json_encode([
                        'success' => true,
                        'message' => 'Transfer already exists (idempotent).',
                        'transfer_out' => $out ? (new TransactionResource($out))->resolve() : null,
                        'transfer_in' => $in ? (new TransactionResource($in))->resolve() : null,
                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                }

                return Response::text(json_encode([
                    'success' => true,
                    'message' => 'Transaction already exists (idempotent).',
                    'transaction' => (new TransactionResource($existing))->resolve(),
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        }

        $data = $validated;
        $data['notes'] = $this->buildNotes($validated);

        $type = $validated['type'];

        return match ($type) {
            'expense' => $this->createExpense($user, $data),
            'income' => $this->createIncome($user, $data),
            'transfer' => $this->createTransfer($user, $data),
        };
    }

    private function createExpense(mixed $user, array $data): Response
    {
        if (empty($data['category_id'])) {
            return Response::error('Category is required for expenses. Use GetCategoriesTool to find expense categories.');
        }

        if (empty($data['account_id'])) {
            return Response::error('Account is required for expenses. Use GetAccountsTool to find available accounts.');
        }

        try {
            $transaction = app(CreateExpenseAction::class)->handle($user, $data);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return Response::error($e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return Response::error($e->getMessage());
        }

        $transaction->load(['category', 'account', 'tags']);

        return Response::text(json_encode([
            'success' => true,
            'message' => 'Expense created successfully.',
            'transaction' => (new TransactionResource($transaction))->resolve(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function createIncome(mixed $user, array $data): Response
    {
        if (empty($data['category_id'])) {
            return Response::error('Category is required for income. Use GetCategoriesTool to find income categories.');
        }

        if (empty($data['account_id'])) {
            return Response::error('Account is required for income. Use GetAccountsTool to find available accounts.');
        }

        try {
            $transaction = app(CreateIncomeAction::class)->handle($user, $data);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return Response::error($e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return Response::error($e->getMessage());
        }

        $transaction->load(['category', 'account', 'tags']);

        return Response::text(json_encode([
            'success' => true,
            'message' => 'Income created successfully.',
            'transaction' => (new TransactionResource($transaction))->resolve(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function createTransfer(mixed $user, array $data): Response
    {
        if (empty($data['origin_account_id'])) {
            return Response::error('Origin account is required for transfers.');
        }

        if (empty($data['destination_account_id'])) {
            return Response::error('Destination account is required for transfers.');
        }

        try {
            [$transferOut, $transferIn] = app(CreateTransferAction::class)->handle($user, $data);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return Response::error($e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return Response::error($e->getMessage());
        }

        $transferOut->load(['account', 'tags']);
        $transferIn->load(['account', 'tags']);

        return Response::text(json_encode([
            'success' => true,
            'message' => 'Transfer created successfully.',
            'transfer_out' => (new TransactionResource($transferOut))->resolve(),
            'transfer_in' => (new TransactionResource($transferIn))->resolve(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
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
                ->description('Optional idempotency key to prevent duplicate transactions from repeated submissions.'),
        ];
    }
}
