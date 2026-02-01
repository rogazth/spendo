<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
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
            ->with(['paymentMethod', 'category']);

        if ($request->filled('payment_method_id')) {
            $query->where('payment_method_id', $request->input('payment_method_id'));
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
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

        $paymentMethods = Auth::user()
            ->paymentMethods()
            ->where('is_active', true)
            ->get();

        $categories = Auth::user()
            ->categories()
            ->whereNull('parent_id')
            ->with('children')
            ->get()
            ->merge(
                Category::query()
                    ->whereNull('user_id')
                    ->where('is_system', true)
                    ->whereNull('parent_id')
                    ->with('children')
                    ->get()
            );

        return Inertia::render('transactions/index', [
            'transactions' => $transactions,
            'paymentMethods' => $paymentMethods,
            'categories' => $categories,
            'filters' => $request->only(['payment_method_id', 'category_id', 'date_from', 'date_to']),
        ]);
    }

    public function create(): Response
    {
        $paymentMethods = Auth::user()
            ->paymentMethods()
            ->where('is_active', true)
            ->get();

        $categories = Auth::user()
            ->categories()
            ->whereNull('parent_id')
            ->with('children')
            ->get()
            ->merge(
                Category::query()
                    ->whereNull('user_id')
                    ->where('is_system', true)
                    ->whereNull('parent_id')
                    ->with('children')
                    ->get()
            );

        return Inertia::render('transactions/create', [
            'paymentMethods' => $paymentMethods,
            'categories' => $categories,
        ]);
    }

    public function store(StoreTransactionRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        Auth::user()->transactions()->create([
            'payment_method_id' => $validated['payment_method_id'],
            'category_id' => $validated['category_id'] ?? null,
            'amount' => $validated['amount'],
            'currency' => $validated['currency'],
            'description' => $validated['description'],
            'transaction_date' => $validated['transaction_date'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()
            ->route('transactions.index')
            ->with('success', 'Transaccion creada exitosamente.');
    }

    public function edit(Transaction $transaction): Response
    {
        $this->authorizeTransaction($transaction);

        $paymentMethods = Auth::user()
            ->paymentMethods()
            ->where('is_active', true)
            ->get();

        $categories = Auth::user()
            ->categories()
            ->whereNull('parent_id')
            ->with('children')
            ->get()
            ->merge(
                Category::query()
                    ->whereNull('user_id')
                    ->where('is_system', true)
                    ->whereNull('parent_id')
                    ->with('children')
                    ->get()
            );

        return Inertia::render('transactions/edit', [
            'transaction' => $transaction->load(['paymentMethod', 'category']),
            'paymentMethods' => $paymentMethods,
            'categories' => $categories,
        ]);
    }

    public function update(UpdateTransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        $this->authorizeTransaction($transaction);

        $validated = $request->validated();

        $transaction->update([
            'payment_method_id' => $validated['payment_method_id'],
            'category_id' => $validated['category_id'] ?? null,
            'amount' => $validated['amount'],
            'currency' => $validated['currency'],
            'description' => $validated['description'],
            'transaction_date' => $validated['transaction_date'],
            'notes' => $validated['notes'] ?? null,
        ]);

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
}
