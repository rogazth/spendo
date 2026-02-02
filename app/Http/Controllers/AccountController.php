<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Http\Resources\AccountResource;
use App\Http\Resources\TransactionResource;
use App\Models\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('accounts/index', [
            'accounts' => AccountResource::collection($accounts),
        ]);
    }

    public function store(StoreAccountRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $isDefault = $validated['is_default'] ?? false;
        $initialBalance = $validated['initial_balance'] ?? 0;

        DB::transaction(function () use ($validated, $isDefault, $initialBalance) {
            if ($isDefault) {
                Auth::user()->accounts()->update(['is_default' => false]);
            }

            $account = Auth::user()->accounts()->create([
                'name' => $validated['name'],
                'type' => $validated['type'],
                'currency' => $validated['currency'],
                'color' => $validated['color'] ?? '#3B82F6',
                'icon' => $validated['icon'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
                'is_default' => $isDefault,
            ]);

            if ($initialBalance > 0) {
                Auth::user()->transactions()->create([
                    'type' => TransactionType::Income,
                    'account_id' => $account->id,
                    'amount' => $initialBalance,
                    'currency' => $account->currency,
                    'description' => 'Balance inicial',
                    'transaction_date' => now(),
                ]);
            }
        });

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
            'account' => new AccountResource($account),
            'transactions' => TransactionResource::collection($transactions),
        ]);
    }

    public function update(UpdateAccountRequest $request, Account $account): RedirectResponse
    {
        $this->authorizeAccount($account);

        $validated = $request->validated();
        $isDefault = $validated['is_default'] ?? $account->is_default;

        if ($isDefault && ! $account->is_default) {
            Auth::user()->accounts()->where('id', '!=', $account->id)->update(['is_default' => false]);
        }

        $account->update([
            'name' => $validated['name'],
            'type' => $validated['type'],
            'currency' => $validated['currency'],
            'is_active' => $validated['is_active'] ?? $account->is_active,
            'is_default' => $isDefault,
        ]);

        return redirect()
            ->route('accounts.index')
            ->with('success', 'Cuenta actualizada exitosamente.');
    }

    public function makeDefault(Account $account): RedirectResponse
    {
        $this->authorizeAccount($account);

        if (! $account->is_default) {
            Auth::user()->accounts()->where('id', '!=', $account->id)->update(['is_default' => false]);
            $account->update(['is_default' => true]);
        }

        return back();
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
