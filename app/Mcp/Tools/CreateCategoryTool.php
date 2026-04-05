<?php

namespace App\Mcp\Tools;

use App\Actions\Categories\CreateCategoryAction;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class CreateCategoryTool extends Tool
{
    protected string $description = <<<'MARKDOWN'
        Create a new category for classifying transactions.

        **Any category can be used for any transaction type** (expense, income, transfer).
        **Subcategories**: Provide parent_id to create a subcategory. The parent must be a top-level, non-system category.
        **Note**: System categories cannot be created via this tool.
    MARKDOWN;

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user) {
            return Response::error('User not authenticated.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer'],
            'emoji' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ], [
            'name.required' => 'Category name is required.',
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
        }

        $category = app(CreateCategoryAction::class)->handle($user, [
            'name' => $validated['name'],
            'parent_id' => $validated['parent_id'] ?? null,
            'emoji' => $validated['emoji'] ?? '🏷️',
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
                ->description('Category name (e.g., "Alimentación", "Sueldo", "Reembolsos")')
                ->required(),
            'parent_id' => $schema->integer()
                ->description('Parent category ID for subcategories. Use GetCategoriesTool to find parent categories.'),
            'emoji' => $schema->string()
                ->description('Emoji (e.g., 🛒, 🚗, 💡). Defaults to 🏷️'),
            'color' => $schema->string()
                ->description('Hex color code (e.g., #10B981)'),
        ];
    }
}
