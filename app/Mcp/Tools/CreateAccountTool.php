<?php

namespace App\Mcp\Tools;

use App\Enums\TransactionType;
use App\Models\Currency;
use App\Models\Transaction;
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
            'icon' => ['nullable', 'string', 'max:50'],
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

        if (! empty($validated['is_default'])) {
            $user->accounts()->update(['is_default' => false]);
        }

        $account = $user->accounts()->create([
            'name' => $validated['name'],
            'currency' => $validated['currency'],
            'color' => $validated['color'] ?? '#6B7280',
            'icon' => $validated['icon'] ?? null,
            'is_active' => true,
            'is_default' => $validated['is_default'] ?? false,
            'sort_order' => 0,
        ]);

        if (! empty($validated['initial_balance']) && $validated['initial_balance'] > 0) {
            $systemCategory = \App\Models\Category::where('is_system', true)
                ->where('name', 'Balance Inicial')
                ->first();

            Transaction::create([
                'user_id' => $user->id,
                'type' => TransactionType::Income,
                'account_id' => $account->id,
                'category_id' => $systemCategory?->id,
                'amount' => $validated['initial_balance'],
                'currency' => $validated['currency'],
                'description' => 'Balance inicial',
                'transaction_date' => now(),
            ]);
        }

        return Response::text(json_encode([
            'success' => true,
            'message' => "Account \"{$account->name}\" created successfully.",
            'account' => [
                'id' => $account->id,
                'uuid' => $account->uuid,
                'name' => $account->name,
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
            'icon' => $schema->string()
                ->description('Icon name'),
            'is_default' => $schema->boolean()
                ->description('Set as default account'),
        ];
    }
}
