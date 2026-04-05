<?php

namespace App\Http\Controllers;

use App\Actions\Budgets\CreateBudgetAction;
use App\Actions\Budgets\DeleteBudgetAction;
use App\Http\Requests\StoreBudgetRequest;
use App\Http\Resources\BudgetResource;
use App\Http\Resources\TransactionResource;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\Category;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class BudgetController extends Controller
{
    public function index(Request $request): Response
    {
        $referenceDate = CarbonImmutable::now()->startOfDay();

        $budgets = Auth::user()
            ->budgets()
            ->with(['items.category.children'])
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $budgets->getCollection()->transform(function (Budget $budget) use ($referenceDate) {
            [$cycleStart, $cycleEnd] = $budget->resolveCycleRange($referenceDate);
            $categoryIds = $this->collectBudgetCategoryIds($budget);
            $spent = $this->calculateBudgetSpent($budget, $cycleStart, $cycleEnd, $categoryIds);
            $totalBudgeted = $budget->total_budgeted;
            $percentage = $totalBudgeted > 0
                ? min(100, round(($spent / $totalBudgeted) * 100, 2))
                : 0;

            $budget->setAttribute('current_cycle_start', $cycleStart->toDateString());
            $budget->setAttribute('current_cycle_end', $cycleEnd->toDateString());
            $budget->setAttribute('current_cycle_spent', $spent);
            $budget->setAttribute('current_cycle_percentage', $percentage);

            return $budget;
        });

        $categories = Category::query()
            ->where(function ($query) {
                $query->whereNull('user_id')
                    ->orWhere('user_id', Auth::id());
            })
            ->where('is_system', false)
            ->whereNull('parent_id')
            ->with([
                'children' => fn ($query) => $query
                    ->where('is_system', false)
                    ->orderBy('sort_order')
                    ->orderBy('name'),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('budgets/index', [
            'budgets' => BudgetResource::collection($budgets),
            'categories' => $categories->map(fn ($category) => [
                'id' => $category->id,
                'uuid' => $category->uuid,
                'name' => $category->name,
                'color' => $category->color,
                'children' => $category->children->map(fn ($child) => [
                    'id' => $child->id,
                    'uuid' => $child->uuid,
                    'name' => $child->name,
                    'color' => $child->color,
                    'parent_id' => $child->parent_id,
                ])->toArray(),
            ])->toArray(),
        ]);
    }

    public function store(StoreBudgetRequest $request, CreateBudgetAction $action): RedirectResponse
    {
        $action->handle(Auth::user(), $request->validated());

        return redirect()
            ->route('budgets.index')
            ->with('success', 'Budget creado exitosamente.');
    }

    public function show(Budget $budget, Request $request): Response
    {
        $this->authorizeBudget($budget);

        $scope = in_array($request->input('scope'), ['current', 'history'], true)
            ? $request->input('scope')
            : 'current';
        $referenceDate = CarbonImmutable::now()->startOfDay();

        $budget->load(['items.category.children']);
        $categoryGroups = $this->budgetItemCategoryGroups($budget);
        $categoryIds = collect($categoryGroups)->flatten()->unique()->values()->all();

        [$cycleStart, $cycleEnd] = $this->resolveCycleRange($budget, $referenceDate);
        $categoryProgress = $this->buildCategoryProgress(
            $budget,
            $cycleStart,
            $cycleEnd,
            $categoryGroups,
        );

        $totalBudgeted = $budget->total_budgeted;
        $totalSpent = collect($categoryProgress)->sum('spent');
        $totalPercentage = $totalBudgeted > 0
            ? min(100, round(($totalSpent / $totalBudgeted) * 100, 2))
            : 0;

        $budget->setAttribute('current_cycle_start', $cycleStart->toDateString());
        $budget->setAttribute('current_cycle_end', $cycleEnd->toDateString());
        $budget->setAttribute('current_cycle_spent', $totalSpent);
        $budget->setAttribute('current_cycle_percentage', $totalPercentage);

        $scopeStart = $cycleStart;
        $scopeEnd = $cycleEnd;
        if ($scope === 'history') {
            [$scopeStart, $scopeEnd] = $this->resolveHistoryRange($budget, $referenceDate);
        }

        $transactions = $this->buildBudgetTransactionsQuery(
            $budget,
            $scopeStart,
            $scopeEnd,
            $categoryIds,
            true,
        )
            ->latest('transaction_date')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('budgets/show', [
            'budget' => (new BudgetResource($budget))->resolve(),
            'summary' => [
                'budgeted' => $totalBudgeted,
                'spent' => $totalSpent,
                'remaining' => $totalBudgeted - $totalSpent,
                'percentage' => $totalPercentage,
                'current_cycle_start' => $cycleStart->toDateString(),
                'current_cycle_end' => $cycleEnd->toDateString(),
            ],
            'categoryProgress' => $categoryProgress,
            'transactions' => TransactionResource::collection($transactions),
            'scope' => $scope,
            'range' => [
                'start' => $scopeStart->toDateString(),
                'end' => $scopeEnd->toDateString(),
            ],
        ]);
    }

    public function destroy(Budget $budget, DeleteBudgetAction $action): RedirectResponse
    {
        $this->authorizeBudget($budget);

        $action->handle($budget);

        return redirect()
            ->route('budgets.index')
            ->with('success', 'Budget eliminado exitosamente.');
    }

    private function authorizeBudget(Budget $budget): void
    {
        if ($budget->user_id !== Auth::id()) {
            abort(403);
        }
    }

    /**
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    private function resolveHistoryRange(Budget $budget, CarbonImmutable $referenceDate): array
    {
        $startDate = CarbonImmutable::parse($budget->anchor_date)->startOfDay();
        $endDate = $referenceDate->startOfDay();

        if ($budget->ends_at !== null) {
            $budgetEndDate = CarbonImmutable::parse($budget->ends_at)->startOfDay();
            if ($endDate->greaterThan($budgetEndDate)) {
                $endDate = $budgetEndDate;
            }
        }

        if ($endDate->lessThan($startDate)) {
            $endDate = $startDate;
        }

        return [$startDate, $endDate];
    }

    /**
     * @return array<int, array<int, int>>
     */
    private function budgetItemCategoryGroups(Budget $budget): array
    {
        return $budget->items->mapWithKeys(function (BudgetItem $item) {
            $category = $item->category;
            if (! $category) {
                return [$item->id => []];
            }

            $categoryIds = [$category->id];
            $childrenIds = $category->relationLoaded('children')
                ? $category->children->pluck('id')->all()
                : $category->children()->pluck('id')->all();

            return [$item->id => array_values(array_unique(array_merge($categoryIds, $childrenIds)))];
        })->all();
    }

    /**
     * @return array<int, int>
     */
    private function collectBudgetCategoryIds(Budget $budget): array
    {
        return collect($this->budgetItemCategoryGroups($budget))
            ->flatten()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function calculateBudgetSpent(
        Budget $budget,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
        array $categoryIds,
    ): float {
        $spentInCents = $this->buildBudgetTransactionsQuery(
            $budget,
            $startDate,
            $endDate,
            $categoryIds,
            false,
        )->sum('amount');

        return $spentInCents / 100;
    }

    /**
     * @param  array<int, array<int, int>>  $categoryGroups
     * @return array<int, array<string, mixed>>
     */
    private function buildCategoryProgress(
        Budget $budget,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
        array $categoryGroups,
    ): array {
        $allCategoryIds = collect($categoryGroups)->flatten()->unique()->values()->all();
        $transactions = $this->buildBudgetTransactionsQuery(
            $budget,
            $startDate,
            $endDate,
            $allCategoryIds,
            false,
        )->get(['category_id', 'amount']);

        $spentByCategoryId = [];
        foreach ($transactions as $transaction) {
            $spentByCategoryId[$transaction->category_id] = ($spentByCategoryId[$transaction->category_id] ?? 0) + $transaction->amount;
        }

        return $budget->items->map(function (BudgetItem $item) use ($categoryGroups, $spentByCategoryId) {
            $groupCategoryIds = $categoryGroups[$item->id] ?? [];
            $spent = collect($groupCategoryIds)
                ->sum(fn ($categoryId) => $spentByCategoryId[$categoryId] ?? 0);
            $remaining = $item->amount - $spent;
            $percentage = $item->amount > 0
                ? min(100, round(($spent / $item->amount) * 100, 2))
                : 0;

            return [
                'id' => $item->id,
                'category_id' => $item->category_id,
                'category_name' => $item->category?->name ?? 'Sin categoría',
                'category_color' => $item->category?->color ?? '#6B7280',
                'budgeted' => $item->amount,
                'spent' => $spent,
                'remaining' => $remaining,
                'percentage' => $percentage,
            ];
        })->values()->all();
    }

    /**
     * @param  array<int, int>  $categoryIds
     */
    private function buildBudgetTransactionsQuery(
        Budget $budget,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
        array $categoryIds,
        bool $withRelations = true
    ): HasMany {
        $query = Auth::user()->transactions();

        if ($withRelations) {
            $query->with(['category', 'account', 'linkedTransaction.account']);
        }

        $query->where('type', 'expense')
            ->where('exclude_from_budget', false)
            ->whereDate('transaction_date', '>=', $startDate->toDateString())
            ->whereDate('transaction_date', '<=', $endDate->toDateString())
            ->where('currency', $budget->currency);

        if ($categoryIds === []) {
            $query->whereRaw('1 = 0');

            return $query;
        }

        $query->whereIn('category_id', $categoryIds);

        return $query;
    }
}
