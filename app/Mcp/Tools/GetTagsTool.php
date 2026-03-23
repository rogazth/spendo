<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetTagsTool extends Tool
{
    protected string $description = <<<'MARKDOWN'
        Get all tags for the authenticated user.
        Tags are used to label and organize transactions beyond categories.
    MARKDOWN;

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user) {
            return Response::error('User not authenticated.');
        }

        $tags = $user->tags()->orderBy('name')->get();

        return Response::text(json_encode([
            'total_count' => $tags->count(),
            'tags' => $tags->map(fn ($tag) => [
                'id' => $tag->id,
                'uuid' => $tag->uuid,
                'name' => $tag->name,
                'color' => $tag->color,
            ])->values(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
