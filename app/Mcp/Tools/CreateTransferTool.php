<?php

namespace App\Mcp\Tools;

use App\Actions\Transactions\CreateTransferAction;
use App\Enums\TransactionType;
use App\Http\Resources\TransactionResource;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class CreateTransferTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Create a transfer between two accounts.

        Creates two linked transactions:
        - Origin account: outflow (negative signed amount)
        - Destination account: inflow (positive signed amount)

        Both rows are linked via `linked_transaction_id`.

        **Amount**: Provide a positive value in major currency units (e.g., 100000 for 100,000 CLP).
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
            'origin_account_id' => ['required', 'integer'],
            'destination_account_id' => ['required', 'integer', 'different:origin_account_id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:255'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer'],
            'transaction_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ], [
            'origin_account_id.required' => 'Origin account ID is required.',
            'destination_account_id.required' => 'Destination account ID is required.',
            'destination_account_id.different' => 'Destination account must be different from origin account.',
            'amount.required' => 'Amount is required in major currency units (e.g., 100000 for 100,000 CLP).',
            'amount.gt' => 'Amount must be greater than zero.',
        ]);

        if (! empty($validated['idempotency_key'])) {
            $idempotencyTag = 'idempotency:'.str_replace(['%', '_'], ['\%', '\_'], $validated['idempotency_key']);
            $existing = $user->transactions()
                ->where('type', TransactionType::Transfer)
                ->whereRaw("notes LIKE ? ESCAPE '\\'", ['%'.$idempotencyTag.'%'])
                ->first();

            if ($existing) {
                $existing->load(['category', 'account', 'tags']);
                $linked = $existing->linked_transaction_id
                    ? $user->transactions()->with(['category', 'account', 'tags'])->find($existing->linked_transaction_id)
                    : null;

                $out = $existing->amount < 0 ? $existing : $linked;
                $in = $existing->amount > 0 ? $existing : $linked;

                return Response::text(json_encode([
                    'success' => true,
                    'message' => 'Transfer already exists (idempotent).',
                    'transfer_out' => $out ? (new TransactionResource($out))->resolve() : null,
                    'transfer_in' => $in ? (new TransactionResource($in))->resolve() : null,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        }

        $data = $validated;
        $data['notes'] = $this->buildNotes($validated);

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
            'origin_account_id' => $schema->integer()
                ->description('Origin account ID. Use GetAccountsTool to find accounts.')
                ->required(),
            'destination_account_id' => $schema->integer()
                ->description('Destination account ID. Must differ from origin.')
                ->required(),
            'amount' => $schema->number()
                ->description('Positive amount in major currency units (e.g., 100000 for 100,000 CLP).')
                ->required(),
            'description' => $schema->string()
                ->description('Optional transfer description.'),
            'tag_ids' => $schema->array()
                ->description('Optional array of tag IDs to attach to the outgoing leg of the transfer.'),
            'transaction_date' => $schema->string()
                ->description('Transfer date (YYYY-MM-DD). Defaults to today.'),
            'notes' => $schema->string()
                ->description('Optional notes for the transfer.'),
            'idempotency_key' => $schema->string()
                ->description('Optional idempotency key to prevent duplicate transfers from repeated submissions.'),
        ];
    }
}
