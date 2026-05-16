<?php

namespace App\Mcp\Tools;

use App\Actions\Transactions\CreateTransactionAction;
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
        Create a new transaction (income or expense).

        - Requires: account_id, category_id, amount, description
        - Optionally: tag_ids, transaction_date, notes, exclude_from_budget, idempotency_key

        **Amount sign determines direction:**
        - Negative amount → expense (e.g., -58900 for a 58,900 CLP grocery purchase)
        - Positive amount → income (e.g., 1500000 for a 1,500,000 CLP paycheck)
        - Do not send a `type` field; transactions no longer have one.

        Use the CreateTransferTool for transfers between accounts.

        **Amount**: Provide in major currency units (e.g., 572000 for 572,000 CLP).
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
            'type' => ['prohibited'],
            'amount' => ['required', 'numeric', 'not_in:0'],
            'description' => ['required', 'string', 'max:255'],
            'account_id' => ['required', 'integer'],
            'category_id' => ['required', 'integer'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer'],
            'transaction_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'exclude_from_budget' => ['nullable', 'boolean'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ], [
            'type.prohibited' => 'Transaction type is no longer used. Use a negative amount for expenses and a positive amount for income.',
            'amount.required' => 'Amount is required. Use a negative value for expenses and a positive value for income.',
            'amount.not_in' => 'Amount cannot be zero.',
            'description.required' => 'Description is required.',
            'account_id.required' => 'Account ID is required. Use GetAccountsTool to find accounts.',
            'category_id.required' => 'Category ID is required. Use GetCategoriesTool to find categories.',
        ]);

        if (! empty($validated['idempotency_key'])) {
            $idempotencyTag = 'idempotency:'.str_replace(['%', '_'], ['\%', '\_'], $validated['idempotency_key']);
            $existing = $user->transactions()
                ->whereRaw("notes LIKE ? ESCAPE '\\'", ['%'.$idempotencyTag.'%'])
                ->first();

            if ($existing) {
                $existing->load(['category', 'account', 'tags']);

                return Response::text(json_encode([
                    'success' => true,
                    'message' => 'Transaction already exists (idempotent).',
                    'transaction' => (new TransactionResource($existing))->resolve(),
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        }

        $data = $validated;
        $data['notes'] = $this->buildNotes($validated);

        try {
            $transaction = app(CreateTransactionAction::class)->handle($user, $data);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return Response::error($e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return Response::error($e->getMessage());
        }

        $transaction->load(['category', 'account', 'tags']);

        return Response::text(json_encode([
            'success' => true,
            'message' => 'Transaction created successfully.',
            'transaction' => (new TransactionResource($transaction))->resolve(),
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
            'amount' => $schema->number()
                ->description('Signed amount in major currency units. Negative = expense, positive = income (e.g., -58900 for a 58,900 CLP grocery purchase). Do not send a type field.')
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
                ->description('Optional idempotency key to prevent duplicate transactions from repeated submissions.'),
        ];
    }
}
