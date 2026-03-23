<?php

namespace App\Mcp\Tools;

use App\Actions\Tags\UpdateTagAction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class UpdateTagTool extends Tool
{
    protected string $description = <<<'MARKDOWN'
        Update an existing tag. Only provided fields will be updated.
        Use GetTagsTool to find tag IDs.
    MARKDOWN;

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user) {
            return Response::error('User not authenticated.');
        }

        $validated = $request->validate([
            'tag_id' => ['required', 'integer'],
            'name' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ], [
            'tag_id.required' => 'Tag ID is required. Use GetTagsTool to find tags.',
            'name.max' => 'Tag name must not exceed 50 characters.',
            'color.regex' => 'Color must be a valid hex code (e.g., #FF5733).',
        ]);

        $tag = $user->tags()->find($validated['tag_id']);

        if (! $tag) {
            return Response::error('Tag not found.');
        }

        $data = array_filter([
            'name' => $validated['name'] ?? null,
            'color' => $validated['color'] ?? null,
        ], fn ($v) => $v !== null);

        try {
            $tag = app(UpdateTagAction::class)->handle($tag, $data);
        } catch (\InvalidArgumentException $e) {
            return Response::error($e->getMessage());
        }

        return Response::text(json_encode([
            'success' => true,
            'message' => "Tag \"{$tag->name}\" updated successfully.",
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
            'tag_id' => $schema->integer()
                ->description('The ID of the tag to update')
                ->required(),
            'name' => $schema->string()
                ->description('New tag name'),
            'color' => $schema->string()
                ->description('New hex color code (e.g., #10B981)'),
        ];
    }
}
