<?php

namespace App\Mcp\Tools;

use App\Enums\InstrumentType;
use App\Models\Currency;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class CreateInstrumentTool extends Tool
{
    protected string $description = <<<'MARKDOWN'
        Create a new financial instrument (bank account, credit card, cash, etc.).

        **Types**: checking, savings, cash, investment, credit_card, prepaid_card
        **Credit cards**: Optionally provide credit_limit, billing_cycle_day, payment_due_day
        **Credit limit**: In major currency units (e.g., 2000000 for 2,000,000 CLP)
        **Currency**: 3-letter code (e.g., CLP). Defaults to CLP.
    MARKDOWN;

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user) {
            return Response::error('User not authenticated.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:checking,savings,cash,investment,credit_card,prepaid_card'],
            'currency' => ['nullable', 'string', 'size:3'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'billing_cycle_day' => ['nullable', 'integer', 'min:1', 'max:28'],
            'payment_due_day' => ['nullable', 'integer', 'min:1', 'max:28'],
            'last_four_digits' => ['nullable', 'string', 'size:4'],
            'color' => ['nullable', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icon' => ['nullable', 'string', 'max:50'],
            'is_default' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'Instrument name is required.',
            'name.max' => 'Instrument name cannot exceed 255 characters.',
            'type.required' => 'Instrument type is required (checking, savings, cash, investment, credit_card, prepaid_card).',
            'type.in' => 'Instrument type must be checking, savings, cash, investment, credit_card, or prepaid_card.',
            'currency.size' => 'Currency must be a 3-letter code.',
        ]);

        $currency = $validated['currency'] ?? 'CLP';
        if (! in_array($currency, Currency::codes())) {
            return Response::error('Invalid currency code. Use a valid 3-letter currency code (e.g., CLP).');
        }

        $type = InstrumentType::from($validated['type']);

        if (! empty($validated['credit_limit']) && ! $type->isCreditCard()) {
            return Response::error('credit_limit is only valid for credit_card or prepaid_card types.');
        }

        $existing = $user->instruments()
            ->where('name', $validated['name'])
            ->first();

        if ($existing) {
            return Response::error("An instrument named \"{$validated['name']}\" already exists.");
        }

        if (! empty($validated['is_default'])) {
            $user->instruments()->update(['is_default' => false]);
        }

        $instrument = $user->instruments()->create([
            'name' => $validated['name'],
            'type' => $type,
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
            'message' => "Instrument \"{$instrument->name}\" created successfully.",
            'instrument' => [
                'id' => $instrument->id,
                'uuid' => $instrument->uuid,
                'name' => $instrument->name,
                'type' => $instrument->type->value,
                'type_label' => $instrument->type->label(),
                'currency' => $instrument->currency,
                'is_credit_card' => $instrument->isCreditCard(),
                'credit_limit' => $instrument->credit_limit,
                'is_active' => $instrument->is_active,
                'is_default' => $instrument->is_default,
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->description('Instrument name (e.g., "Cuenta Corriente BCI", "Visa BCI")')
                ->required(),
            'type' => $schema->string()
                ->description('Instrument type')
                ->enum(['checking', 'savings', 'cash', 'investment', 'credit_card', 'prepaid_card'])
                ->required(),
            'currency' => $schema->string()
                ->description('3-letter currency code (default: CLP)'),
            'credit_limit' => $schema->number()
                ->description('Credit limit in major currency units (for credit_card/prepaid_card). E.g., 2000000 for 2M CLP.'),
            'billing_cycle_day' => $schema->integer()
                ->description('Day of month for billing cycle (1-28, for credit cards)'),
            'payment_due_day' => $schema->integer()
                ->description('Day of month for payment due (1-28, for credit cards)'),
            'last_four_digits' => $schema->string()
                ->description('Last 4 digits of card number'),
            'color' => $schema->string()
                ->description('Hex color code (e.g., #3B82F6)'),
            'icon' => $schema->string()
                ->description('Icon name'),
            'is_default' => $schema->boolean()
                ->description('Set as default instrument'),
        ];
    }
}
