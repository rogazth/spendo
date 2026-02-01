<?php

namespace App\Mcp\Tools;

use App\Enums\PaymentMethodType;
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
        - Total credit card debt
        - Net balance (accounts - debt)
        - Monthly transaction count and expenses
        - Account breakdown by type
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
        $accountsByType = [];

        foreach ($accounts as $account) {
            $balance = $account->current_balance;
            $totalAccountBalance += $balance;

            $type = $account->type->value;
            if (! isset($accountsByType[$type])) {
                $accountsByType[$type] = ['count' => 0, 'total' => 0];
            }
            $accountsByType[$type]['count']++;
            $accountsByType[$type]['total'] += $balance;
        }

        // Get credit card debt
        $paymentMethods = $user->paymentMethods()
            ->where('is_active', true)
            ->where('type', PaymentMethodType::CreditCard)
            ->get();

        $totalCreditDebt = 0;
        foreach ($paymentMethods as $card) {
            $totalCreditDebt += $card->current_debt;
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
            'total_account_balance_formatted' => '$'.number_format($totalAccountBalance / 100, 0, ',', '.'),
            'total_credit_debt' => $totalCreditDebt,
            'total_credit_debt_formatted' => '$'.number_format($totalCreditDebt / 100, 0, ',', '.'),
            'net_balance' => $totalAccountBalance - $totalCreditDebt,
            'net_balance_formatted' => '$'.number_format(($totalAccountBalance - $totalCreditDebt) / 100, 0, ',', '.'),
            'accounts_by_type' => $accountsByType,
            'credit_cards_count' => $paymentMethods->count(),
            'monthly_stats' => [
                'month' => now()->format('F Y'),
                'transaction_count' => $monthlyTransactionCount,
                'expenses' => $monthlyExpenses,
                'expenses_formatted' => '$'.number_format($monthlyExpenses / 100, 0, ',', '.'),
                'income' => $monthlyIncome,
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
