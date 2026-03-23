<?php

namespace App\Mcp\Tools;

use App\Actions\Categories\CreateCategoryAction;
use App\Enums\CategoryType;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class CreateCategoryTool extends Tool
{
    protected string $description = <<<'MARKDOWN'
        Create a new expense or income category.

        **Types**: expense, income (cannot create system categories)
        **Subcategories**: Provide parent_id to create a subcategory. The parent must be a top-level, non-system category.
        **Subcategory type**: Automatically inherited from the parent category.
    MARKDOWN;

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user) {
            return Response::error('User not authenticated.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'in:expense,income'],
            'parent_id' => ['nullable', 'integer'],
            'icon' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ], [
            'name.required' => 'Category name is required.',
            'type.in' => 'Category type must be expense or income.',
        ]);

        // Check for duplicate name
        $existing = Category::where('name', $validated['name'])
            ->where(function ($q) use ($user) {
                $q->whereNull('user_id')
                    ->orWhere('user_id', $user->id);
            })
            ->first();

        if ($existing) {
            return Response::error("A category named \"{$validated['name']}\" already exists.");
        }

        $categoryType = isset($validated['type']) ? CategoryType::from($validated['type']) : null;

        if (! empty($validated['parent_id'])) {
            $parentCategory = Category::where(function ($q) use ($user) {
                $q->whereNull('user_id')
                    ->orWhere('user_id', $user->id);
            })
                ->whereNull('parent_id')
                ->where('is_system', false)
                ->find($validated['parent_id']);

            if (! $parentCategory) {
                return Response::error('Parent category not found or not valid (must be a top-level, non-system category).');
            }
        } elseif ($categoryType === null) {
            return Response::error('Category type (expense or income) is required when not creating a subcategory.');
        }

        $category = app(CreateCategoryAction::class)->handle($user, [
            'name' => $validated['name'],
            'type' => $categoryType?->value,
            'parent_id' => $validated['parent_id'] ?? null,
            'icon' => $validated['icon'] ?? 'tag',
            'color' => $validated['color'] ?? '#6B7280',
            'sort_order' => 0,
        ]);

        return Response::text(json_encode([
            'success' => true,
            'message' => "Category \"{$category->name}\" created successfully.",
            'category' => (new CategoryResource($category))->resolve(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->description('Category name (e.g., "Alimentación", "Sueldo")')
                ->required(),
            'type' => $schema->string()
                ->description('Category type. Required for top-level categories. Inherited from parent for subcategories.')
                ->enum(['expense', 'income']),
            'parent_id' => $schema->integer()
                ->description('Parent category ID for subcategories. Use GetCategoriesTool to find parent categories.'),
            'icon' => $schema->string()
                ->description('Icon name (default: tag)'),
            'color' => $schema->string()
                ->description('Hex color code (e.g., #10B981)'),
        ];
    }
}
