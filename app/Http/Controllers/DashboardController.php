<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Currency;
use App\Services\BudgetMetricsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const TIGHT_THRESHOLD = 0.9;

    public function __construct(private readonly BudgetMetricsService $budgetMetrics) {}

    /**
     * Money per account, what is left free after budget reserves, and only the
     * budgets that need attention. Budget detail lives in /budgets.
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $referenceDate = CarbonImmutable::now()->startOfDay();

        $accounts = $user->accounts()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $metrics = $this->budgetMetrics->forActiveBudgets($user, $referenceDate);
        $metricsByCurrency = $metrics->groupBy(fn (array $entry) => $entry['budget']->currency);

        $currencies = $accounts->pluck('currency')
            ->merge($metricsByCurrency->keys())
            ->unique()
            ->sort()
            ->values();

        $currencySummaries = $currencies
            ->map(function (string $currency) use ($accounts, $metricsByCurrency) {
                $currencyAccounts = $accounts->where('currency', $currency);
                $entries = $metricsByCurrency[$currency] ?? collect();

                $total = (float) $currencyAccounts->sum('current_balance');
                $reserved = (float) $entries->sum('reserved');

                return [
                    'currency' => $currency,
                    'currency_locale' => Currency::localeFor($currency),
                    'total' => $total,
                    'reserved' => $reserved,
                    'available' => $total - $reserved,
                    'accounts' => $currencyAccounts->map(fn (Account $account) => [
                        'uuid' => $account->uuid,
                        'name' => $account->name,
                        'emoji' => $account->emoji,
                        'color' => $account->color,
                        'balance' => (float) $account->current_balance,
                        'is_default' => (bool) $account->is_default,
                    ])->values()->all(),
                    'alerts' => $this->buildAlerts($entries),
                ];
            })
            ->values()
            ->all();

        return Inertia::render('dashboard', [
            'currencySummaries' => $currencySummaries,
        ]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $entries
     * @return list<array<string, mixed>>
     */
    private function buildAlerts(Collection $entries): array
    {
        return $entries
            ->map(function (array $entry) {
                $budgeted = (float) $entry['budgeted'];
                $spent = (float) $entry['spent'];
                $ratio = $budgeted > 0 ? $spent / $budgeted : 0;
                $isOver = $entry['has_overspend'] || $ratio > 1;

                if (! $isOver && $ratio < self::TIGHT_THRESHOLD) {
                    return null;
                }

                return [
                    'uuid' => $entry['budget']->uuid,
                    'name' => $entry['budget']->name,
                    'emoji' => $entry['budget']->emoji ?? '💰',
                    'budgeted' => $budgeted,
                    'spent' => $spent,
                    'overspend_amount' => (float) $entry['overspend_amount'],
                    'percentage' => $budgeted > 0 ? round($ratio * 100, 1) : 0,
                    'status' => $isOver ? 'over' : 'tight',
                    'cycle_end' => $entry['cycle_end']->toDateString(),
                ];
            })
            ->filter()
            ->sortByDesc('percentage')
            ->values()
            ->all();
    }
}
