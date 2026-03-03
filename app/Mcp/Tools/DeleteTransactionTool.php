<?php

namespace App\Mcp\Tools;

use App\Models\Transaction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class DeleteTransactionTool extends Tool
{
    protected string $description = <<<'MARKDOWN'
        Delete a single transaction permanently.

        If the transaction is a transfer (transfer_out or transfer_in), the linked
        counterpart leg will also be deleted automatically.

        Use GetTransactionsTool first to find the transaction ID.
        This action cannot be undone.
    MARKDOWN;

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user) {
            return Response::error('User not authenticated.');
        }

        $validated = $request->validate([
            'transaction_id' => ['required', 'integer'],
        ]);

        $transaction = $user->transactions()->find($validated['transaction_id']);

        if (! $transaction) {
            return Response::error('Transaction not found.');
        }

        $description = $transaction->description ?? 'no description';
        $type = $transaction->type->value;
        $deletedLinked = false;

        DB::transaction(function () use ($transaction, &$deletedLinked) {
            if ($transaction->linked_transaction_id !== null) {
                Transaction::query()->find($transaction->linked_transaction_id)?->delete();
                $deletedLinked = true;
            }

            $transaction->delete();
        });

        $message = "Transaction \"{$description}\" deleted.";
        if ($deletedLinked) {
            $message .= ' The linked transfer leg was also deleted.';
        }

        return Response::text(json_encode([
            'success' => true,
            'message' => $message,
            'deleted' => [
                'description' => $description,
                'type' => $type,
                'linked_leg_deleted' => $deletedLinked,
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'transaction_id' => $schema->integer()
                ->description('The ID of the transaction to delete. Transfer transactions will also delete the linked leg.')
                ->required(),
        ];
    }
}
