<?php

namespace App\Mcp\Tools;

use App\Enums\AccountType;
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
            'type' => ['nullable', 'string', 'in:checking,savings,cash,investment'],
            'color' => ['nullable', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icon' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
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

        $updates = array_filter([
            'name' => $validated['name'] ?? null,
            'type' => isset($validated['type']) ? AccountType::from($validated['type']) : null,
            'color' => $validated['color'] ?? null,
            'icon' => $validated['icon'] ?? null,
            'is_active' => $validated['is_active'] ?? null,
            'is_default' => $validated['is_default'] ?? null,
        ], fn ($value) => $value !== null);

        if (! empty($updates['is_default']) && $updates['is_default']) {
            $user->accounts()->where('id', '!=', $account->id)->update(['is_default' => false]);
        }

        $account->update($updates);

        return Response::text(json_encode([
            'success' => true,
            'message' => "Account \"{$account->name}\" updated successfully.",
            'account' => [
                'id' => $account->id,
                'uuid' => $account->uuid,
                'name' => $account->name,
                'type' => $account->type->value,
                'currency' => $account->currency,
                'current_balance' => $account->current_balance,
                'is_active' => $account->is_active,
                'is_default' => $account->is_default,
            ],
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
            'type' => $schema->string()
                ->description('New account type')
                ->enum(['checking', 'savings', 'cash', 'investment']),
            'color' => $schema->string()
                ->description('New hex color code'),
            'icon' => $schema->string()
                ->description('New icon name'),
            'is_active' => $schema->boolean()
                ->description('Set active/inactive status'),
            'is_default' => $schema->boolean()
                ->description('Set as default account'),
        ];
    }
}
