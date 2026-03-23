<?php

namespace App\Mcp\Tools;

use App\Actions\Tags\DeleteTagAction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class DeleteTagTool extends Tool
{
    protected string $description = <<<'MARKDOWN'
        Delete a tag permanently.
        The tag will be removed from all transactions it was attached to.
        Use GetTagsTool to find the tag ID before deleting.
    MARKDOWN;

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user) {
            return Response::error('User not authenticated.');
        }

        $validated = $request->validate([
            'tag_id' => ['required', 'integer'],
        ], [
            'tag_id.required' => 'Tag ID is required. Use GetTagsTool to find tags.',
        ]);

        $tag = $user->tags()->find($validated['tag_id']);

        if (! $tag) {
            return Response::error('Tag not found.');
        }

        $tagName = $tag->name;

        app(DeleteTagAction::class)->handle($tag);

        return Response::text(json_encode([
            'success' => true,
            'message' => "Tag \"{$tagName}\" deleted successfully.",
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'tag_id' => $schema->integer()
                ->description('The ID of the tag to delete')
                ->required(),
        ];
    }
}
