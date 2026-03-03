<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class UpdateInstrumentTool extends Tool
{
    protected string $description = <<<'MARKDOWN'
        Update an existing instrument. Only provided fields will be updated.
        Use GetInstrumentsTool first to find the instrument ID.
    MARKDOWN;

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user) {
            return Response::error('User not authenticated.');
        }

        $validated = $request->validate([
            'instrument_id' => ['required', 'integer'],
            'name' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icon' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
        ], [
            'instrument_id.required' => 'Instrument ID is required. Use GetInstrumentsTool to find instruments.',
        ]);

        $instrument = $user->instruments()->find($validated['instrument_id']);

        if (! $instrument) {
            return Response::error('Instrument not found.');
        }

        if (isset($validated['name']) && $validated['name'] !== $instrument->name) {
            $duplicate = $user->instruments()
                ->where('name', $validated['name'])
                ->where('id', '!=', $instrument->id)
                ->first();

            if ($duplicate) {
                return Response::error("An instrument named \"{$validated['name']}\" already exists.");
            }
        }

        $updates = array_filter([
            'name' => $validated['name'] ?? null,
            'color' => $validated['color'] ?? null,
            'icon' => $validated['icon'] ?? null,
            'is_active' => $validated['is_active'] ?? null,
            'is_default' => $validated['is_default'] ?? null,
        ], fn ($value) => $value !== null);

        if (! empty($updates['is_default']) && $updates['is_default']) {
            $user->instruments()->where('id', '!=', $instrument->id)->update(['is_default' => false]);
        }

        $instrument->update($updates);

        return Response::text(json_encode([
            'success' => true,
            'message' => "Instrument \"{$instrument->name}\" updated successfully.",
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
            'instrument_id' => $schema->integer()
                ->description('The ID of the instrument to update')
                ->required(),
            'name' => $schema->string()
                ->description('New instrument name'),
            'color' => $schema->string()
                ->description('New hex color code'),
            'icon' => $schema->string()
                ->description('New icon name'),
            'is_active' => $schema->boolean()
                ->description('Set active/inactive status'),
            'is_default' => $schema->boolean()
                ->description('Set as default instrument'),
        ];
    }
}
