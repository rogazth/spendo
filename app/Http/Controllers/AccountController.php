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
            ->with(['budgets:id,uuid,name,color,emoji'])
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
                $budgetGroups = [];
                $unbudgetedAccounts = [];

                // An account belongs to at most one budget today. Accounts sharing a
                // budget are grouped so the budget's caps are shown once, not duplicated.
                foreach ($group->groupBy(fn (Account $a) => $a->budgets->first()?->id ?? 0) as $budgetId => $accountsInGroup) {
                    if ($budgetId === 0) {
                        foreach ($accountsInGroup as $account) {
                            $row = $accountToArray($account);
                            $row['available'] = $row['current_balance'];
                            $unbudgetedAccounts[] = $row;
                        }

                        continue;
                    }

                    $budget = $accountsInGroup->first()->budgets->first();
                    $metrics = $metricsByBudgetId[$budgetId] ?? null;

                    $budgeted = $metrics ? (float) $metrics['budgeted'] : 0.0;
                    $spent = $metrics ? (float) $metrics['spent'] : 0.0;
                    $reserved = $metrics ? (float) $metrics['reserved'] : 0.0;
                    $overspend = $metrics ? (float) $metrics['overspend_amount'] : 0.0;

                    $rows = $accountsInGroup->map($accountToArray)->values();
                    $groupTotal = (float) $rows->sum('current_balance');

                    $budgetGroups[] = [
                        'budget' => [
                            'uuid' => $budget->uuid,
                            'name' => $budget->name,
                            'color' => $budget->color,
                            'emoji' => $budget->emoji,
                        ],
                        'budgeted' => $budgeted,
                        'spent' => $spent,
                        'reserved' => $reserved,
                        'overspend' => $overspend,
                        'percentage' => $budgeted > 0 ? min(100, round(($spent / $budgeted) * 100, 2)) : 0,
                        'total' => $groupTotal,
                        'available' => $groupTotal - $reserved,
                        'accounts' => $rows->all(),
                    ];
                }

                $total = (float) $group->sum(fn (Account $a) => ((int) ($a->getAttribute('balance_cents') ?? 0)) / 100);
                $reservedTotal = (float) collect($budgetGroups)->sum('reserved');
                $budgetedTotal = (float) collect($budgetGroups)->sum('budgeted');

                return [
                    'currency' => $currency,
                    'currency_locale' => Currency::localeFor($currency),
                    'accounts_count' => $group->count(),
                    'total' => $total,
                    'budgeted_total' => $budgetedTotal,
                    'reserved_total' => $reservedTotal,
                    // Free cash for the currency = balance not still reserved by budgets.
                    'available' => $total - $reservedTotal,
                    'budget_groups' => $budgetGroups,
                    'unbudgeted_accounts' => $unbudgetedAccounts,
                ];
            })
            ->sortKeys()
            ->values()
            ->all();

        $defaultAccount = $accounts->firstWhere('is_default', true);

        $totals = [
            'accounts' => $accounts->count(),
            'budgeted' => $accounts->filter(fn (Account $a) => $a->budgets->isNotEmpty())->count(),
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
