<?php

namespace App\Mcp\Servers;

use App\Mcp\Prompts\BudgetStatusPrompt;
use App\Mcp\Prompts\RegisterTransactionPrompt;
use App\Mcp\Prompts\SetupBudgetPrompt;
use App\Mcp\Tools\BulkCreateTransactionsTool;
use App\Mcp\Tools\CreateAccountTool;
use App\Mcp\Tools\CreateBudgetTool;
use App\Mcp\Tools\CreateCategoryTool;
use App\Mcp\Tools\CreateInstrumentTool;
use App\Mcp\Tools\CreateTransactionTool;
use App\Mcp\Tools\GetAccountsTool;
use App\Mcp\Tools\GetBudgetMetricsTool;
use App\Mcp\Tools\GetBudgetsTool;
use App\Mcp\Tools\GetCategoriesTool;
use App\Mcp\Tools\GetFinancialSummaryTool;
use App\Mcp\Tools\GetInstrumentsTool;
use App\Mcp\Tools\GetTransactionsTool;
use App\Mcp\Tools\UpdateAccountTool;
use App\Mcp\Tools\UpdateBudgetTool;
use App\Mcp\Tools\UpdateCategoryTool;
use App\Mcp\Tools\UpdateInstrumentTool;
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
    protected string $version = '2.0.0';

    /**
     * The MCP server's instructions for the LLM.
     */
    protected string $instructions = <<<'MARKDOWN'
        Spendo is a personal finance management application for managing personal and household finances. Use this server to:

        - **Accounts**: Create, update, and list bank accounts (simple containers with balance)
        - **Instruments**: Create, update, and list financial instruments (checking, savings, cash, investment, credit_card, prepaid_card)
        - **Categories**: Create, update, and list expense/income categories (hierarchical)
        - **Budgets**: Create, update, and list monthly budgets with category-level caps
        - **Transactions**: Record expenses, income, transfers, and credit card settlements
        - **Metrics**: View budget progress, category-level spending, and financial summaries

        **Currency**: All amounts use **major currency units** (e.g., 572000 means 572,000 CLP). Do NOT use centavos.

        **Transaction Types**:
        - `expense`: Money spent — requires account_id; optionally instrument_id (the card used)
        - `income`: Money received — requires account_id
        - `transfer`: Money moved between two accounts (creates linked transfer_out + transfer_in)
        - `settlement`: Credit card payment — requires instrument_id (CC being paid) and from_instrument_id (bank paying). Does NOT affect account balance.

        **Budget Model**: Budgets have a frequency (weekly/biweekly/monthly/bimonthly), an anchor date, and category items with caps. Only expense transactions within the budget's categories count toward spending.

        **IDs**: All entities have both numeric `id` and `uuid`. Use `id` for tool parameters. Use `uuid` for external references.
    MARKDOWN;

    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        // Read tools
        GetFinancialSummaryTool::class,
        GetAccountsTool::class,
        GetInstrumentsTool::class,
        GetCategoriesTool::class,
        GetTransactionsTool::class,
        GetBudgetsTool::class,
        GetBudgetMetricsTool::class,

        // Write tools - Accounts
        CreateAccountTool::class,
        UpdateAccountTool::class,

        // Write tools - Instruments
        CreateInstrumentTool::class,
        UpdateInstrumentTool::class,

        // Write tools - Categories
        CreateCategoryTool::class,
        UpdateCategoryTool::class,

        // Write tools - Budgets
        CreateBudgetTool::class,
        UpdateBudgetTool::class,

        // Write tools - Transactions
        CreateTransactionTool::class,
        BulkCreateTransactionsTool::class,
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
