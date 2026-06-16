<?php

namespace App\Mcp\Tools;

use App\Actions\Budgets\CreateBudgetAction;
use App\Http\Resources\BudgetResource;
use App\Models\Currency;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class CreateBudgetTool extends Tool
{
    protected string $description = <<<'MARKDOWN'
        Create a new budget with category-level spending caps.

        **Frequency**: weekly, biweekly, monthly, bimonthly
        **Anchor date**: Start of the first cycle (YYYY-MM-DD). Required for weekly, biweekly, and bimonthly. For **monthly** budgets the cycle follows the user's global cycle start day (from settings), so anchor_date is optional and its day-of-month is ignored.
        **Items**: Array of category_id + amount pairs defining spending caps per category
        **Amounts**: In major currency units (e.g., 572000 for 572,000 CLP)
        **Categories**: Must be non-system spending categories. Cannot mix a parent and its children in the same budget.
        **Account**: Optional `account_id` scopes spending to a single account (must match the budget currency). Several budgets may share an account. When omitted, spending is tracked across every account in the budget currency.
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
            'account_id' => ['nullable', 'integer'],
            'frequency' => ['required', 'string', 'in:weekly,biweekly,monthly,bimonthly'],
            'anchor_date' => ['required_unless:frequency,monthly', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:anchor_date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.category_id' => ['required', 'integer'],
            'items.*.amount' => ['required', 'numeric', 'gt:0'],
        ], [
            'name.required' => 'Budget name is required.',
            'currency.required' => 'Currency is required (e.g., CLP).',
            'frequency.required' => 'Frequency is required (weekly, biweekly, monthly, bimonthly).',
            'frequency.in' => 'Frequency must be weekly, biweekly, monthly, or bimonthly.',
            'anchor_date.required_unless' => 'Anchor date is required (YYYY-MM-DD) for weekly, biweekly, and bimonthly budgets. It is the start date of the first cycle.',
            'items.required' => 'At least one budget item (category + amount) is required.',
            'items.min' => 'At least one budget item is required.',
            'items.*.category_id.required' => 'Each item needs a category_id. Use GetCategoriesTool to find spending categories.',
            'items.*.amount.required' => 'Each item needs an amount in major currency units.',
            'items.*.amount.gt' => 'Each item amount must be greater than zero.',
        ]);

        if (! in_array($validated['currency'], Currency::codes())) {
            return Response::error('Invalid currency code.');
        }

        if (! empty($validated['account_id'])) {
            $account = $user->accounts()->whereKey($validated['account_id'])->first(['id', 'currency']);

            if (! $account) {
                return Response::error('Account not found. Use GetAccountsTool to find valid accounts.');
            }

            if ($account->currency !== $validated['currency']) {
                return Response::error('The account currency must match the budget currency.');
            }
        }

        if ($validated['frequency'] === 'monthly' && empty($validated['anchor_date'])) {
            $day = (int) ($user->settings?->budget_cycle_start_day ?? 1);
            [$cycleStart] = User::resolveMonthlyCycleForDay(CarbonImmutable::now()->startOfDay(), $day);
            $validated['anchor_date'] = $cycleStart->toDateString();
        }

        // Validate categories
        $categoryIds = collect($validated['items'])->pluck('category_id')->unique()->values();

        if ($categoryIds->count() !== count($validated['items'])) {
            return Response::error('Duplicate category IDs found. Each category can only appear once in a budget.');
        }

        $categories = $user->categories()
            ->whereIn('id', $categoryIds->all())
            ->get(['id', 'parent_id', 'name']);

        if ($categories->count() !== $categoryIds->count()) {
            return Response::error('Some categories were not found. Use GetCategoriesTool to find valid categories.');
        }

        // Check parent+child overlap
        $selectedIds = $categoryIds->flip();
        foreach ($categories as $category) {
            if ($category->parent_id !== null && $selectedIds->has($category->parent_id)) {
                return Response::error('Cannot mix a parent category with its subcategories in the same budget.');
            }
        }

        $budget = app(CreateBudgetAction::class)->handle($user, $validated);

        $budget->load('items.category');

        $budgetData = (new BudgetResource($budget))->resolve();
        $budgetData['items_count'] = $budget->items->count();

        return Response::text(json_encode([
            'success' => true,
            'message' => "Budget \"{$budget->name}\" created successfully.",
            'budget' => $budgetData,
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
            'account_id' => $schema->integer()
                ->description('Optional account this budget draws from. Must match the budget currency. Omit to track every account in the currency.'),
            'frequency' => $schema->string()
                ->description('Budget cycle frequency')
                ->enum(['weekly', 'biweekly', 'monthly', 'bimonthly'])
                ->required(),
            'anchor_date' => $schema->string()
                ->description('Start date of the first cycle (YYYY-MM-DD). Required for weekly/biweekly/bimonthly; optional and ignored for monthly (uses the user\'s global cycle start day).'),
            'ends_at' => $schema->string()
                ->description('Optional end date (YYYY-MM-DD)'),
            'items' => $schema->array()
                ->description('Budget items: array of {category_id, amount} objects. Amount in major currency units.')
                ->required(),
        ];
    }
}
