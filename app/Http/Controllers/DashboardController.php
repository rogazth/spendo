<?php

namespace App\Http\Controllers;

use App\Enums\InstrumentType;
use App\Enums\TransactionType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = Auth::user();

        $accounts = $user->accounts()
            ->where('is_active', true)
            ->get();

        $instruments = $user->instruments()
            ->where('is_active', true)
            ->get();

        // Calculate total balance across all accounts
        $totalAccountBalance = 0;
        foreach ($accounts as $account) {
            $totalAccountBalance += $account->current_balance;
        }

        // Calculate total credit card debt
        $totalCreditDebt = 0;
        foreach ($instruments->filter(fn ($i) => $i->type === InstrumentType::CreditCard || $i->type === InstrumentType::PrepaidCard) as $card) {
            $totalCreditDebt += $card->current_debt;
        }

        // Get recent transactions
        $recentTransactions = $user->transactions()
            ->with(['instrument', 'category', 'account'])
            ->latest('transaction_date')
            ->take(10)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'uuid' => $t->uuid,
                'type' => $t->type->value,
                'type_label' => $t->type->label(),
                'amount' => $t->amount,
                'formatted_amount' => $t->formatted_amount,
                'description' => $t->description,
                'transaction_date' => $t->transaction_date->format('Y-m-d'),
                'category' => $t->category?->name,
                'category_color' => $t->category?->color,
                'instrument' => $t->instrument?->name,
                'account' => $t->account?->name,
            ]);

        // Get this month's transactions count
        $monthlyTransactionCount = $user->transactions()
            ->whereMonth('transaction_date', now()->month)
            ->whereYear('transaction_date', now()->year)
            ->count();

        // Get this month's expenses
        $monthlyExpenses = $user->transactions()
            ->where('type', TransactionType::Expense)
            ->whereMonth('transaction_date', now()->month)
            ->whereYear('transaction_date', now()->year)
            ->sum('amount');

        return Inertia::render('dashboard', [
            'accounts' => $accounts->map(fn ($a) => [
                'id' => $a->id,
                'uuid' => $a->uuid,
                'name' => $a->name,
                'currency' => $a->currency,
                'current_balance' => $a->current_balance,
                'formatted_balance' => $a->formatted_balance,
                'color' => $a->color,
                'icon' => $a->icon,
            ]),
            'instruments' => $instruments->map(fn ($i) => [
                'id' => $i->id,
                'uuid' => $i->uuid,
                'name' => $i->name,
                'type' => $i->type->value,
                'type_label' => $i->type->label(),
                'current_debt' => $i->current_debt,
                'formatted_debt' => $i->formatted_debt,
                'credit_limit' => $i->credit_limit,
                'color' => $i->color,
                'icon' => $i->icon,
            ]),
            'recentTransactions' => $recentTransactions,
            'summary' => [
                'totalAccountBalance' => $totalAccountBalance,
                'formattedAccountBalance' => number_format($totalAccountBalance / 100, 0, ',', '.'),
                'totalCreditDebt' => $totalCreditDebt,
                'formattedCreditDebt' => number_format($totalCreditDebt / 100, 0, ',', '.'),
                'netBalance' => $totalAccountBalance - $totalCreditDebt,
                'formattedNetBalance' => number_format(($totalAccountBalance - $totalCreditDebt) / 100, 0, ',', '.'),
                'monthlyTransactionCount' => $monthlyTransactionCount,
                'monthlyExpenses' => $monthlyExpenses,
                'formattedMonthlyExpenses' => number_format($monthlyExpenses / 100, 0, ',', '.'),
            ],
        ]);
    }
}
