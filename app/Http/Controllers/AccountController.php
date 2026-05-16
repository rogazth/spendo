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
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(): Response
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

        $currencySummaries = $accounts
            ->groupBy('currency')
            ->map(function ($group, string $currency) {
                $list = $group->map(fn (Account $account) => [
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
                    'include_in_budget' => (bool) $account->include_in_budget,
                ])->values();

                $total = (float) $list->sum('current_balance');
                $budgetedTotal = (float) $list->where('include_in_budget', true)->sum('current_balance');
                $excludedTotal = $total - $budgetedTotal;

                return [
                    'currency' => $currency,
                    'currency_locale' => Currency::localeFor($currency),
                    'accounts_count' => $list->count(),
                    'active_count' => $list->where('is_active', true)->count(),
                    'inactive_count' => $list->where('is_active', false)->count(),
                    'included_count' => $list->where('include_in_budget', true)->count(),
                    'excluded_count' => $list->where('include_in_budget', false)->count(),
                    'negative_count' => $list->filter(fn (array $a) => $a['current_balance'] < 0)->count(),
                    'total' => $total,
                    'budgeted_total' => $budgetedTotal,
                    'excluded_total' => $excludedTotal,
                    'accounts' => $list->all(),
                ];
            })
            ->sortKeys()
            ->values()
            ->all();

        $defaultAccount = $accounts->firstWhere('is_default', true);

        $totals = [
            'accounts' => $accounts->count(),
            'active' => $accounts->where('is_active', true)->count(),
            'inactive' => $accounts->where('is_active', false)->count(),
            'included' => $accounts->where('include_in_budget', true)->count(),
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
