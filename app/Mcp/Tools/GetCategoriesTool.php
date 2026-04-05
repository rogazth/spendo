<?php

namespace App\Mcp\Tools;

use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetCategoriesTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Get all categories organized hierarchically.
        Any category can be used for any transaction type (expense, income, transfer).
        System categories (Balance Inicial, Transferencia, etc.) are shown separately and cannot be modified.
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

        $categories = Category::query()
            ->where(function ($q) use ($user) {
                $q->whereNull('user_id')
                    ->orWhere('user_id', $user->id);
            })
            ->with(['children' => function ($q) use ($user) {
                $q->where(function ($q) use ($user) {
                    $q->whereNull('user_id')
                        ->orWhere('user_id', $user->id);
                });
            }])
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $result = collect(CategoryResource::collection($categories)->resolve());

        $grouped = [
            'categories' => $result->where('is_system', false)->values(),
            'system' => $result->where('is_system', true)->values(),
        ];

        return Response::text(json_encode([
            'total_count' => $result->count(),
            'categories_by_group' => $grouped,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
