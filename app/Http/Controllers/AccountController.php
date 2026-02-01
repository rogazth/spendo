<?php

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Models\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(): Response
    {
        $accounts = Auth::user()
            ->accounts()
            ->withCount('paymentMethods')
            ->latest()
            ->get();

        return Inertia::render('accounts/index', [
            'accounts' => $accounts,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('accounts/create', [
            'accountTypes' => collect(AccountType::cases())->map(fn ($type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ]),
        ]);
    }

    public function store(StoreAccountRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        Auth::user()->accounts()->create([
            'name' => $validated['name'],
            'type' => $validated['type'],
            'currency' => $validated['currency'],
            'initial_balance' => $validated['initial_balance'],
            'color' => $validated['color'] ?? '#3B82F6',
            'icon' => $validated['icon'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return redirect()
            ->route('accounts.index')
            ->with('success', 'Cuenta creada exitosamente.');
    }

    public function show(Account $account): Response
    {
        $this->authorizeAccount($account);

        $account->load(['paymentMethods']);

        $transactions = $account->paymentMethods()
            ->with(['transactions' => fn ($query) => $query
                ->with(['category', 'paymentMethod'])
                ->latest('transaction_date')
                ->limit(50),
            ])
            ->get()
            ->pluck('transactions')
            ->flatten()
            ->sortByDesc('transaction_date')
            ->take(50)
            ->values();

        return Inertia::render('accounts/show', [
            'account' => $account,
            'transactions' => $transactions,
        ]);
    }

    public function edit(Account $account): Response
    {
        $this->authorizeAccount($account);

        return Inertia::render('accounts/edit', [
            'account' => $account,
            'accountTypes' => collect(AccountType::cases())->map(fn ($type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ]),
        ]);
    }

    public function update(UpdateAccountRequest $request, Account $account): RedirectResponse
    {
        $this->authorizeAccount($account);

        $validated = $request->validated();

        $account->update([
            'name' => $validated['name'],
            'type' => $validated['type'],
            'currency' => $validated['currency'],
            'is_active' => $validated['is_active'] ?? $account->is_active,
        ]);

        return redirect()
            ->route('accounts.index')
            ->with('success', 'Cuenta actualizada exitosamente.');
    }

    public function destroy(Account $account): RedirectResponse
    {
        $this->authorizeAccount($account);

        $account->delete();

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
