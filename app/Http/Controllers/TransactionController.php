<?php

namespace App\Http\Controllers;

use App\Actions\Transactions\CreateTransactionAction;
use App\Actions\Transactions\CreateTransferAction;
use App\Actions\Transactions\DeleteTransactionAction;
use App\Actions\Transactions\UpdateTransactionAction;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\StoreTransferRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
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
        $user = Auth::user();
        $accountIds = $this->extractIdFilter($request, 'account_ids');
        $categoryIds = $this->extractIdFilter($request, 'category_ids');
        $tagIds = $this->extractIdFilter($request, 'tag_ids');
        $budgetId = $request->input('budget_id') ? (int) $request->input('budget_id') : null;
        $datesAll = $request->input('dates') === 'all';

        if (empty($accountIds)) {
            $defaultAccount = $user
                ->accounts()
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->first();

            if ($defaultAccount) {
                $accountIds = [$defaultAccount->id];
            }
        }

        $activeBudget = null;
        $budgetDateRangeApplied = false;
        $resolvedDateFrom = $request->input('date_from');
        $resolvedDateTo = $request->input('date_to');

        if ($budgetId) {
            $activeBudget = $user
                ->budgets()
                ->with('items.category.children')
                ->find($budgetId);

            if ($activeBudget) {
                $categoryIds = $activeBudget->budgetCategoryIds();
            }
        }

        $query = $user
            ->transactions()
            ->with(['category', 'account', 'tags', 'linkedTransaction.account']);

        if ($activeBudget) {
            if ($datesAll) {
                $query->forBudgetSpending($activeBudget);
            } elseif ($request->filled('date_from') || $request->filled('date_to')) {
                $query->forBudgetSpending($activeBudget);
            } else {
                [$cycleStart, $cycleEnd] = $activeBudget->resolveCycleRange(CarbonImmutable::now()->startOfDay());
                $query->forBudgetSpending($activeBudget, $cycleStart, $cycleEnd);
                $budgetDateRangeApplied = true;
                $resolvedDateFrom = $cycleStart->toDateString();
                $resolvedDateTo = $cycleEnd->toDateString();
            }
        } elseif (! $datesAll && ! $request->filled('date_from') && ! $request->filled('date_to')) {
            [$cycleStart, $cycleEnd] = $user->resolveCurrentCycleRange(CarbonImmutable::now()->startOfDay());
            $query->whereBetween('transaction_date', [$cycleStart->startOfDay(), $cycleEnd->endOfDay()]);
            $budgetDateRangeApplied = true;
            $resolvedDateFrom = $cycleStart->toDateString();
            $resolvedDateTo = $cycleEnd->toDateString();
        }

        if ($accountIds) {
            $query->whereIn('account_id', $accountIds);
        }

        if ($categoryIds && ! $activeBudget) {
            $expandedCategoryIds = $this->expandCategoryIdsWithChildren($categoryIds);
            $query->whereIn('category_id', $expandedCategoryIds);
        }

        if ($tagIds) {
            $query->whereHas('tags', fn ($q) => $q->whereIn('tags.id', $tagIds));
        }

        if (! $budgetDateRangeApplied && $request->filled('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->input('date_from'));
        }

        if (! $budgetDateRangeApplied && $request->filled('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->input('date_to'));
        }

        $summary = $this->buildCurrencySummary($query);

        $transactions = $query
            ->latest('transaction_date')
            ->paginate(25)
            ->withQueryString();

        $accounts = $user
            ->accounts()
            ->where('is_active', true)
            ->get();

        $budgets = $user
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
            'transactions' => Inertia::scroll(TransactionResource::collection($transactions)),
            'summary' => $summary,
            'accounts' => $accounts->map(fn ($account) => [
                'id' => $account->id,
                'uuid' => $account->uuid,
                'name' => $account->name,
                'currency' => $account->currency,
                'currency_locale' => Currency::localeFor($account->currency),
                'is_active' => $account->is_active,
                'is_default' => $account->is_default,
                'emoji' => $account->emoji,
                'color' => $account->color,
                'current_balance' => $account->current_balance,
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
                'color' => $cat->color,
                'emoji' => $cat->emoji,
                'is_system' => $cat->is_system,
                'children' => $cat->children->map(fn ($child) => [
                    'id' => $child->id,
                    'uuid' => $child->uuid,
                    'name' => $child->name,
                    'color' => $child->color,
                    'emoji' => $child->emoji,
                ])->toArray(),
            ])->toArray(),
            'filters' => [
                'budget_id' => $budgetId,
                'account_ids' => $accountIds,
                'category_ids' => $categoryIds,
                'tag_ids' => $tagIds,
                'date_from' => $resolvedDateFrom,
                'date_to' => $resolvedDateTo,
                'dates' => $datesAll ? 'all' : null,
            ],
        ]);
    }

    public function store(StoreTransactionRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $transaction = app(CreateTransactionAction::class)->handle(Auth::user(), $validated);

        $this->storeAttachments($transaction, $validated['attachments'] ?? []);

        return redirect()
            ->route('transactions.index')
            ->with('success', 'Transaccion creada exitosamente.');
    }

    public function storeTransfer(StoreTransferRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        [$outgoing] = app(CreateTransferAction::class)->handle(Auth::user(), $validated);

        $this->storeAttachments($outgoing, $validated['attachments'] ?? []);

        return redirect()
            ->route('transactions.index')
            ->with('success', 'Transferencia creada exitosamente.');
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
     * @param  \Illuminate\Database\Eloquent\Builder<Transaction>|\Illuminate\Database\Eloquent\Relations\HasMany<Transaction, \App\Models\User>  $query
     * @return array<string, array{income: float, expenses: float, net: float, currency_locale: string}>
     */
    private function buildCurrencySummary($query): array
    {
        $rows = (clone $query)
            ->whereNull('linked_transaction_id')
            ->select('currency')
            ->selectRaw('COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END), 0) as income_cents')
            ->selectRaw('COALESCE(SUM(CASE WHEN amount < 0 THEN -amount ELSE 0 END), 0) as expense_cents')
            ->groupBy('currency')
            ->get();

        $summary = [];

        foreach ($rows as $row) {
            $income = ((int) $row->income_cents) / 100;
            $expenses = ((int) $row->expense_cents) / 100;

            $summary[$row->currency] = [
                'income' => $income,
                'expenses' => $expenses,
                'net' => $income - $expenses,
                'currency_locale' => Currency::localeFor($row->currency),
            ];
        }

        ksort($summary);

        return $summary;
    }

    /**
     * Expand a list of category IDs to include the children of any parent categories.
     *
     * @param  array<int, int>  $categoryIds
     * @return array<int, int>
     */
    private function expandCategoryIdsWithChildren(array $categoryIds): array
    {
        $childIds = Category::query()
            ->whereIn('parent_id', $categoryIds)
            ->where(function ($query) {
                $query->whereNull('user_id')
                    ->orWhere('user_id', Auth::id());
            })
            ->pluck('id')
            ->all();

        return array_values(array_unique(array_merge($categoryIds, $childIds)));
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
