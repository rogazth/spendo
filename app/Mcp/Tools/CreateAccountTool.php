<?php

namespace App\Mcp\Tools;

use App\Actions\Accounts\CreateAccountAction;
use App\Http\Resources\AccountResource;
use App\Models\Currency;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class CreateAccountTool extends Tool
{
    protected string $description = <<<'MARKDOWN'
        Create a new bank account.

        **Currency**: 3-letter code (e.g., CLP). Use CLP for Chilean pesos.
        **Initial balance**: Optional. If provided, creates an income transaction to set the starting balance.
    MARKDOWN;

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user) {
            return Response::error('User not authenticated.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3'],
            'initial_balance' => ['nullable', 'numeric', 'min:0'],
            'color' => ['nullable', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'emoji' => ['nullable', 'string', 'max:50'],
            'is_default' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'Account name is required.',
            'name.max' => 'Account name cannot exceed 255 characters.',
            'currency.required' => 'Currency code is required (e.g., CLP).',
            'currency.size' => 'Currency must be a 3-letter code.',
        ]);

        if (! in_array($validated['currency'], Currency::codes())) {
            return Response::error('Invalid currency code. Use a valid 3-letter currency code (e.g., CLP).');
        }

        $existing = $user->accounts()
            ->where('name', $validated['name'])
            ->first();

        if ($existing) {
            return Response::error("An account named \"{$validated['name']}\" already exists.");
        }

        $account = app(CreateAccountAction::class)->handle($user, $validated);

        return Response::text(json_encode([
            'success' => true,
            'message' => "Account \"{$account->name}\" created successfully.",
            'account' => (new AccountResource($account))->resolve(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->description('Account name (e.g., "Cuenta Corriente BCI")')
                ->required(),
            'currency' => $schema->string()
                ->description('3-letter currency code (e.g., CLP)')
                ->required(),
            'initial_balance' => $schema->number()
                ->description('Initial balance in major currency units (e.g., 500000 for 500,000 CLP)'),
            'color' => $schema->string()
                ->description('Hex color code (e.g., #3B82F6)'),
            'emoji' => $schema->string()
                ->description('Emoji (e.g., 🏦, 💳, 💵)'),
            'is_default' => $schema->boolean()
                ->description('Set as default account'),
        ];
    }
}
