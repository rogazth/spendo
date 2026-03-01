<?php

namespace App\Mcp\Tools;

use App\Models\Category;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class UpdateCategoryTool extends Tool
{
    protected string $description = <<<'MARKDOWN'
        Update an existing category. Only provided fields will be updated.
        System categories cannot be modified. Use GetCategoriesTool to find category IDs.
    MARKDOWN;

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user) {
            return Response::error('User not authenticated.');
        }

        $validated = $request->validate([
            'category_id' => ['required', 'integer'],
            'name' => ['nullable', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ], [
            'category_id.required' => 'Category ID is required. Use GetCategoriesTool to find categories.',
        ]);

        $category = $user->categories()->find($validated['category_id']);

        if (! $category) {
            return Response::error('Category not found. Only user-created categories can be modified.');
        }

        if ($category->is_system) {
            return Response::error('System categories cannot be modified.');
        }

        if (isset($validated['name']) && $validated['name'] !== $category->name) {
            $duplicate = Category::where('name', $validated['name'])
                ->where('id', '!=', $category->id)
                ->where(function ($q) use ($user) {
                    $q->whereNull('user_id')
                        ->orWhere('user_id', $user->id);
                })
                ->first();

            if ($duplicate) {
                return Response::error("A category named \"{$validated['name']}\" already exists.");
            }
        }

        $updates = array_filter([
            'name' => $validated['name'] ?? null,
            'icon' => $validated['icon'] ?? null,
            'color' => $validated['color'] ?? null,
        ], fn ($value) => $value !== null);

        $category->update($updates);

        return Response::text(json_encode([
            'success' => true,
            'message' => "Category \"{$category->name}\" updated successfully.",
            'category' => [
                'id' => $category->id,
                'uuid' => $category->uuid,
                'name' => $category->name,
                'full_name' => $category->full_name,
                'type' => $category->type->value,
                'parent_id' => $category->parent_id,
                'icon' => $category->icon,
                'color' => $category->color,
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'category_id' => $schema->integer()
                ->description('The ID of the category to update')
                ->required(),
            'name' => $schema->string()
                ->description('New category name'),
            'icon' => $schema->string()
                ->description('New icon name'),
            'color' => $schema->string()
                ->description('New hex color code'),
        ];
    }
}
