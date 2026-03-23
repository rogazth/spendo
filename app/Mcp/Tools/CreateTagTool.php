<?php

namespace App\Mcp\Tools;

use App\Actions\Tags\CreateTagAction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class CreateTagTool extends Tool
{
    protected string $description = <<<'MARKDOWN'
        Create a new tag for the authenticated user.
        Tags can optionally have a hex color to visually distinguish them.
        Tag names must be unique per user.
    MARKDOWN;

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user) {
            return Response::error('User not authenticated.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ], [
            'name.required' => 'Tag name is required.',
            'name.max' => 'Tag name must not exceed 50 characters.',
            'color.regex' => 'Color must be a valid hex code (e.g., #FF5733).',
        ]);

        try {
            $tag = app(CreateTagAction::class)->handle($user, $validated);
        } catch (\InvalidArgumentException $e) {
            return Response::error($e->getMessage());
        }

        return Response::text(json_encode([
            'success' => true,
            'message' => "Tag \"{$tag->name}\" created successfully.",
            'tag' => [
                'id' => $tag->id,
                'uuid' => $tag->uuid,
                'name' => $tag->name,
                'color' => $tag->color,
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->description('Tag name (e.g., "Viajes", "Trabajo")')
                ->required(),
            'color' => $schema->string()
                ->description('Hex color code (e.g., #10B981)'),
        ];
    }
}
