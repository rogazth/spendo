<?php

namespace App\Mcp\Tools;

use App\Enums\TransactionType;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetFinancialSummaryTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Get a complete financial summary including:
        - Total balance across all accounts
        - Net balance
        - Monthly transaction count and expenses
        - Account totals
    MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user) {
            return Response::error('User not authenticated.');
        }

        // Get all active accounts with their balances
        $accounts = $user->accounts()->where('is_active', true)->get();
        $totalAccountBalance = 0;

        foreach ($accounts as $account) {
            $totalAccountBalance += $account->current_balance;
        }

        // Get this month's stats
        $monthlyTransactionCount = $user->transactions()
            ->whereMonth('transaction_date', now()->month)
            ->whereYear('transaction_date', now()->year)
            ->count();

        $monthlyExpenses = $user->transactions()
            ->where('type', TransactionType::Expense)
            ->whereMonth('transaction_date', now()->month)
            ->whereYear('transaction_date', now()->year)
            ->sum('amount');

        $monthlyIncome = $user->transactions()
            ->where('type', TransactionType::Income)
            ->whereMonth('transaction_date', now()->month)
            ->whereYear('transaction_date', now()->year)
            ->sum('amount');

        $summary = [
            'total_account_balance' => $totalAccountBalance,
            'total_account_balance_formatted' => '$'.number_format($totalAccountBalance, 0, ',', '.'),
            'net_balance' => $totalAccountBalance,
            'net_balance_formatted' => '$'.number_format($totalAccountBalance, 0, ',', '.'),
            'accounts_count' => $accounts->count(),
            'monthly_stats' => [
                'month' => now()->format('F Y'),
                'transaction_count' => $monthlyTransactionCount,
                'expenses' => $monthlyExpenses / 100,
                'expenses_formatted' => '$'.number_format($monthlyExpenses / 100, 0, ',', '.'),
                'income' => $monthlyIncome / 100,
                'income_formatted' => '$'.number_format($monthlyIncome / 100, 0, ',', '.'),
            ],
        ];

        return Response::text(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
