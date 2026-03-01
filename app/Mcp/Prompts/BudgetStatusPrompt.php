<?php

namespace App\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

#[Description('Get a concise budget status overview with spending progress and category breakdown.')]
class BudgetStatusPrompt extends Prompt
{
    /**
     * @return array<int, \Laravel\Mcp\Server\Prompts\Argument>
     */
    public function arguments(): array
    {
        return [
            new Argument(
                name: 'budget_name',
                description: 'Name of the budget to check (e.g., "House", "Personal"). If not provided, shows all active budgets.',
                required: false,
            ),
        ];
    }

    /**
     * @return array<int, \Laravel\Mcp\Response>
     */
    public function handle(Request $request): array
    {
        $budgetName = $request->get('budget_name');
        $budgetFilter = $budgetName ? "specifically the \"{$budgetName}\" budget" : 'all active budgets';

        return [
            Response::text(<<<PROMPT
                You are providing a budget status summary for {$budgetFilter} in Spendo.

                Follow these steps:

                1. **Find budgets**: Call GetBudgetsTool to list active budgets.
                   If a specific budget was requested, find it by name.
                   If no match is found, list available budgets and ask the user to clarify.

                2. **Get metrics**: For each relevant budget, call GetBudgetMetricsTool with scope "current" to get:
                   - Overall budget progress (budgeted, spent, remaining, percentages)
                   - Per-category breakdown

                3. **Present summary**: Format a clear, concise overview:
                   - Budget name and current cycle dates
                   - Total: budgeted / spent / remaining (with percentage)
                   - Category breakdown: show each category's spent vs budgeted
                   - Highlight categories that are over 80% spent
                   - Flag any categories that have exceeded their budget

                4. **Format amounts**: Display amounts in CLP format (e.g., $572.000) and percentages.

                **Important:**
                - All amounts from the API are in major currency units.
                - Categories over 80% should be highlighted as warnings.
                - Categories over 100% should be flagged as over-budget.
                PROMPT)->asAssistant(),
        ];
    }
}
