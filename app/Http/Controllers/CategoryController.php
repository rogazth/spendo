<?php

namespace App\Http\Controllers;

use App\Enums\CategoryType;
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

        $expenseCategories = $categories->filter(fn ($c) => $c->type === CategoryType::Expense)->values();
        $incomeCategories = $categories->filter(fn ($c) => $c->type === CategoryType::Income)->values();

        $parentCategories = $categories->filter(fn ($c) => ! $c->is_system)->map(fn ($c) => [
            'id' => $c->id,
            'uuid' => $c->uuid,
            'name' => $c->name,
            'type' => $c->type->value,
            'color' => $c->color,
        ])->values();

        return Inertia::render('categories/index', [
            'expenseCategories' => $expenseCategories->map(fn ($c) => [
                'id' => $c->id,
                'uuid' => $c->uuid,
                'name' => $c->name,
                'type' => $c->type->value,
                'color' => $c->color,
                'is_system' => $c->is_system,
                'children' => $c->children->map(fn ($child) => [
                    'id' => $child->id,
                    'uuid' => $child->uuid,
                    'name' => $child->name,
                    'type' => $child->type->value,
                    'color' => $child->color,
                    'is_system' => $child->is_system,
                ])->toArray(),
            ])->toArray(),
            'incomeCategories' => $incomeCategories->map(fn ($c) => [
                'id' => $c->id,
                'uuid' => $c->uuid,
                'name' => $c->name,
                'type' => $c->type->value,
                'color' => $c->color,
                'is_system' => $c->is_system,
                'children' => $c->children->map(fn ($child) => [
                    'id' => $child->id,
                    'uuid' => $child->uuid,
                    'name' => $child->name,
                    'type' => $child->type->value,
                    'color' => $child->color,
                    'is_system' => $child->is_system,
                ])->toArray(),
            ])->toArray(),
            'parentCategories' => $parentCategories->toArray(),
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $user = Auth::user();

        $data = $request->validated();

        if (! empty($data['parent_id'])) {
            $parent = Category::findOrFail($data['parent_id']);
            $data['type'] = $parent->type->value;
        }

        Category::create([
            ...$data,
            'user_id' => $user->id,
            'is_system' => false,
        ]);

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

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $this->authorizeCategory($category);

        if ($category->is_system) {
            abort(403, 'No puedes editar categorías del sistema.');
        }

        $data = $request->validated();

        if (! empty($data['parent_id'])) {
            $parent = Category::findOrFail($data['parent_id']);
            $data['type'] = $parent->type->value;
        }

        $category->update($data);

        return redirect()->route('categories.index')
            ->with('success', 'Categoría actualizada correctamente.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $this->authorizeCategory($category);

        if ($category->is_system) {
            abort(403, 'No puedes eliminar categorías del sistema.');
        }

        $category->children()->update(['parent_id' => null]);

        $category->delete();

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
