<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Services\BudgetMetricsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly BudgetMetricsService $budgetMetrics) {}

    public function index(Request $request): Response
    {
        $user = Auth::user();
        $referenceDate = CarbonImmutable::now()->startOfDay();

        $accounts = $user->accounts()
            ->where('is_active', true)
            ->get();

        $cashByCurrency = $accounts
            ->groupBy('currency')
            ->map(fn ($group) => (float) $group->sum('current_balance'));

        $budgetMetrics = $this->budgetMetrics->forActiveBudgets($user, $referenceDate);

        $budgetsByCurrency = $budgetMetrics->groupBy(fn (array $entry) => $entry['budget']->currency);

        $currencies = collect($cashByCurrency->keys())
            ->merge($budgetsByCurrency->keys())
            ->unique()
            ->sort()
            ->values();

        $currencySummaries = $currencies
            ->map(function (string $currency) use ($cashByCurrency, $budgetsByCurrency) {
                $cashOnHand = (float) ($cashByCurrency[$currency] ?? 0);
                $entries = $budgetsByCurrency[$currency] ?? collect();

                $totalReserved = (float) $entries->sum('reserved');
                $totalBudgeted = (float) $entries->sum('budgeted');
                $totalSpent = (float) $entries->sum('spent');
                $totalOverspend = (float) $entries->sum('overspend_amount');

                $budgets = $entries
                    ->map(function (array $entry) {
                        $budgeted = (float) $entry['budgeted'];
                        $spent = (float) $entry['spent'];
                        $percentage = $budgeted > 0
                            ? round(($spent / $budgeted) * 100, 2)
                            : 0;

                        return [
                            'id' => $entry['budget']->id,
                            'uuid' => $entry['budget']->uuid,
                            'name' => $entry['budget']->name,
                            'budgeted' => $budgeted,
                            'spent' => $spent,
                            'reserved' => (float) $entry['reserved'],
                            'overspend_amount' => (float) $entry['overspend_amount'],
                            'has_overspend' => (bool) $entry['has_overspend'],
                            'percentage' => $percentage,
                            'cycle_start' => $entry['cycle_start']->toDateString(),
                            'cycle_end' => $entry['cycle_end']->toDateString(),
                            'daily_spent' => $entry['daily_spent'],
                        ];
                    })
                    ->sortByDesc('reserved')
                    ->values()
                    ->all();

                return [
                    'currency' => $currency,
                    'currency_locale' => Currency::localeFor($currency),
                    'cash_on_hand' => $cashOnHand,
                    'total_reserved' => $totalReserved,
                    'ready_to_assign' => $cashOnHand - $totalReserved,
                    'total_budgeted' => $totalBudgeted,
                    'total_spent' => $totalSpent,
                    'total_overspend' => $totalOverspend,
                    'budgets' => $budgets,
                ];
            })
            ->values()
            ->all();

        return Inertia::render('dashboard', [
            'currencySummaries' => $currencySummaries,
        ]);
    }
}
