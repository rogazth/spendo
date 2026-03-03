<?php

namespace App\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

#[Description('Register a transaction and immediately show its impact on the relevant budget.')]
class RegisterTransactionPrompt extends Prompt
{
    /**
     * @return array<int, \Laravel\Mcp\Server\Prompts\Argument>
     */
    public function arguments(): array
    {
        return [
            new Argument(
                name: 'transaction_description',
                description: 'Natural language description of the transaction (e.g., "Groceries 58900 CLP on debit card today")',
                required: true,
            ),
        ];
    }

    /**
     * @return array<int, \Laravel\Mcp\Response>
     */
    public function handle(Request $request): array
    {
        $description = $request->string('transaction_description');

        return [
            Response::text(<<<PROMPT
                You are helping the user register a transaction in Spendo and show its budget impact.

                The user said: "{$description}"

                Follow these steps:

                1. **Parse the request**: Extract transaction type, amount, category, payment method, account, and date from the description.

                2. **Resolve IDs**: Call the appropriate Get tools to find matching IDs:
                   - GetCategoriesTool for the category
                   - GetInstrumentsTool for the instrument (card or bank account used)
                   - GetAccountsTool for the account
                   If ambiguous, ask the user to clarify.

                3. **Create the transaction**: Use CreateTransactionTool with the resolved parameters.
                   - Amounts are in major currency units (e.g., 58900 for 58,900 CLP)
                   - Default date is today if not specified

                4. **Show budget impact**: After creating the transaction, call GetBudgetsTool to find active budgets, then call GetBudgetMetricsTool for each relevant budget to show:
                   - Overall budget remaining amount and percentage
                   - Category-level remaining amount and percentage

                5. **Summarize**: Present a concise summary showing:
                   - Transaction created (type, amount, category, date)
                   - Budget impact (remaining in budget and category)

                **Important rules:**
                - Never guess IDs. Always fetch and confirm.
                - All amounts are in major currency units.
                - If a transaction doesn't match any budget category, still create it but note it won't affect budgets.
                PROMPT)->asAssistant(),
        ];
    }
}
