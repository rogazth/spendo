<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CreateTransactionTool;
use App\Mcp\Tools\GetAccountsTool;
use App\Mcp\Tools\GetCategoriesTool;
use App\Mcp\Tools\GetFinancialSummaryTool;
use App\Mcp\Tools\GetPaymentMethodsTool;
use App\Mcp\Tools\GetTransactionsTool;
use Laravel\Mcp\Server;

class SpendoServer extends Server
{
    /**
     * The MCP server's name.
     */
    protected string $name = 'Spendo';

    /**
     * The MCP server's version.
     */
    protected string $version = '1.0.0';

    /**
     * The MCP server's instructions for the LLM.
     */
    protected string $instructions = <<<'MARKDOWN'
        Spendo is a personal finance management application. Use this server to:

        - **Get Financial Summary**: View total account balances, credit card debt, net balance, and monthly expenses
        - **Get Accounts**: List all bank accounts (checking, savings, cash, investments) with their current balances
        - **Get Payment Methods**: List payment methods (credit cards, debit cards, cash) with debt information for credit cards
        - **Get Categories**: View expense and income categories organized hierarchically
        - **Get Transactions**: Query transactions with optional filters (date range, type, category, account)
        - **Create Transaction**: Record new expenses, income, or other transaction types

        **Currency**: All amounts are stored in centavos (1/100 of the currency unit). For example, $1,500.50 CLP = 150050 centavos.

        **Transaction Types**:
        - `expense`: Money spent (affects account if not credit card, or adds to credit card debt)
        - `income`: Money received (adds to account balance)
        - `transfer_out` / `transfer_in`: Money moved between accounts
        - `settlement`: Credit card payment (reduces account balance and credit card debt)
        - `initial_balance`: Starting balance for an account
    MARKDOWN;

    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        GetFinancialSummaryTool::class,
        GetAccountsTool::class,
        GetPaymentMethodsTool::class,
        GetCategoriesTool::class,
        GetTransactionsTool::class,
        CreateTransactionTool::class,
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
        //
    ];
}
