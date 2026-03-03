<?php

namespace App\Mcp\Tools;

use App\Enums\InstrumentType;
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
        - Total credit card debt (from credit card instruments)
        - Net balance (accounts - debt)
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

        // Get credit card instruments and their outstanding debt
        $creditCardInstruments = $user->instruments()
            ->where('is_active', true)
            ->whereIn('type', [InstrumentType::CreditCard->value, InstrumentType::PrepaidCard->value])
            ->get();

        $totalCreditDebt = 0;
        $creditCardBreakdown = [];
        foreach ($creditCardInstruments as $card) {
            $debt = $card->current_debt;
            $totalCreditDebt += $debt;
            $creditCardBreakdown[] = [
                'id' => $card->id,
                'name' => $card->name,
                'current_debt' => $debt,
                'current_debt_formatted' => '$'.number_format($debt, 0, ',', '.'),
                'credit_limit' => $card->credit_limit,
                'available_credit' => $card->available_credit,
            ];
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
            'total_credit_debt' => $totalCreditDebt,
            'total_credit_debt_formatted' => '$'.number_format($totalCreditDebt, 0, ',', '.'),
            'net_balance' => $totalAccountBalance - $totalCreditDebt,
            'net_balance_formatted' => '$'.number_format($totalAccountBalance - $totalCreditDebt, 0, ',', '.'),
            'accounts_count' => $accounts->count(),
            'credit_cards_count' => $creditCardInstruments->count(),
            'credit_cards' => $creditCardBreakdown,
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
