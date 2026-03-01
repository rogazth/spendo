<?php

namespace App\Mcp\Tools;

use App\Models\Category;
use App\Models\Currency;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class CreateBudgetTool extends Tool
{
    protected string $description = <<<'MARKDOWN'
        Create a new budget with category-level spending caps.

        **Frequency**: weekly, biweekly, monthly, bimonthly
        **Items**: Array of category_id + amount pairs defining spending caps per category
        **Amounts**: In major currency units (e.g., 572000 for 572,000 CLP)
        **Categories**: Must be expense-type categories. Cannot mix a parent and its children in the same budget.
        **Account scope**: Optionally scope to a specific account (must match budget currency).
    MARKDOWN;

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user) {
            return Response::error('User not authenticated.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'currency' => ['required', 'string', 'size:3'],
            'frequency' => ['required', 'string', 'in:weekly,biweekly,monthly,bimonthly'],
            'anchor_date' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:anchor_date'],
            'account_id' => ['nullable', 'integer'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.category_id' => ['required', 'integer'],
            'items.*.amount' => ['required', 'numeric', 'gt:0'],
        ], [
            'name.required' => 'Budget name is required.',
            'currency.required' => 'Currency is required (e.g., CLP).',
            'frequency.required' => 'Frequency is required (weekly, biweekly, monthly, bimonthly).',
            'frequency.in' => 'Frequency must be weekly, biweekly, monthly, or bimonthly.',
            'anchor_date.required' => 'Anchor date is required (YYYY-MM-DD). This is the start date of the first budget cycle.',
            'items.required' => 'At least one budget item (category + amount) is required.',
            'items.min' => 'At least one budget item is required.',
            'items.*.category_id.required' => 'Each item needs a category_id. Use GetCategoriesTool to find expense categories.',
            'items.*.amount.required' => 'Each item needs an amount in major currency units.',
            'items.*.amount.gt' => 'Each item amount must be greater than zero.',
        ]);

        if (! in_array($validated['currency'], Currency::codes())) {
            return Response::error('Invalid currency code.');
        }

        // Validate account ownership and currency match
        if (! empty($validated['account_id'])) {
            $account = $user->accounts()->find($validated['account_id']);
            if (! $account) {
                return Response::error('Account not found.');
            }
            if ($account->currency !== $validated['currency']) {
                return Response::error("Account currency ({$account->currency}) does not match budget currency ({$validated['currency']}).");
            }
        }

        // Validate categories
        $categoryIds = collect($validated['items'])->pluck('category_id')->unique()->values();

        if ($categoryIds->count() !== count($validated['items'])) {
            return Response::error('Duplicate category IDs found. Each category can only appear once in a budget.');
        }

        $categories = Category::whereIn('id', $categoryIds->all())
            ->where(function ($q) use ($user) {
                $q->whereNull('user_id')
                    ->orWhere('user_id', $user->id);
            })
            ->where('type', 'expense')
            ->get(['id', 'parent_id', 'name']);

        if ($categories->count() !== $categoryIds->count()) {
            return Response::error('Some categories were not found or are not expense categories. Use GetCategoriesTool to find valid expense categories.');
        }

        // Check parent+child overlap
        $selectedIds = $categoryIds->flip();
        foreach ($categories as $category) {
            if ($category->parent_id !== null && $selectedIds->has($category->parent_id)) {
                return Response::error('Cannot mix a parent category with its subcategories in the same budget.');
            }
        }

        $budget = DB::transaction(function () use ($user, $validated) {
            $budget = $user->budgets()->create([
                'account_id' => $validated['account_id'] ?? null,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'currency' => $validated['currency'],
                'frequency' => $validated['frequency'],
                'anchor_date' => $validated['anchor_date'],
                'ends_at' => $validated['ends_at'] ?? null,
                'is_active' => true,
            ]);

            foreach ($validated['items'] as $item) {
                $budget->items()->create([
                    'category_id' => $item['category_id'],
                    'amount' => $item['amount'],
                ]);
            }

            return $budget;
        });

        $budget->load('items.category');

        $items = $budget->items->map(fn ($item) => [
            'category_id' => $item->category_id,
            'category_name' => $item->category?->name ?? 'Unknown',
            'amount' => $item->amount,
        ]);

        return Response::text(json_encode([
            'success' => true,
            'message' => "Budget \"{$budget->name}\" created successfully.",
            'budget' => [
                'id' => $budget->id,
                'uuid' => $budget->uuid,
                'name' => $budget->name,
                'currency' => $budget->currency,
                'frequency' => $budget->frequency,
                'anchor_date' => $budget->anchor_date->format('Y-m-d'),
                'total_budgeted' => $budget->total_budgeted,
                'items_count' => $items->count(),
                'items' => $items,
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->description('Budget name (e.g., "House", "Personal")')
                ->required(),
            'description' => $schema->string()
                ->description('Optional description'),
            'currency' => $schema->string()
                ->description('3-letter currency code (e.g., CLP)')
                ->required(),
            'frequency' => $schema->string()
                ->description('Budget cycle frequency')
                ->enum(['weekly', 'biweekly', 'monthly', 'bimonthly'])
                ->required(),
            'anchor_date' => $schema->string()
                ->description('Start date of the first cycle (YYYY-MM-DD)')
                ->required(),
            'ends_at' => $schema->string()
                ->description('Optional end date (YYYY-MM-DD)'),
            'account_id' => $schema->integer()
                ->description('Optional account to scope the budget to'),
            'items' => $schema->array()
                ->description('Budget items: array of {category_id, amount} objects. Amount in major currency units.')
                ->required(),
        ];
    }
}
