<?php

namespace App\Http\Controllers;

use App\Actions\Categories\CreateCategoryAction;
use App\Actions\Categories\DeleteCategoryAction;
use App\Actions\Categories\UpdateCategoryAction;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        $user = Auth::user();

        $categories = Category::query()
            ->where(function ($query) use ($user) {
                $query->whereNull('user_id')
                    ->orWhere('user_id', $user->id);
            })
            ->with('children')
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $userCategories = $categories->filter(fn ($c) => ! $c->is_system)->values();
        $systemCategories = $categories->filter(fn ($c) => $c->is_system)->values();

        $parentCategories = $userCategories->map(fn ($c) => [
            'id' => $c->id,
            'uuid' => $c->uuid,
            'name' => $c->name,
            'color' => $c->color,
        ])->values();

        $mapCategory = fn ($c) => [
            'id' => $c->id,
            'uuid' => $c->uuid,
            'name' => $c->name,
            'color' => $c->color,
            'is_system' => $c->is_system,
            'children' => $c->children->map(fn ($child) => [
                'id' => $child->id,
                'uuid' => $child->uuid,
                'name' => $child->name,
                'color' => $child->color,
                'is_system' => $child->is_system,
            ])->toArray(),
        ];

        return Inertia::render('categories/index', [
            'categories' => $userCategories->map($mapCategory)->toArray(),
            'systemCategories' => $systemCategories->map($mapCategory)->toArray(),
            'parentCategories' => $parentCategories->toArray(),
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
