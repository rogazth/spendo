<?php

namespace App\Http\Controllers;

use App\Actions\Budgets\CreateBudgetAction;
use App\Actions\Budgets\DeleteBudgetAction;
use App\Actions\Budgets\UpdateBudgetAction;
use App\Http\Requests\StoreBudgetRequest;
use App\Http\Resources\BudgetResource;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\Currency;
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
        $cycleStartDay = (int) (Auth::user()->settings?->budget_cycle_start_day ?? 1);

        $budgets = Auth::user()
            ->budgets()
            ->with(['items.category.children', 'accounts'])
            ->latest()
            ->get();

        $budgets->transform(function (Budget $budget) use ($referenceDate, $cycleStartDay) {
            [$cycleStart, $cycleEnd] = $budget->resolveCycleRange($referenceDate, $cycleStartDay);
            $spent = $this->calculateBudgetSpent($budget, $cycleStart, $cycleEnd);
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

        $summary = $budgets
            ->groupBy('currency')
            ->map(function ($group) {
                $budgeted = (float) $group->sum('total_budgeted');
                $spent = (float) $group->sum('current_cycle_spent');

                return [
                    'budgeted' => $budgeted,
                    'spent' => $spent,
                    'remaining' => (float) ($budgeted - $spent),
                    'currency_locale' => Currency::localeFor($group->first()->currency),
                ];
            })
            ->toArray();

        $accounts = Auth::user()
            ->accounts()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $categories = Auth::user()->categories()
            ->whereNull('parent_id')
            ->with([
                'children' => fn ($query) => $query
                    ->orderBy('sort_order')
                    ->orderBy('name'),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('budgets/index', [
            'budgets' => BudgetResource::collection($budgets),
            'summary' => $summary,
            'accounts' => $accounts->map(fn ($account) => [
                'id' => $account->id,
                'uuid' => $account->uuid,
                'name' => $account->name,
                'currency' => $account->currency,
                'currency_locale' => Currency::localeFor($account->currency),
                'color' => $account->color,
                'emoji' => $account->emoji,
                'is_active' => $account->is_active,
                'is_default' => $account->is_default,
            ])->toArray(),
            'categories' => $categories->map(fn ($category) => [
                'id' => $category->id,
                'uuid' => $category->uuid,
                'name' => $category->name,
                'emoji' => $category->emoji,
                'color' => $category->color,
                'children' => $category->children->map(fn ($child) => [
                    'id' => $child->id,
                    'uuid' => $child->uuid,
                    'name' => $child->name,
                    'emoji' => $child->emoji,
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

    public function show(Budget $budget): Response
    {
        $this->authorizeBudget($budget);

        $referenceDate = CarbonImmutable::now()->startOfDay();
        $cycleStartDay = (int) (Auth::user()->settings?->budget_cycle_start_day ?? 1);

        $budget->load(['items.category.children', 'accounts']);
        $categoryGroups = $budget->budgetCategoryGroups();

        [$cycleStart, $cycleEnd] = $budget->resolveCycleRange($referenceDate, $cycleStartDay);
        $categoryProgress = $this->buildCategoryProgress(
            $budget,
            $cycleStart,
            $cycleEnd,
            $categoryGroups,
        );

        $totalBudgeted = (float) $budget->total_budgeted;
        $totalSpent = (float) collect($categoryProgress)->sum('spent');
        $totalPercentage = $totalBudgeted > 0
            ? min(100, round(($totalSpent / $totalBudgeted) * 100, 2))
            : 0;

        $budget->setAttribute('current_cycle_start', $cycleStart->toDateString());
        $budget->setAttribute('current_cycle_end', $cycleEnd->toDateString());
        $budget->setAttribute('current_cycle_spent', $totalSpent);
        $budget->setAttribute('current_cycle_percentage', $totalPercentage);

        $categories = Auth::user()->categories()
            ->whereNull('parent_id')
            ->with([
                'children' => fn ($query) => $query
                    ->orderBy('sort_order')
                    ->orderBy('name'),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $accounts = Auth::user()
            ->accounts()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return Inertia::render('budgets/show', [
            'budget' => (new BudgetResource($budget))->resolve(),
            'summary' => [
                'budgeted' => $totalBudgeted,
                'spent' => $totalSpent,
                'remaining' => $totalBudgeted - $totalSpent,
                'percentage' => $totalPercentage,
                'currency_locale' => Currency::localeFor($budget->currency),
                'current_cycle_start' => $cycleStart->toDateString(),
                'current_cycle_end' => $cycleEnd->toDateString(),
            ],
            'categoryProgress' => $categoryProgress,
            'accounts' => $accounts->map(fn ($account) => [
                'id' => $account->id,
                'uuid' => $account->uuid,
                'name' => $account->name,
                'currency' => $account->currency,
                'currency_locale' => Currency::localeFor($account->currency),
                'color' => $account->color,
                'emoji' => $account->emoji,
                'is_active' => $account->is_active,
                'is_default' => $account->is_default,
            ])->toArray(),
            'categories' => $categories->map(fn ($category) => [
                'id' => $category->id,
                'uuid' => $category->uuid,
                'name' => $category->name,
                'emoji' => $category->emoji,
                'color' => $category->color,
                'children' => $category->children->map(fn ($child) => [
                    'id' => $child->id,
                    'uuid' => $child->uuid,
                    'name' => $child->name,
                    'emoji' => $child->emoji,
                    'color' => $child->color,
                    'parent_id' => $child->parent_id,
                ])->toArray(),
            ])->toArray(),
        ]);
    }

    public function update(StoreBudgetRequest $request, Budget $budget, UpdateBudgetAction $action): RedirectResponse
    {
        $this->authorizeBudget($budget);

        $action->handle($budget, Auth::user(), $request->validated());

        return redirect()
            ->route('budgets.show', $budget)
            ->with('success', 'Budget actualizado exitosamente.');
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

    private function calculateBudgetSpent(
        Budget $budget,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
    ): float {
        $totalInCents = (int) ($this->buildBudgetTransactionsQuery(
            $budget,
            $startDate,
            $endDate,
            false,
        )->selectRaw('COALESCE(SUM(-amount), 0) as total')->value('total') ?? 0);

        return $totalInCents / 100;
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
        $transactions = $this->buildBudgetTransactionsQuery(
            $budget,
            $startDate,
            $endDate,
            false,
        )->get(['category_id', 'amount']);

        $spentByCategoryId = [];
        foreach ($transactions as $transaction) {
            $spentByCategoryId[$transaction->category_id] = ($spentByCategoryId[$transaction->category_id] ?? 0) + abs($transaction->amount);
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
                'category_emoji' => $item->category?->emoji ?? '💰',
                'budgeted' => (float) $item->amount,
                'spent' => (float) $spent,
                'remaining' => (float) $remaining,
                'percentage' => $percentage,
            ];
        })->values()->all();
    }

    private function buildBudgetTransactionsQuery(
        Budget $budget,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
        bool $withRelations = true
    ): HasMany {
        $query = Auth::user()->transactions();

        if ($withRelations) {
            $query->with(['category', 'account', 'linkedTransaction.account']);
        }

        $query->forBudgetSpending($budget, $startDate, $endDate);

        return $query;
    }
}
