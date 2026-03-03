<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class DeleteInstrumentTool extends Tool
{
    protected string $description = <<<'MARKDOWN'
        Delete an instrument (credit card, debit card, etc.) permanently.

        **WARNING**: Deleting an instrument will also permanently delete ALL transactions
        where this instrument was used as the primary instrument. This action cannot be undone.

        Use GetInstrumentsTool first to find the instrument ID and confirm with the user
        before proceeding.
    MARKDOWN;

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user) {
            return Response::error('User not authenticated.');
        }

        $validated = $request->validate([
            'instrument_id' => ['required', 'integer'],
        ]);

        $instrument = $user->instruments()->find($validated['instrument_id']);

        if (! $instrument) {
            return Response::error('Instrument not found.');
        }

        $transactionCount = $instrument->transactions()->count();
        $instrumentName = $instrument->name;

        $instrument->forceDelete();

        return Response::text(json_encode([
            'success' => true,
            'message' => "Instrument \"{$instrumentName}\" deleted along with {$transactionCount} transaction(s).",
            'deleted' => [
                'instrument_name' => $instrumentName,
                'transactions_deleted' => $transactionCount,
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'instrument_id' => $schema->integer()
                ->description('The ID of the instrument to delete. WARNING: all transactions using this instrument will also be deleted.')
                ->required(),
        ];
    }
}
