<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    public function index(Request $request): Response
    {
        $instrumentIds = $this->extractIdFilter($request, 'instrument_ids');
        $accountIds = $this->extractIdFilter($request, 'account_ids');
        $categoryIds = $this->extractIdFilter($request, 'category_ids');
        $budgetId = $request->input('budget_id') ? (int) $request->input('budget_id') : null;

        // Budget filter: scope by the budget's categories and account
        $activeBudget = null;
        $budgetAccountId = null;

        if ($budgetId) {
            $activeBudget = Auth::user()
                ->budgets()
                ->with('items')
                ->find($budgetId);

            if ($activeBudget) {
                $categoryIds = $activeBudget->items->pluck('category_id')->toArray();

                if ($activeBudget->account_id) {
                    $budgetAccountId = $activeBudget->account_id;
                    $accountIds = [$activeBudget->account_id];
                }
            }
        }

        $query = Auth::user()
            ->transactions()
            ->with(['instrument', 'fromInstrument', 'category', 'account', 'linkedTransaction.account']);

        if ($instrumentIds) {
            $query->whereIn('instrument_id', $instrumentIds);
        }

        if ($accountIds) {
            $query->whereIn('account_id', $accountIds);
        }

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

        $instruments = Auth::user()
            ->instruments()
            ->where('is_active', true)
            ->get();

        $budgets = Auth::user()
            ->budgets()
            ->orderBy('name')
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
                'currency' => $account->currency,
                'currency_locale' => Currency::localeFor($account->currency),
                'is_active' => $account->is_active,
                'is_default' => $account->is_default,
            ])->toArray(),
            'instruments' => $instruments->map(fn ($instrument) => [
                'id' => $instrument->id,
                'uuid' => $instrument->uuid,
                'name' => $instrument->name,
                'type' => $instrument->type->value,
                'currency' => $instrument->currency,
                'currency_locale' => Currency::localeFor($instrument->currency),
                'is_active' => $instrument->is_active,
                'is_default' => $instrument->is_default,
            ])->toArray(),
            'budgets' => $budgets->map(fn ($budget) => [
                'id' => $budget->id,
                'uuid' => $budget->uuid,
                'name' => $budget->name,
                'account_id' => $budget->account_id,
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
            'filters' => [
                'budget_id' => $budgetId,
                'budget_account_id' => $budgetAccountId,
                'instrument_ids' => $instrumentIds,
                'account_ids' => $request->input('budget_id') ? [] : $this->extractIdFilter($request, 'account_ids'),
                'category_ids' => $categoryIds,
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
            ],
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

            \DB::transaction(function () use ($validated, $originAccount, $destinationAccount) {
                $outgoing = Auth::user()->transactions()->create([
                    'type' => 'transfer_out',
                    'account_id' => $originAccount->id,
                    'instrument_id' => null,
                    'category_id' => null,
                    'exclude_from_budget' => false,
                    'amount' => $validated['amount'],
                    'currency' => $originAccount->currency,
                    'description' => $validated['description'] ?? 'Transferencia',
                    'transaction_date' => $validated['transaction_date'],
                ]);

                $incoming = Auth::user()->transactions()->create([
                    'type' => 'transfer_in',
                    'account_id' => $destinationAccount->id,
                    'instrument_id' => null,
                    'category_id' => null,
                    'exclude_from_budget' => false,
                    'amount' => $validated['amount'],
                    'currency' => $originAccount->currency,
                    'description' => $validated['description'] ?? 'Transferencia',
                    'transaction_date' => $validated['transaction_date'],
                    'linked_transaction_id' => $outgoing->id,
                ]);

                $outgoing->update(['linked_transaction_id' => $incoming->id]);

                $this->storeAttachments($outgoing, $validated['attachments'] ?? []);
            });
        } elseif ($validated['type'] === 'settlement') {
            $instrumentId = $validated['instrument_id'] ?? null;
            $fromInstrumentId = $validated['from_instrument_id'] ?? null;

            if ($instrumentId) {
                $instrument = Auth::user()->instruments()->find($instrumentId);
                if (! $instrument) {
                    abort(403);
                }
            }

            if ($fromInstrumentId) {
                $fromInstrument = Auth::user()->instruments()->find($fromInstrumentId);
                if (! $fromInstrument) {
                    abort(403);
                }
            }

            $transaction = Auth::user()->transactions()->create([
                'type' => 'settlement',
                'account_id' => null,
                'instrument_id' => $instrumentId,
                'from_instrument_id' => $fromInstrumentId,
                'category_id' => $validated['category_id'] ?? null,
                'exclude_from_budget' => true,
                'amount' => $validated['amount'],
                'currency' => $validated['currency'],
                'description' => $validated['description'] ?? null,
                'transaction_date' => $validated['transaction_date'],
            ]);

            $this->storeAttachments($transaction, $validated['attachments'] ?? []);
        } else {
            $account = Auth::user()->accounts()->find($validated['account_id']);

            if (! $account) {
                abort(403);
            }

            $instrumentId = $validated['instrument_id'] ?? null;

            if ($instrumentId !== null) {
                $instrument = Auth::user()->instruments()->find($instrumentId);
                if (! $instrument) {
                    abort(403);
                }
            }

            $transaction = Auth::user()->transactions()->create([
                'type' => $validated['type'],
                'account_id' => $account->id,
                'instrument_id' => $instrumentId,
                'category_id' => $validated['category_id'] ?? null,
                'exclude_from_budget' => $validated['exclude_from_budget'] ?? false,
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

            \DB::transaction(function () use ($validated, $transaction, $originAccount, $destinationAccount) {
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
                        'instrument_id' => null,
                        'category_id' => null,
                        'exclude_from_budget' => false,
                        'amount' => $validated['amount'],
                        'currency' => $originAccount->currency,
                        'description' => $validated['description'] ?? 'Transferencia',
                        'transaction_date' => $validated['transaction_date'],
                    ]);
                } else {
                    $outgoing->update([
                        'type' => 'transfer_out',
                        'account_id' => $originAccount->id,
                        'instrument_id' => null,
                        'category_id' => null,
                        'exclude_from_budget' => false,
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
                        'instrument_id' => null,
                        'category_id' => null,
                        'exclude_from_budget' => false,
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
                        'instrument_id' => null,
                        'category_id' => null,
                        'exclude_from_budget' => false,
                        'amount' => $validated['amount'],
                        'currency' => $originAccount->currency,
                        'description' => $validated['description'] ?? 'Transferencia',
                        'transaction_date' => $validated['transaction_date'],
                        'linked_transaction_id' => $outgoing->id,
                    ]);
                }

                $outgoing->update(['linked_transaction_id' => $incoming->id]);

                $this->storeAttachments($outgoing, $validated['attachments'] ?? []);
            });
        } else {
            $transaction->update([
                'type' => $validated['type'],
                'account_id' => $validated['account_id'] ?? null,
                'instrument_id' => $validated['instrument_id'] ?? null,
                'from_instrument_id' => $validated['from_instrument_id'] ?? null,
                'category_id' => $validated['category_id'] ?? null,
                'exclude_from_budget' => $validated['exclude_from_budget'] ?? false,
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

        \DB::transaction(function () use ($transaction) {
            $transaction->delete();

            // Also soft-delete the linked transfer leg to prevent orphaned transfers
            if ($transaction->linked_transaction_id !== null) {
                Transaction::query()
                    ->find($transaction->linked_transaction_id)
                    ?->delete();
            }
        });

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
     * @return array<int, int>
     */
    private function extractIdFilter(Request $request, string $multiKey): array
    {
        $raw = $request->input($multiKey, []);

        if (! is_array($raw)) {
            $raw = [$raw];
        }

        return collect($raw)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value) => (int) $value)
            ->filter(fn ($value) => $value > 0)
            ->unique()
            ->values()
            ->all();
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
