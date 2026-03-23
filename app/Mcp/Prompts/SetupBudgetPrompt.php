<?php

namespace App\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

#[Description('Guide the user through creating a monthly budget with category caps. Ensures all required entities exist before creating the budget.')]
class SetupBudgetPrompt extends Prompt
{
    /**
     * @return array<int, \Laravel\Mcp\Server\Prompts\Argument>
     */
    public function arguments(): array
    {
        return [
            new Argument(
                name: 'budget_name',
                description: 'Name for the budget (e.g., "House", "Personal")',
                required: true,
            ),
        ];
    }

    /**
     * @return array<int, \Laravel\Mcp\Response>
     */
    public function handle(Request $request): array
    {
        $budgetName = $request->string('budget_name');

        return [
            Response::text(<<<PROMPT
                You are helping the user set up a "{$budgetName}" monthly budget in Spendo.

                Follow these steps in order:

                1. **Check existing setup**: Call GetAccountsTool and GetCategoriesTool to see what already exists.

                2. **Verify prerequisites**: The user needs at least:
                   - One active account
                   - Expense categories for each budget line item

                3. **Create missing entities**: If any accounts or categories are missing, ask the user what to create and use the appropriate Create tools.

                4. **Collect budget items**: Ask the user which expense categories to include and the monthly cap for each. Amounts should be in major currency units (e.g., 572000 for 572,000 CLP).

                5. **Create the budget**: Use CreateBudgetTool with:
                   - name: "{$budgetName}"
                   - currency: "CLP" (or ask the user)
                   - frequency: "monthly"
                   - anchor_date: First day of the current month
                   - items: Array of {category_id, amount} pairs

                6. **Confirm**: Show the created budget summary with total budgeted amount and all category caps.

                **Important rules:**
                - Never guess or hallucinate IDs. Always fetch them first with Get tools.
                - Cannot mix a parent category and its subcategories in the same budget.
                - All amounts are in major currency units (NOT centavos).
                - If the user provides a list of categories and amounts, create the budget in one call.
                PROMPT)->asAssistant(),
        ];
    }
}
