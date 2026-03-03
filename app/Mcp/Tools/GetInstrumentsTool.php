<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetInstrumentsTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Get all financial instruments (bank accounts, credit cards, cash, etc.) with their balances.
        For credit cards, includes current debt and available credit.
        Results are grouped by currency.
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

        $query = $user->instruments();

        // Filter by active status (default: only active)
        $includeInactive = $request->get('include_inactive', false);
        if (! $includeInactive) {
            $query->where('is_active', true);
        }

        $instruments = $query->orderBy('sort_order')->orderBy('name')->get();

        $result = $instruments->map(fn ($i) => [
            'id' => $i->id,
            'uuid' => $i->uuid,
            'name' => $i->name,
            'type' => $i->type->value,
            'type_label' => $i->type->label(),
            'currency' => $i->currency,
            'is_credit_card' => $i->isCreditCard(),
            'current_debt' => $i->isCreditCard() ? $i->current_debt : null,
            'current_debt_formatted' => $i->isCreditCard() ? '$'.number_format($i->current_debt, 0, ',', '.') : null,
            'current_balance' => $i->current_balance,
            'current_balance_formatted' => '$'.number_format($i->current_balance, 0, ',', '.'),
            'available_credit' => $i->available_credit,
            'available_credit_formatted' => $i->available_credit !== null ? '$'.number_format($i->available_credit, 0, ',', '.') : null,
            'credit_limit' => $i->credit_limit,
            'credit_limit_formatted' => $i->credit_limit !== null ? '$'.number_format($i->credit_limit, 0, ',', '.') : null,
            'billing_cycle_day' => $i->billing_cycle_day,
            'payment_due_day' => $i->payment_due_day,
            'last_four_digits' => $i->last_four_digits,
            'color' => $i->color,
            'icon' => $i->icon,
            'is_active' => $i->is_active,
            'is_default' => $i->is_default,
        ]);

        $grouped = $result->groupBy('currency')->map(fn ($items) => $items->values());

        return Response::text(json_encode([
            'count' => $result->count(),
            'instruments_by_currency' => $grouped,
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
            'include_inactive' => $schema->boolean()
                ->description('Include inactive instruments (default: false)'),
        ];
    }
}
