<?php

namespace App\Mcp\Tools;

use App\Models\Category;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class UpdateBudgetTool extends Tool
{
    protected string $description = <<<'MARKDOWN'
        Update an existing budget. Only provided fields will be updated.
        If items are provided, they replace all existing budget items entirely.
        Use GetBudgetsTool first to find budget IDs.
    MARKDOWN;

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user) {
            return Response::error('User not authenticated.');
        }

        $validated = $request->validate([
            'budget_id' => ['required', 'integer'],
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
            'items' => ['nullable', 'array', 'min:1'],
            'items.*.category_id' => ['required_with:items', 'integer'],
            'items.*.amount' => ['required_with:items', 'numeric', 'gt:0'],
        ], [
            'budget_id.required' => 'Budget ID is required. Use GetBudgetsTool to find budgets.',
        ]);

        $budget = $user->budgets()->find($validated['budget_id']);

        if (! $budget) {
            return Response::error('Budget not found.');
        }

        if (isset($validated['items'])) {
            // Validate categories before entering transaction
            $categoryIds = collect($validated['items'])->pluck('category_id')->unique()->values();

            // Check for duplicate category IDs in input
            $inputCategoryIds = collect($validated['items'])->pluck('category_id');
            if ($inputCategoryIds->count() !== $inputCategoryIds->unique()->count()) {
                return Response::error('Duplicate category IDs are not allowed in budget items.');
            }

            $categories = Category::whereIn('id', $categoryIds->all())
                ->where(function ($q) use ($user) {
                    $q->whereNull('user_id')
                        ->orWhere('user_id', $user->id);
                })
                ->where('type', 'expense')
                ->get(['id', 'parent_id']);

            if ($categories->count() !== $categoryIds->count()) {
                return Response::error('Some categories were not found or are not expense categories.');
            }

            // Check parent+child overlap
            $selectedIds = $categoryIds->flip();
            foreach ($categories as $category) {
                if ($category->parent_id !== null && $selectedIds->has($category->parent_id)) {
                    return Response::error('Cannot mix a parent category with its subcategories.');
                }
            }
        }

        DB::transaction(function () use ($budget, $validated) {
            $updates = array_filter([
                'name' => $validated['name'] ?? null,
                'description' => $validated['description'] ?? null,
                'is_active' => $validated['is_active'] ?? null,
            ], fn ($value) => $value !== null);

            if (! empty($updates)) {
                $budget->update($updates);
            }

            if (isset($validated['items'])) {
                $budget->items()->delete();
                foreach ($validated['items'] as $item) {
                    $budget->items()->create([
                        'category_id' => $item['category_id'],
                        'amount' => $item['amount'],
                    ]);
                }
            }
        });

        $budget->load('items.category');

        $items = $budget->items->map(fn ($item) => [
            'category_id' => $item->category_id,
            'category_name' => $item->category?->name ?? 'Unknown',
            'amount' => $item->amount,
        ]);

        return Response::text(json_encode([
            'success' => true,
            'message' => "Budget \"{$budget->name}\" updated successfully.",
            'budget' => [
                'id' => $budget->id,
                'uuid' => $budget->uuid,
                'name' => $budget->name,
                'currency' => $budget->currency,
                'frequency' => $budget->frequency,
                'is_active' => $budget->is_active,
                'total_budgeted' => $budget->total_budgeted,
                'items_count' => $items->count(),
                'items' => $items,
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'budget_id' => $schema->integer()
                ->description('The ID of the budget to update')
                ->required(),
            'name' => $schema->string()
                ->description('New budget name'),
            'description' => $schema->string()
                ->description('New description'),
            'is_active' => $schema->boolean()
                ->description('Set active/inactive status'),
            'items' => $schema->array()
                ->description('Replace all budget items. Array of {category_id, amount} objects.'),
        ];
    }
}
