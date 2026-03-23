<?php

namespace App\Http\Controllers;

use App\Actions\Accounts\CreateAccountAction;
use App\Actions\Accounts\DeleteAccountAction;
use App\Actions\Accounts\UpdateAccountAction;
use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Http\Resources\AccountResource;
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
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('accounts/index', [
            'accounts' => AccountResource::collection($accounts),
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
