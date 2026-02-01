<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetPaymentMethodsTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Get all payment methods (credit cards, debit cards, cash, etc.).
        For credit cards, includes current debt and available credit.
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

        $query = $user->paymentMethods()->with('linkedAccount');

        // Filter by type if provided
        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        // Filter by active status (default: only active)
        $includeInactive = $request->get('include_inactive', false);
        if (! $includeInactive) {
            $query->where('is_active', true);
        }

        $paymentMethods = $query->orderBy('sort_order')->orderBy('name')->get();

        $result = $paymentMethods->map(fn ($pm) => [
            'id' => $pm->id,
            'uuid' => $pm->uuid,
            'name' => $pm->name,
            'type' => $pm->type->value,
            'type_label' => $pm->type->label(),
            'currency' => $pm->currency,
            'linked_account' => $pm->linkedAccount ? [
                'id' => $pm->linkedAccount->id,
                'uuid' => $pm->linkedAccount->uuid,
                'name' => $pm->linkedAccount->name,
            ] : null,
            'credit_limit' => $pm->credit_limit,
            'credit_limit_formatted' => $pm->credit_limit ? '$'.number_format($pm->credit_limit / 100, 0, ',', '.') : null,
            'current_debt' => $pm->isCreditCard() ? $pm->current_debt : null,
            'current_debt_formatted' => $pm->isCreditCard() ? '$'.number_format($pm->current_debt / 100, 0, ',', '.') : null,
            'available_credit' => $pm->available_credit,
            'available_credit_formatted' => $pm->available_credit !== null ? '$'.number_format($pm->available_credit / 100, 0, ',', '.') : null,
            'billing_cycle_day' => $pm->billing_cycle_day,
            'payment_due_day' => $pm->payment_due_day,
            'last_four_digits' => $pm->last_four_digits,
            'color' => $pm->color,
            'icon' => $pm->icon,
            'is_active' => $pm->is_active,
        ]);

        return Response::text(json_encode([
            'count' => $result->count(),
            'payment_methods' => $result,
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
                ->description('Filter by payment method type: credit_card, debit_card, prepaid_card, cash, transfer')
                ->enum(['credit_card', 'debit_card', 'prepaid_card', 'cash', 'transfer']),
            'include_inactive' => $schema->boolean()
                ->description('Include inactive payment methods (default: false)'),
        ];
    }
}
