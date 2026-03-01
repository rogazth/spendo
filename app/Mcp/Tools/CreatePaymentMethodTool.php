<?php

namespace App\Mcp\Tools;

use App\Enums\PaymentMethodType;
use App\Models\Currency;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class CreatePaymentMethodTool extends Tool
{
    protected string $description = <<<'MARKDOWN'
        Create a new payment method.

        **Types**: credit_card, debit_card, prepaid_card, cash, transfer
        **Credit cards**: Provide credit_limit, billing_cycle_day, payment_due_day
        **Non-credit cards**: Should have a linked_account_id (the account money comes from)
        **Amounts**: credit_limit in major currency units (e.g., 2000000 for 2,000,000 CLP)
    MARKDOWN;

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user) {
            return Response::error('User not authenticated.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:credit_card,debit_card,prepaid_card,cash,transfer'],
            'linked_account_id' => ['nullable', 'integer'],
            'currency' => ['nullable', 'string', 'size:3'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'billing_cycle_day' => ['nullable', 'integer', 'min:1', 'max:28'],
            'payment_due_day' => ['nullable', 'integer', 'min:1', 'max:28'],
            'last_four_digits' => ['nullable', 'string', 'size:4'],
            'color' => ['nullable', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icon' => ['nullable', 'string', 'max:50'],
            'is_default' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'Payment method name is required.',
            'type.required' => 'Payment method type is required.',
            'type.in' => 'Type must be credit_card, debit_card, prepaid_card, cash, or transfer.',
        ]);

        $existing = $user->paymentMethods()
            ->where('name', $validated['name'])
            ->first();

        if ($existing) {
            return Response::error("A payment method named \"{$validated['name']}\" already exists.");
        }

        if (! empty($validated['linked_account_id'])) {
            $linkedAccount = $user->accounts()->find($validated['linked_account_id']);
            if (! $linkedAccount) {
                return Response::error('Linked account not found. Use GetAccountsTool to find valid accounts.');
            }
        }

        $currency = $validated['currency'] ?? 'CLP';
        if (! in_array($currency, Currency::codes())) {
            return Response::error('Invalid currency code.');
        }

        if (! empty($validated['is_default'])) {
            $user->paymentMethods()->update(['is_default' => false]);
        }

        $paymentMethod = $user->paymentMethods()->create([
            'name' => $validated['name'],
            'type' => PaymentMethodType::from($validated['type']),
            'linked_account_id' => $validated['linked_account_id'] ?? null,
            'currency' => $currency,
            'credit_limit' => $validated['credit_limit'] ?? null,
            'billing_cycle_day' => $validated['billing_cycle_day'] ?? null,
            'payment_due_day' => $validated['payment_due_day'] ?? null,
            'last_four_digits' => $validated['last_four_digits'] ?? null,
            'color' => $validated['color'] ?? '#6B7280',
            'icon' => $validated['icon'] ?? null,
            'is_active' => true,
            'is_default' => $validated['is_default'] ?? false,
            'sort_order' => 0,
        ]);

        return Response::text(json_encode([
            'success' => true,
            'message' => "Payment method \"{$paymentMethod->name}\" created successfully.",
            'payment_method' => [
                'id' => $paymentMethod->id,
                'uuid' => $paymentMethod->uuid,
                'name' => $paymentMethod->name,
                'type' => $paymentMethod->type->value,
                'currency' => $paymentMethod->currency,
                'credit_limit' => $paymentMethod->credit_limit,
                'is_active' => $paymentMethod->is_active,
                'is_default' => $paymentMethod->is_default,
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->description('Payment method name (e.g., "Visa BCI", "Efectivo")')
                ->required(),
            'type' => $schema->string()
                ->description('Payment method type')
                ->enum(['credit_card', 'debit_card', 'prepaid_card', 'cash', 'transfer'])
                ->required(),
            'linked_account_id' => $schema->integer()
                ->description('Account ID to link (required for debit/cash/transfer types). Use GetAccountsTool to find accounts.'),
            'currency' => $schema->string()
                ->description('3-letter currency code (default: CLP)'),
            'credit_limit' => $schema->number()
                ->description('Credit limit in major currency units (for credit cards). E.g., 2000000 for 2M CLP.'),
            'billing_cycle_day' => $schema->integer()
                ->description('Day of month for billing cycle (1-28, for credit cards)'),
            'payment_due_day' => $schema->integer()
                ->description('Day of month for payment due (1-28, for credit cards)'),
            'last_four_digits' => $schema->string()
                ->description('Last 4 digits of card number'),
            'color' => $schema->string()
                ->description('Hex color code'),
            'icon' => $schema->string()
                ->description('Icon name'),
            'is_default' => $schema->boolean()
                ->description('Set as default payment method'),
        ];
    }
}
