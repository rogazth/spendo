<?php

namespace App\Mcp\Tools;

use App\Enums\CategoryType;
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
        Categories are used to classify transactions as expenses, income, or system categories.
        System categories (Balance Inicial, Transferencia, etc.) cannot be modified.
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

        $query = Category::query()
            ->where(function ($q) use ($user) {
                $q->whereNull('user_id')
                    ->orWhere('user_id', $user->id);
            })
            ->with('children')
            ->whereNull('parent_id');

        // Filter by type if provided
        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        $categories = $query
            ->orderBy('type')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $result = $categories->map(fn ($category) => $this->formatCategory($category));

        // Group by type for easier reading
        $grouped = [
            'expense' => $result->where('type', CategoryType::Expense->value)->values(),
            'income' => $result->where('type', CategoryType::Income->value)->values(),
            'system' => $result->where('type', CategoryType::System->value)->values(),
        ];

        return Response::text(json_encode([
            'total_count' => $result->count(),
            'categories_by_type' => $grouped,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array<string, mixed>
     */
    private function formatCategory(Category $category): array
    {
        return [
            'id' => $category->id,
            'uuid' => $category->uuid,
            'name' => $category->name,
            'full_name' => $category->full_name,
            'type' => $category->type->value,
            'type_label' => $category->type->label(),
            'icon' => $category->icon,
            'color' => $category->color,
            'is_system' => $category->is_system,
            'children' => $category->relationLoaded('children')
                ? $category->children->map(fn ($c) => $this->formatCategory($c))->values()
                : [],
        ];
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()
                ->description('Filter by category type: expense, income, system')
                ->enum(['expense', 'income', 'system']),
        ];
    }
}
