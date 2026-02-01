<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethodType;
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

        $paymentMethods = $user->paymentMethods()
            ->where('is_active', true)
            ->with('linkedAccount')
            ->get();

        // Calculate total balance across all accounts
        $totalAccountBalance = 0;
        foreach ($accounts as $account) {
            $totalAccountBalance += $account->current_balance;
        }

        // Calculate total credit card debt
        $totalCreditDebt = 0;
        foreach ($paymentMethods->where('type', PaymentMethodType::CreditCard) as $card) {
            $totalCreditDebt += $card->current_debt;
        }

        // Get recent transactions
        $recentTransactions = $user->transactions()
            ->with(['paymentMethod', 'category', 'account'])
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
                'payment_method' => $t->paymentMethod?->name,
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
                'type' => $a->type->value,
                'type_label' => $a->type->label(),
                'currency' => $a->currency,
                'current_balance' => $a->current_balance,
                'formatted_balance' => $a->formatted_balance,
                'color' => $a->color,
                'icon' => $a->icon,
            ]),
            'paymentMethods' => $paymentMethods->map(fn ($pm) => [
                'id' => $pm->id,
                'uuid' => $pm->uuid,
                'name' => $pm->name,
                'type' => $pm->type->value,
                'type_label' => $pm->type->label(),
                'current_debt' => $pm->current_debt,
                'formatted_debt' => $pm->formatted_debt,
                'credit_limit' => $pm->credit_limit,
                'color' => $pm->color,
                'icon' => $pm->icon,
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
