<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Auth::user()
            ->transactions()
            ->with(['paymentMethod', 'category', 'account', 'linkedTransaction.account']);

        if ($request->filled('payment_method_id')) {
            $query->where('payment_method_id', $request->input('payment_method_id'));
        }

        $accountIds = array_filter((array) $request->input('account_ids', []));
        if ($accountIds) {
            $query->whereIn('account_id', $accountIds);
        }

        $categoryIds = array_filter((array) $request->input('category_ids', []));
        if ($categoryIds) {
            $query->whereIn('category_id', $categoryIds);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->input('date_to'));
        }

        $transactions = $query
            ->latest('transaction_date')
            ->paginate(25)
            ->withQueryString();

        $accounts = Auth::user()
            ->accounts()
            ->where('is_active', true)
            ->get();

        $paymentMethods = Auth::user()
            ->paymentMethods()
            ->where('is_active', true)
            ->get();

        $categories = Category::query()
            ->where(function ($query) {
                $query->whereNull('user_id')
                    ->orWhere('user_id', Auth::id());
            })
            ->whereNull('parent_id')
            ->with('children')
            ->get();

        return Inertia::render('transactions/index', [
            'transactions' => TransactionResource::collection($transactions),
            'accounts' => $accounts->map(fn ($account) => [
                'id' => $account->id,
                'uuid' => $account->uuid,
                'name' => $account->name,
                'type' => $account->type->value,
                'currency' => $account->currency,
                'currency_locale' => Currency::localeFor($account->currency),
                'is_active' => $account->is_active,
                'is_default' => $account->is_default,
            ])->toArray(),
            'paymentMethods' => $paymentMethods->map(fn ($pm) => [
                'id' => $pm->id,
                'uuid' => $pm->uuid,
                'name' => $pm->name,
                'type' => $pm->type->value,
                'currency' => $pm->currency,
                'currency_locale' => Currency::localeFor($pm->currency),
                'is_active' => $pm->is_active,
                'is_default' => $pm->is_default,
            ])->toArray(),
            'categories' => $categories->map(fn ($cat) => [
                'id' => $cat->id,
                'uuid' => $cat->uuid,
                'name' => $cat->name,
                'type' => $cat->type->value,
                'color' => $cat->color,
                'children' => $cat->children->map(fn ($child) => [
                    'id' => $child->id,
                    'uuid' => $child->uuid,
                    'name' => $child->name,
                    'type' => $child->type->value,
                    'color' => $child->color,
                ])->toArray(),
            ])->toArray(),
            'filters' => $request->only(['account_ids', 'category_ids', 'date_from', 'date_to']),
        ]);
    }

    public function store(StoreTransactionRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        if ($validated['type'] === 'transfer') {
            $originAccount = Auth::user()
                ->accounts()
                ->find($validated['origin_account_id']);
            $destinationAccount = Auth::user()
                ->accounts()
                ->find($validated['destination_account_id']);

            if (! $originAccount || ! $destinationAccount) {
                abort(403);
            }

            \DB::transaction(function () use (
                $validated,
                $originAccount,
                $destinationAccount
            ) {
                $outgoing = Auth::user()->transactions()->create([
                    'type' => 'transfer_out',
                    'account_id' => $originAccount->id,
                    'payment_method_id' => null,
                    'category_id' => null,
                    'amount' => $validated['amount'],
                    'currency' => $originAccount->currency,
                    'description' => $validated['description'] ?? 'Transferencia',
                    'transaction_date' => $validated['transaction_date'],
                ]);

                $incoming = Auth::user()->transactions()->create([
                    'type' => 'transfer_in',
                    'account_id' => $destinationAccount->id,
                    'payment_method_id' => null,
                    'category_id' => null,
                    'amount' => $validated['amount'],
                    'currency' => $originAccount->currency,
                    'description' => $validated['description'] ?? 'Transferencia',
                    'transaction_date' => $validated['transaction_date'],
                    'linked_transaction_id' => $outgoing->id,
                ]);

                $outgoing->update([
                    'linked_transaction_id' => $incoming->id,
                ]);

                $this->storeAttachments($outgoing, $validated['attachments'] ?? []);
            });
        } else {
            $transaction = Auth::user()->transactions()->create([
                'type' => $validated['type'],
                'account_id' => $validated['account_id'],
                'payment_method_id' => $validated['payment_method_id'] ?? null,
                'category_id' => $validated['category_id'] ?? null,
                'amount' => $validated['amount'],
                'currency' => $validated['currency'],
                'description' => $validated['description'] ?? null,
                'transaction_date' => $validated['transaction_date'],
            ]);

            $this->storeAttachments($transaction, $validated['attachments'] ?? []);
        }

        return redirect()
            ->route('transactions.index')
            ->with('success', 'Transaccion creada exitosamente.');
    }

    public function update(UpdateTransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        $this->authorizeTransaction($transaction);

        $validated = $request->validated();

        if ($validated['type'] === 'transfer') {
            $originAccount = Auth::user()
                ->accounts()
                ->find($validated['origin_account_id']);
            $destinationAccount = Auth::user()
                ->accounts()
                ->find($validated['destination_account_id']);

            if (! $originAccount || ! $destinationAccount) {
                abort(403);
            }

            \DB::transaction(function () use (
                $validated,
                $transaction,
                $originAccount,
                $destinationAccount
            ) {
                $outgoing = $transaction->type->value === 'transfer_in'
                    ? $transaction->linkedTransaction ?? $transaction->counterpartTransaction
                    : $transaction;
                $incoming = $transaction->type->value === 'transfer_in'
                    ? $transaction
                    : $transaction->linkedTransaction ?? $transaction->counterpartTransaction;

                if (! $outgoing) {
                    $outgoing = Auth::user()->transactions()->create([
                        'type' => 'transfer_out',
                        'account_id' => $originAccount->id,
                        'payment_method_id' => null,
                        'category_id' => null,
                        'amount' => $validated['amount'],
                        'currency' => $originAccount->currency,
                        'description' => $validated['description'] ?? 'Transferencia',
                        'transaction_date' => $validated['transaction_date'],
                    ]);
                } else {
                    $outgoing->update([
                        'type' => 'transfer_out',
                        'account_id' => $originAccount->id,
                        'payment_method_id' => null,
                        'category_id' => null,
                        'amount' => $validated['amount'],
                        'currency' => $originAccount->currency,
                        'description' => $validated['description'] ?? 'Transferencia',
                        'transaction_date' => $validated['transaction_date'],
                    ]);
                }

                if (! $incoming) {
                    $incoming = Auth::user()->transactions()->create([
                        'type' => 'transfer_in',
                        'account_id' => $destinationAccount->id,
                        'payment_method_id' => null,
                        'category_id' => null,
                        'amount' => $validated['amount'],
                        'currency' => $originAccount->currency,
                        'description' => $validated['description'] ?? 'Transferencia',
                        'transaction_date' => $validated['transaction_date'],
                        'linked_transaction_id' => $outgoing->id,
                    ]);
                } else {
                    $incoming->update([
                        'type' => 'transfer_in',
                        'account_id' => $destinationAccount->id,
                        'payment_method_id' => null,
                        'category_id' => null,
                        'amount' => $validated['amount'],
                        'currency' => $originAccount->currency,
                        'description' => $validated['description'] ?? 'Transferencia',
                        'transaction_date' => $validated['transaction_date'],
                        'linked_transaction_id' => $outgoing->id,
                    ]);
                }

                $outgoing->update([
                    'linked_transaction_id' => $incoming->id,
                ]);

                $this->storeAttachments($outgoing, $validated['attachments'] ?? []);
            });
        } else {
            $transaction->update([
                'type' => $validated['type'],
                'account_id' => $validated['account_id'],
                'payment_method_id' => $validated['payment_method_id'] ?? null,
                'category_id' => $validated['category_id'] ?? null,
                'amount' => $validated['amount'],
                'currency' => $validated['currency'],
                'description' => $validated['description'] ?? null,
                'transaction_date' => $validated['transaction_date'],
            ]);

            $this->storeAttachments($transaction, $validated['attachments'] ?? []);
        }

        return redirect()
            ->route('transactions.index')
            ->with('success', 'Transaccion actualizada exitosamente.');
    }

    public function destroy(Transaction $transaction): RedirectResponse
    {
        $this->authorizeTransaction($transaction);

        $transaction->delete();

        return redirect()
            ->route('transactions.index')
            ->with('success', 'Transaccion eliminada exitosamente.');
    }

    private function authorizeTransaction(Transaction $transaction): void
    {
        if ($transaction->user_id !== Auth::id()) {
            abort(403);
        }
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    private function storeAttachments(Transaction $transaction, array $files): void
    {
        foreach ($files as $file) {
            $path = $file->store('attachments', 'public');

            $transaction->attachments()->create([
                'filename' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getClientMimeType() ?? $file->getMimeType() ?? 'application/octet-stream',
                'size' => $file->getSize() ?? 0,
            ]);
        }
    }
}
