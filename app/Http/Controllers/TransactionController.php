<?php

namespace App\Http\Controllers;

use App\Actions\Transactions\CreateExpenseAction;
use App\Actions\Transactions\CreateIncomeAction;
use App\Actions\Transactions\CreateTransferAction;
use App\Actions\Transactions\DeleteTransactionAction;
use App\Actions\Transactions\UpdateTransactionAction;
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
        $accountIds = $this->extractIdFilter($request, 'account_ids');
        $categoryIds = $this->extractIdFilter($request, 'category_ids');
        $tagIds = $this->extractIdFilter($request, 'tag_ids');
        $budgetId = $request->input('budget_id') ? (int) $request->input('budget_id') : null;

        // Budget filter: scope by the budget's categories
        $activeBudget = null;

        if ($budgetId) {
            $activeBudget = Auth::user()
                ->budgets()
                ->with('items')
                ->find($budgetId);

            if ($activeBudget) {
                $categoryIds = $activeBudget->items->pluck('category_id')->toArray();
            }
        }

        $query = Auth::user()
            ->transactions()
            ->with(['category', 'account', 'tags', 'linkedTransaction.account']);

        if ($accountIds) {
            $query->whereIn('account_id', $accountIds);
        }

        if ($categoryIds) {
            $query->whereIn('category_id', $categoryIds);
        }

        if ($tagIds) {
            $query->whereHas('tags', fn ($q) => $q->whereIn('tags.id', $tagIds));
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
            'budgets' => $budgets->map(fn ($budget) => [
                'id' => $budget->id,
                'uuid' => $budget->uuid,
                'name' => $budget->name,
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
                'account_ids' => $request->input('budget_id') ? [] : $this->extractIdFilter($request, 'account_ids'),
                'category_ids' => $categoryIds,
                'tag_ids' => $tagIds,
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
            ],
        ]);
    }

    public function store(StoreTransactionRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        if ($validated['type'] === 'transfer') {
            $transaction = app(CreateTransferAction::class)->handle(Auth::user(), $validated);
            [$outgoing] = $transaction;
            $this->storeAttachments($outgoing, $validated['attachments'] ?? []);
        } elseif ($validated['type'] === 'income') {
            $transaction = app(CreateIncomeAction::class)->handle(Auth::user(), $validated);
            $this->storeAttachments($transaction, $validated['attachments'] ?? []);
        } else {
            $transaction = app(CreateExpenseAction::class)->handle(Auth::user(), $validated);
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

        $transaction = app(UpdateTransactionAction::class)->handle($transaction, Auth::user(), $validated);

        $this->storeAttachments($transaction, $validated['attachments'] ?? []);

        return redirect()
            ->route('transactions.index')
            ->with('success', 'Transaccion actualizada exitosamente.');
    }

    public function destroy(Transaction $transaction): RedirectResponse
    {
        $this->authorizeTransaction($transaction);

        app(DeleteTransactionAction::class)->handle($transaction);

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
