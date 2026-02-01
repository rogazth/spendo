<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetAccountsTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Get all user accounts (bank accounts, savings, cash, investments) with their current balances.
        Optionally filter by account type or active status.
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

        $query = $user->accounts();

        // Filter by type if provided
        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        // Filter by active status (default: only active)
        $includeInactive = $request->get('include_inactive', false);
        if (! $includeInactive) {
            $query->where('is_active', true);
        }

        $accounts = $query->orderBy('sort_order')->orderBy('name')->get();

        $result = $accounts->map(fn ($account) => [
            'id' => $account->id,
            'uuid' => $account->uuid,
            'name' => $account->name,
            'type' => $account->type->value,
            'type_label' => $account->type->label(),
            'currency' => $account->currency,
            'current_balance' => $account->current_balance,
            'current_balance_formatted' => '$'.number_format($account->current_balance / 100, 0, ',', '.'),
            'initial_balance' => $account->initial_balance,
            'color' => $account->color,
            'icon' => $account->icon,
            'is_active' => $account->is_active,
        ]);

        return Response::text(json_encode([
            'count' => $result->count(),
            'accounts' => $result,
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
                ->description('Filter by account type: checking, savings, cash, investment')
                ->enum(['checking', 'savings', 'cash', 'investment']),
            'include_inactive' => $schema->boolean()
                ->description('Include inactive accounts (default: false)'),
        ];
    }
}
