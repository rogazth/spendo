<?php

namespace App\Http\Controllers;

use App\Actions\Categories\CreateCategoryAction;
use App\Actions\Categories\DeleteCategoryAction;
use App\Actions\Categories\UpdateCategoryAction;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        $user = Auth::user();
        $today = CarbonImmutable::now();
        $monthStart = $today->startOfMonth();
        $monthEnd = $today->endOfMonth();
        $daysInMonth = $monthEnd->day;

        $categories = Category::query()
            ->where(function ($query) use ($user) {
                $query->whereNull('user_id')
                    ->orWhere('user_id', $user->id);
            })
            ->with(['children' => function ($query) {
                $query->orderBy('sort_order')->orderBy('name');
            }])
            ->whereNull('parent_id')
            ->orderByDesc('user_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $byCategory = Transaction::query()
            ->where('user_id', $user->id)
            ->whereNull('linked_transaction_id')
            ->whereBetween('transaction_date', [$monthStart->startOfDay(), $monthEnd->endOfDay()])
            ->get(['category_id', 'amount', 'transaction_date'])
            ->groupBy('category_id');

        $lastUsedByCategory = Transaction::query()
            ->where('user_id', $user->id)
            ->whereNull('linked_transaction_id')
            ->whereNotNull('category_id')
            ->selectRaw('category_id, MAX(transaction_date) as last_used_at')
            ->groupBy('category_id')
            ->pluck('last_used_at', 'category_id');

        $aggregateFor = function (?int $categoryId) use ($byCategory, $lastUsedByCategory, $daysInMonth): array {
            $items = $categoryId !== null && $byCategory->has($categoryId)
                ? $byCategory->get($categoryId)
                : collect();

            $spent = 0.0;
            $income = 0.0;
            $daily = array_fill(0, $daysInMonth, 0.0);

            foreach ($items as $tx) {
                $amount = (float) $tx->amount;
                if ($amount < 0) {
                    $spent += abs($amount);
                } else {
                    $income += $amount;
                }
                $day = (int) $tx->transaction_date->day;
                $idx = max(0, min($daysInMonth - 1, $day - 1));
                $daily[$idx] += abs($amount);
            }

            $lastUsed = $categoryId !== null
                ? $lastUsedByCategory->get($categoryId)
                : null;

            return [
                'transaction_count' => $items->count(),
                'total_spent' => $spent,
                'total_income' => $income,
                'daily_usage' => $daily,
                'last_used_at' => $lastUsed instanceof \DateTimeInterface
                    ? CarbonImmutable::instance($lastUsed)->toDateString()
                    : ($lastUsed ? CarbonImmutable::parse($lastUsed)->toDateString() : null),
            ];
        };

        $list = $categories->map(function (Category $parent) use ($aggregateFor): array {
            $own = $aggregateFor($parent->id);

            $children = $parent->children->map(function (Category $child) use ($aggregateFor): array {
                $agg = $aggregateFor($child->id);

                return [
                    'id' => $child->id,
                    'uuid' => $child->uuid,
                    'name' => $child->name,
                    'color' => $child->color,
                    'emoji' => $child->emoji,
                    'is_system' => (bool) $child->is_system,
                    'is_user_owned' => $child->user_id !== null,
                    'transaction_count' => $agg['transaction_count'],
                    'total_spent' => $agg['total_spent'],
                    'total_income' => $agg['total_income'],
                    'net' => $agg['total_income'] - $agg['total_spent'],
                    'daily_usage' => $agg['daily_usage'],
                    'last_used_at' => $agg['last_used_at'],
                ];
            })->values();

            $rolledSpent = $own['total_spent'] + (float) $children->sum('total_spent');
            $rolledIncome = $own['total_income'] + (float) $children->sum('total_income');
            $rolledCount = $own['transaction_count'] + (int) $children->sum('transaction_count');

            $rolledDaily = $own['daily_usage'];
            foreach ($children as $childRow) {
                foreach ($childRow['daily_usage'] as $i => $value) {
                    $rolledDaily[$i] += $value;
                }
            }

            $lastUsed = $own['last_used_at'];
            foreach ($children as $childRow) {
                $childLast = $childRow['last_used_at'];
                if ($childLast !== null && ($lastUsed === null || $childLast > $lastUsed)) {
                    $lastUsed = $childLast;
                }
            }

            return [
                'id' => $parent->id,
                'uuid' => $parent->uuid,
                'name' => $parent->name,
                'color' => $parent->color,
                'emoji' => $parent->emoji,
                'is_system' => (bool) $parent->is_system,
                'is_user_owned' => $parent->user_id !== null,
                'transaction_count' => $rolledCount,
                'total_spent' => $rolledSpent,
                'total_income' => $rolledIncome,
                'net' => $rolledIncome - $rolledSpent,
                'daily_usage' => $rolledDaily,
                'last_used_at' => $lastUsed,
                'children' => $children->all(),
            ];
        });

        $totalSpent = (float) $list->sum('total_spent');
        $totalIncome = (float) $list->sum('total_income');
        $inUseCount = $list->filter(fn (array $c) => $c['transaction_count'] > 0)->count();
        $idleCount = $list->count() - $inUseCount;
        $topCategory = $list
            ->filter(fn (array $c) => $c['total_spent'] > 0)
            ->sortByDesc('total_spent')
            ->first();

        $parentOptions = $categories
            ->filter(fn (Category $c) => ! $c->is_system)
            ->map(fn (Category $c) => [
                'id' => $c->id,
                'uuid' => $c->uuid,
                'name' => $c->name,
                'color' => $c->color,
            ])
            ->values()
            ->all();

        return Inertia::render('categories/index', [
            'categories' => $list->values()->all(),
            'parentCategories' => $parentOptions,
            'totals' => [
                'categories' => $list->count(),
                'in_use' => $inUseCount,
                'idle' => $idleCount,
                'total_spent' => $totalSpent,
                'total_income' => $totalIncome,
                'top_category' => $topCategory !== null ? [
                    'name' => $topCategory['name'],
                    'total_spent' => $topCategory['total_spent'],
                ] : null,
            ],
            'period' => [
                'start' => $monthStart->toDateString(),
                'end' => $monthEnd->toDateString(),
                'days' => $daysInMonth,
                'today' => $today->toDateString(),
            ],
        ]);
    }

    public function store(StoreCategoryRequest $request, CreateCategoryAction $action): RedirectResponse
    {
        $action->handle(Auth::user(), $request->validated());

        return redirect()->route('categories.index')
            ->with('success', 'Categoría creada correctamente.');
    }

    public function show(Category $category): Response
    {
        $this->authorizeCategory($category);

        return Inertia::render('categories/show', [
            'category' => new CategoryResource($category->load('children', 'parent')),
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category, UpdateCategoryAction $action): RedirectResponse
    {
        $this->authorizeCategory($category);

        try {
            $action->handle($category, $request->validated());
        } catch (\InvalidArgumentException) {
            abort(403, 'No puedes editar categorías del sistema.');
        }

        return redirect()->route('categories.index')
            ->with('success', 'Categoría actualizada correctamente.');
    }

    public function destroy(Category $category, DeleteCategoryAction $action): RedirectResponse
    {
        $this->authorizeCategory($category);

        try {
            $action->handle($category);
        } catch (\InvalidArgumentException) {
            abort(403, 'No puedes eliminar categorías del sistema.');
        }

        return redirect()->route('categories.index')
            ->with('success', 'Categoría eliminada correctamente.');
    }

    private function authorizeCategory(Category $category): void
    {
        $user = Auth::user();

        if ($category->user_id === null) {
            return;
        }

        if ($category->user_id !== $user->id) {
            abort(403);
        }
    }
}
