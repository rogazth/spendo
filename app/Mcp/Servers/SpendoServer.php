<?php

namespace App\Mcp\Servers;

use App\Mcp\Prompts\BudgetStatusPrompt;
use App\Mcp\Prompts\RegisterTransactionPrompt;
use App\Mcp\Prompts\SetupBudgetPrompt;
use App\Mcp\Tools\BulkCreateTransactionsTool;
use App\Mcp\Tools\CreateAccountTool;
use App\Mcp\Tools\CreateBudgetTool;
use App\Mcp\Tools\CreateCategoryTool;
use App\Mcp\Tools\CreateTagTool;
use App\Mcp\Tools\CreateTransactionTool;
use App\Mcp\Tools\CreateTransferTool;
use App\Mcp\Tools\DeleteAccountTool;
use App\Mcp\Tools\DeleteTagTool;
use App\Mcp\Tools\DeleteTransactionTool;
use App\Mcp\Tools\GetAccountsTool;
use App\Mcp\Tools\GetBudgetMetricsTool;
use App\Mcp\Tools\GetBudgetsTool;
use App\Mcp\Tools\GetCategoriesTool;
use App\Mcp\Tools\GetFinancialSummaryTool;
use App\Mcp\Tools\GetTagsTool;
use App\Mcp\Tools\GetTransactionsTool;
use App\Mcp\Tools\UpdateAccountTool;
use App\Mcp\Tools\UpdateBudgetTool;
use App\Mcp\Tools\UpdateCategoryTool;
use App\Mcp\Tools\UpdateTagTool;
use App\Mcp\Tools\UpdateTransactionTool;
use Laravel\Mcp\Server;

class SpendoServer extends Server
{
    /**
     * The MCP server's name.
     */
    protected string $name = 'Spendo';

    /**
     * Return all tools without pagination truncation.
     */
    public int $defaultPaginationLength = 50;

    /**
     * The MCP server's version.
     */
    protected string $version = '4.0.0';

    /**
     * The MCP server's instructions for the LLM.
     */
    protected string $instructions = <<<'MARKDOWN'
        Spendo is a personal finance management application. Use this server to:

        - **Accounts**: Create, update, and list bank accounts (simple containers with balance)
        - **Categories**: Create, update, and list hierarchical categories. Transaction direction is determined by signed amounts, not category type.
        - **Budgets**: Create, update, and list budgets (named groups with category-level caps). Budget spending is scoped by currency and only includes budget-eligible expenses: transactions with `exclude_from_budget=false` from accounts with `include_in_budget=true`.
        - **Transactions**: Record expenses, income, and transfers. Tag transactions with user-defined labels.
        - **Tags**: Create, update, delete, and list tags. Attach tags to transactions for filtering.
        - **Metrics**: View budget progress, category-level spending, and financial summaries

        **Currency**: All amounts use **major currency units** (e.g., 572000 means 572,000 CLP). Do NOT use centavos.

        **Transactions**:
        Amounts are signed. **Negative amount = expense (outflow); positive amount = income (inflow)**.
        - Use `CreateTransactionTool` for income/expense: requires account_id, category_id, signed amount, description.
        - Use `CreateTransferTool` for transfers between two accounts: requires origin_account_id, destination_account_id, positive amount. Creates two linked transactions with opposite signs.
        - Transfers are identified by a non-null `linked_transaction_id`. They are excluded from budget spending and from income/expense totals.

        **Budget Model**: Budgets have a name, currency, frequency (weekly/biweekly/monthly/bimonthly), an anchor date, and category items with caps. Spending is measured as budget-eligible expenses where transaction.currency = budget.currency.

        **Tags**: User-defined labels (name + optional hex color). A transaction can have multiple tags. Use tag_ids when creating/updating transactions.

        **IDs**: All entities have both numeric `id` and `uuid`. Use `id` for tool parameters. Use `uuid` for external references.
    MARKDOWN;

    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        // Summary
        GetFinancialSummaryTool::class,

        // Accounts
        GetAccountsTool::class,
        CreateAccountTool::class,
        UpdateAccountTool::class,
        DeleteAccountTool::class,

        // Categories
        GetCategoriesTool::class,
        CreateCategoryTool::class,
        UpdateCategoryTool::class,

        // Budgets
        GetBudgetsTool::class,
        GetBudgetMetricsTool::class,
        CreateBudgetTool::class,
        UpdateBudgetTool::class,

        // Transactions
        GetTransactionsTool::class,
        CreateTransactionTool::class,
        CreateTransferTool::class,
        BulkCreateTransactionsTool::class,
        UpdateTransactionTool::class,
        DeleteTransactionTool::class,

        // Tags
        GetTagsTool::class,
        CreateTagTool::class,
        UpdateTagTool::class,
        DeleteTagTool::class,
    ];

    /**
     * The resources registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Resource>>
     */
    protected array $resources = [
        //
    ];

    /**
     * The prompts registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Prompt>>
     */
    protected array $prompts = [
        SetupBudgetPrompt::class,
        RegisterTransactionPrompt::class,
        BudgetStatusPrompt::class,
    ];
}
