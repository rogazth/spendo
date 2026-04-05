<?php

namespace App\Mcp\Tools;

use App\Actions\Accounts\UpdateAccountAction;
use App\Http\Resources\AccountResource;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class UpdateAccountTool extends Tool
{
    protected string $description = <<<'MARKDOWN'
        Update an existing account. Only provided fields will be updated.
        Use GetAccountsTool first to find the account ID.
    MARKDOWN;

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user) {
            return Response::error('User not authenticated.');
        }

        $validated = $request->validate([
            'account_id' => ['required', 'integer'],
            'name' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'emoji' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'include_in_budget' => ['nullable', 'boolean'],
        ], [
            'account_id.required' => 'Account ID is required. Use GetAccountsTool to find accounts.',
        ]);

        $account = $user->accounts()->find($validated['account_id']);

        if (! $account) {
            return Response::error('Account not found.');
        }

        if (isset($validated['name']) && $validated['name'] !== $account->name) {
            $duplicate = $user->accounts()
                ->where('name', $validated['name'])
                ->where('id', '!=', $account->id)
                ->first();

            if ($duplicate) {
                return Response::error("An account named \"{$validated['name']}\" already exists.");
            }
        }

        $data = array_filter(
            array_intersect_key($validated, array_flip(['name', 'color', 'emoji', 'is_active', 'is_default', 'include_in_budget'])),
            fn ($value) => $value !== null
        );

        $account = app(UpdateAccountAction::class)->handle($account, $user, $data);

        return Response::text(json_encode([
            'success' => true,
            'message' => "Account \"{$account->name}\" updated successfully.",
            'account' => (new AccountResource($account))->resolve(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'account_id' => $schema->integer()
                ->description('The ID of the account to update')
                ->required(),
            'name' => $schema->string()
                ->description('New account name'),
            'color' => $schema->string()
                ->description('New hex color code'),
            'emoji' => $schema->string()
                ->description('New emoji (e.g., 🏦, 💳, 💵)'),
            'is_active' => $schema->boolean()
                ->description('Set active/inactive status'),
            'is_default' => $schema->boolean()
                ->description('Set as default account'),
            'include_in_budget' => $schema->boolean()
                ->description('Include this account balance in budget calculations. Set to false for savings or investment accounts'),
        ];
    }
}
