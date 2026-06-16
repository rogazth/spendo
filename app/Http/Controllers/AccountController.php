<?php

namespace App\Http\Controllers;

use App\Actions\Accounts\CreateAccountAction;
use App\Actions\Accounts\DeleteAccountAction;
use App\Actions\Accounts\UpdateAccountAction;
use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Transaction;
use App\Services\BudgetMetricsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(BudgetMetricsService $budgetMetrics): Response
    {
        $user = Auth::user();

        $accounts = $user->accounts()
            ->select('accounts.*')
            ->selectSub(
                Transaction::query()
                    ->selectRaw('COALESCE(SUM(amount), 0)')
                    ->whereColumn('transactions.account_id', 'accounts.id'),
                'balance_cents',
            )
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // Budget commitments keyed by budget id: reserved = unspent caps,
        // overspend = spent over caps. Only active budgets in their cycle appear.
        $metricsByBudgetId = $budgetMetrics
            ->forActiveBudgets($user, CarbonImmutable::now()->startOfDay())
            ->keyBy(fn (array $entry) => $entry['budget']->id);

        $accountToArray = fn (Account $account): array => [
            'id' => $account->id,
            'uuid' => $account->uuid,
            'name' => $account->name,
            'currency' => $account->currency,
            'currency_locale' => Currency::localeFor($account->currency),
            'current_balance' => ((int) ($account->getAttribute('balance_cents') ?? 0)) / 100,
            'color' => $account->color,
            'emoji' => $account->emoji,
            'is_active' => (bool) $account->is_active,
            'is_default' => (bool) $account->is_default,
        ];

        $currencySummaries = $accounts
            ->groupBy('currency')
            ->map(function ($group, string $currency) use ($metricsByBudgetId, $accountToArray) {
                // Each account carries the budgets that draw from it as the
                // segments of its bar. Reserved is the sum of those budgets'
                // unspent caps; free cash is the balance left after them.
                $accountRows = $group->map(function (Account $account) use ($metricsByBudgetId, $accountToArray) {
                    $row = $accountToArray($account);

                    $budgets = $metricsByBudgetId
                        ->filter(fn (array $entry): bool => $entry['budget']->account_id === $account->id)
                        ->map(fn (array $entry): array => [
                            'uuid' => $entry['budget']->uuid,
                            'name' => $entry['budget']->name,
                            'color' => $entry['budget']->color,
                            'emoji' => $entry['budget']->emoji,
                            'reserved' => (float) $entry['reserved'],
                            'overspend' => (float) $entry['overspend_amount'],
                        ])
                        ->sortByDesc('reserved')
                        ->values();

                    $reserved = (float) $budgets->sum('reserved');
                    $row['reserved'] = $reserved;
                    $row['available'] = $row['current_balance'] - $reserved;
                    $row['budgets'] = $budgets->all();

                    return $row;
                })->values();

                $total = (float) $accountRows->sum('current_balance');
                $boundReserved = (float) $accountRows->sum('reserved');

                $currencyMetrics = $metricsByBudgetId->filter(
                    fn (array $entry): bool => $entry['budget']->currency === $currency,
                );

                // Active budgets in this currency with no account can't sit on any
                // card, but their reserve still locks cash — so it keeps reducing the
                // currency's free total, matching the dashboard. (Transient: assign
                // each such budget an account and this drops to zero.)
                $unassignedReserved = (float) $currencyMetrics
                    ->filter(fn (array $entry): bool => $entry['budget']->account_id === null)
                    ->sum('reserved');
                $reservedTotal = $boundReserved + $unassignedReserved;
                $overspendTotal = (float) $currencyMetrics->sum('overspend_amount');

                return [
                    'currency' => $currency,
                    'currency_locale' => Currency::localeFor($currency),
                    'accounts_count' => $group->count(),
                    'total' => $total,
                    'reserved_total' => $reservedTotal,
                    'unassigned_reserved' => $unassignedReserved,
                    'overspend_total' => $overspendTotal,
                    // Free cash for the currency = balance not still reserved by budgets.
                    'available' => $total - $reservedTotal,
                    'accounts' => $accountRows->all(),
                ];
            })
            ->sortKeys()
            ->values()
            ->all();

        $defaultAccount = $accounts->firstWhere('is_default', true);

        $budgetedAccountIds = $metricsByBudgetId
            ->map(fn (array $entry) => $entry['budget']->account_id)
            ->filter()
            ->unique();

        $totals = [
            'accounts' => $accounts->count(),
            'budgeted' => $accounts->whereIn('id', $budgetedAccountIds->all())->count(),
            'currencies' => count($currencySummaries),
            'default_name' => $defaultAccount?->name,
        ];

        return Inertia::render('accounts/index', [
            'currencySummaries' => $currencySummaries,
            'totals' => $totals,
        ]);
    }

    public function store(StoreAccountRequest $request, CreateAccountAction $createAccount): RedirectResponse
    {
        $createAccount->handle(Auth::user(), $request->validated());

        return redirect()
            ->route('accounts.index')
            ->with('success', 'Cuenta creada exitosamente.');
    }

    public function update(UpdateAccountRequest $request, Account $account, UpdateAccountAction $updateAccount): RedirectResponse
    {
        $this->authorizeAccount($account);

        $updateAccount->handle($account, Auth::user(), $request->validated());

        return redirect()
            ->route('accounts.index')
            ->with('success', 'Cuenta actualizada exitosamente.');
    }

    public function makeDefault(Account $account, UpdateAccountAction $updateAccount): RedirectResponse
    {
        $this->authorizeAccount($account);

        $updateAccount->handle($account, Auth::user(), ['is_default' => true]);

        return back();
    }

    public function destroy(Account $account, DeleteAccountAction $deleteAccount): RedirectResponse
    {
        $this->authorizeAccount($account);

        $deleteAccount->handle($account);

        return redirect()
            ->route('accounts.index')
            ->with('success', 'Cuenta eliminada exitosamente.');
    }

    private function authorizeAccount(Account $account): void
    {
        if ($account->user_id !== Auth::id()) {
            abort(403);
        }
    }
}
