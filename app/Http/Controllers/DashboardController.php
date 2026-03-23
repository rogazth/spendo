<?php

namespace App\Http\Controllers;

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

        $totalAccountBalance = 0;
        foreach ($accounts as $account) {
            $totalAccountBalance += $account->current_balance;
        }

        $recentTransactions = $user->transactions()
            ->with(['category', 'account'])
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
                'account' => $t->account?->name,
            ]);

        $monthlyTransactionCount = $user->transactions()
            ->whereMonth('transaction_date', now()->month)
            ->whereYear('transaction_date', now()->year)
            ->count();

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
            'recentTransactions' => $recentTransactions,
            'summary' => [
                'totalAccountBalance' => $totalAccountBalance,
                'formattedAccountBalance' => number_format($totalAccountBalance / 100, 0, ',', '.'),
                'monthlyTransactionCount' => $monthlyTransactionCount,
                'monthlyExpenses' => $monthlyExpenses,
                'formattedMonthlyExpenses' => number_format($monthlyExpenses / 100, 0, ',', '.'),
            ],
        ]);
    }
}
