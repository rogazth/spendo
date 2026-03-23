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
    protected string $version = '3.0.0';

    /**
     * The MCP server's instructions for the LLM.
     */
    protected string $instructions = <<<'MARKDOWN'
        Spendo is a personal finance management application. Use this server to:

        - **Accounts**: Create, update, and list bank accounts (simple containers with balance)
        - **Categories**: Create, update, and list expense/income categories (hierarchical)
        - **Budgets**: Create, update, and list budgets (named groups with category-level caps). Budget spending is scoped by currency — only transactions matching the budget's currency count.
        - **Transactions**: Record expenses, income, and transfers. Tag transactions with user-defined labels.
        - **Tags**: Create, update, delete, and list tags. Attach tags to transactions for filtering.
        - **Metrics**: View budget progress, category-level spending, and financial summaries

        **Currency**: All amounts use **major currency units** (e.g., 572000 means 572,000 CLP). Do NOT use centavos.

        **Transaction Types**:
        - `expense`: Money spent — requires account_id and category_id
        - `income`: Money received — requires account_id and category_id
        - `transfer`: Money moved between two accounts (creates linked transfer_out + transfer_in)

        **Budget Model**: Budgets have a name, currency, frequency (weekly/biweekly/monthly/bimonthly), an anchor date, and category items with caps. Spending is measured as expenses where transaction.currency = budget.currency.

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
