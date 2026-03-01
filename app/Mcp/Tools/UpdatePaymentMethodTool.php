<?php

namespace App\Mcp\Tools;

use App\Enums\PaymentMethodType;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class UpdatePaymentMethodTool extends Tool
{
    protected string $description = <<<'MARKDOWN'
        Update an existing payment method. Only provided fields will be updated.
        Use GetPaymentMethodsTool first to find the payment method ID.
    MARKDOWN;

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user) {
            return Response::error('User not authenticated.');
        }

        $validated = $request->validate([
            'payment_method_id' => ['required', 'integer'],
            'name' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'in:credit_card,debit_card,prepaid_card,cash,transfer'],
            'linked_account_id' => ['nullable', 'integer'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'billing_cycle_day' => ['nullable', 'integer', 'min:1', 'max:28'],
            'payment_due_day' => ['nullable', 'integer', 'min:1', 'max:28'],
            'last_four_digits' => ['nullable', 'string', 'size:4'],
            'color' => ['nullable', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icon' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
        ], [
            'payment_method_id.required' => 'Payment method ID is required. Use GetPaymentMethodsTool to find payment methods.',
        ]);

        $paymentMethod = $user->paymentMethods()->find($validated['payment_method_id']);

        if (! $paymentMethod) {
            return Response::error('Payment method not found.');
        }

        if (isset($validated['name']) && $validated['name'] !== $paymentMethod->name) {
            $duplicate = $user->paymentMethods()
                ->where('name', $validated['name'])
                ->where('id', '!=', $paymentMethod->id)
                ->first();

            if ($duplicate) {
                return Response::error("A payment method named \"{$validated['name']}\" already exists.");
            }
        }

        if (! empty($validated['linked_account_id'])) {
            $linkedAccount = $user->accounts()->find($validated['linked_account_id']);
            if (! $linkedAccount) {
                return Response::error('Linked account not found.');
            }
        }

        $updates = array_filter([
            'name' => $validated['name'] ?? null,
            'type' => isset($validated['type']) ? PaymentMethodType::from($validated['type']) : null,
            'linked_account_id' => $validated['linked_account_id'] ?? null,
            'credit_limit' => $validated['credit_limit'] ?? null,
            'billing_cycle_day' => $validated['billing_cycle_day'] ?? null,
            'payment_due_day' => $validated['payment_due_day'] ?? null,
            'last_four_digits' => $validated['last_four_digits'] ?? null,
            'color' => $validated['color'] ?? null,
            'icon' => $validated['icon'] ?? null,
            'is_active' => $validated['is_active'] ?? null,
            'is_default' => $validated['is_default'] ?? null,
        ], fn ($value) => $value !== null);

        if (! empty($updates['is_default']) && $updates['is_default']) {
            $user->paymentMethods()->where('id', '!=', $paymentMethod->id)->update(['is_default' => false]);
        }

        $paymentMethod->update($updates);

        return Response::text(json_encode([
            'success' => true,
            'message' => "Payment method \"{$paymentMethod->name}\" updated successfully.",
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
            'payment_method_id' => $schema->integer()
                ->description('The ID of the payment method to update')
                ->required(),
            'name' => $schema->string()
                ->description('New name'),
            'type' => $schema->string()
                ->description('New type')
                ->enum(['credit_card', 'debit_card', 'prepaid_card', 'cash', 'transfer']),
            'linked_account_id' => $schema->integer()
                ->description('New linked account ID'),
            'credit_limit' => $schema->number()
                ->description('New credit limit in major currency units'),
            'billing_cycle_day' => $schema->integer()
                ->description('New billing cycle day (1-28)'),
            'payment_due_day' => $schema->integer()
                ->description('New payment due day (1-28)'),
            'last_four_digits' => $schema->string()
                ->description('New last 4 digits'),
            'color' => $schema->string()
                ->description('New hex color code'),
            'icon' => $schema->string()
                ->description('New icon name'),
            'is_active' => $schema->boolean()
                ->description('Set active/inactive status'),
            'is_default' => $schema->boolean()
                ->description('Set as default payment method'),
        ];
    }
}
