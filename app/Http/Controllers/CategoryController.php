<?php

namespace App\Http\Controllers;

use App\Actions\Categories\CreateCategoryAction;
use App\Actions\Categories\DeleteCategoryAction;
use App\Actions\Categories\UpdateCategoryAction;
use App\Enums\TransactionType;
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

        $categories = $user->categories()
            ->with(['children' => function ($query) {
                $query->orderBy('sort_order')->orderBy('name');
            }])
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $countsByCategory = Transaction::query()
            ->where('user_id', $user->id)
            ->where('type', TransactionType::Regular)
            ->whereBetween('transaction_date', [$monthStart->startOfDay(), $monthEnd->endOfDay()])
            ->selectRaw('category_id, COUNT(*) as cnt')
            ->groupBy('category_id')
            ->pluck('cnt', 'category_id');

        $countFor = fn (?int $categoryId): int => $categoryId !== null
            ? (int) $countsByCategory->get($categoryId, 0)
            : 0;

        $list = $categories->map(function (Category $parent) use ($countFor): array {
            $children = $parent->children->map(fn (Category $child): array => [
                'id' => $child->id,
                'uuid' => $child->uuid,
                'parent_id' => $child->parent_id,
                'name' => $child->name,
                'color' => $child->color,
                'emoji' => $child->emoji,
                'transaction_count' => $countFor($child->id),
            ])->values();

            $rolledCount = $countFor($parent->id) + (int) $children->sum('transaction_count');

            return [
                'id' => $parent->id,
                'uuid' => $parent->uuid,
                'parent_id' => null,
                'name' => $parent->name,
                'color' => $parent->color,
                'emoji' => $parent->emoji,
                'transaction_count' => $rolledCount,
                'children' => $children->all(),
            ];
        });

        $inUseCount = $list->filter(fn (array $c) => $c['transaction_count'] > 0)->count();
        $idleCount = $list->count() - $inUseCount;

        $parentOptions = $categories
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
            ],
            'period' => [
                'start' => $monthStart->toDateString(),
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

        $action->handle($category, $request->validated());

        return redirect()->route('categories.index')
            ->with('success', 'Categoría actualizada correctamente.');
    }

    public function destroy(Category $category, DeleteCategoryAction $action): RedirectResponse
    {
        $this->authorizeCategory($category);

        $action->handle($category);

        return redirect()->route('categories.index')
            ->with('success', 'Categoría eliminada correctamente.');
    }

    private function authorizeCategory(Category $category): void
    {
        if ($category->user_id !== Auth::id()) {
            abort(403);
        }
    }
}
