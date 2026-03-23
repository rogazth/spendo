<?php

namespace App\Mcp\Tools;

use App\Actions\Accounts\DeleteAccountAction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class DeleteAccountTool extends Tool
{
    protected string $description = <<<'MARKDOWN'
        Delete an account permanently.

        **WARNING**: Deleting an account will also permanently delete ALL transactions
        associated with that account. This action cannot be undone.

        Use GetAccountsTool first to find the account ID and confirm with the user
        before proceeding.
    MARKDOWN;

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user) {
            return Response::error('User not authenticated.');
        }

        $validated = $request->validate([
            'account_id' => ['required', 'integer'],
        ]);

        $account = $user->accounts()->find($validated['account_id']);

        if (! $account) {
            return Response::error('Account not found.');
        }

        $transactionCount = $account->transactions()->count();
        $accountName = $account->name;

        app(DeleteAccountAction::class)->handle($account);

        return Response::text(json_encode([
            'success' => true,
            'message' => "Account \"{$accountName}\" deleted along with {$transactionCount} transaction(s).",
            'deleted' => [
                'account_name' => $accountName,
                'transactions_deleted' => $transactionCount,
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'account_id' => $schema->integer()
                ->description('The ID of the account to delete. WARNING: all transactions for this account will also be deleted.')
                ->required(),
        ];
    }
}
