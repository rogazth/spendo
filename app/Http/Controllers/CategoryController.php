<?php

namespace App\Http\Controllers;

use App\Enums\CategoryType;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
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

        // Get all categories (system + user's own)
        $categories = Category::query()
            ->where(function ($query) use ($user) {
                $query->whereNull('user_id')
                    ->orWhere('user_id', $user->id);
            })
            ->with('children')
            ->whereNull('parent_id')
            ->orderBy('type')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn ($c) => $this->formatCategory($c));

        return Inertia::render('categories/index', [
            'categories' => $categories,
            'types' => collect(CategoryType::cases())->map(fn ($t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
        ]);
    }

    public function create(): Response
    {
        $user = Auth::user();

        $parentCategories = Category::query()
            ->where(function ($query) use ($user) {
                $query->whereNull('user_id')
                    ->orWhere('user_id', $user->id);
            })
            ->whereNull('parent_id')
            ->where('is_system', false)
            ->orderBy('name')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'uuid' => $c->uuid,
                'name' => $c->name,
                'type' => $c->type->value,
            ]);

        return Inertia::render('categories/create', [
            'parentCategories' => $parentCategories,
            'types' => collect([CategoryType::Expense, CategoryType::Income])->map(fn ($t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $user = Auth::user();

        $data = $request->validated();

        // If subcategory, inherit type from parent
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
            'category' => $this->formatCategory($category->load('children', 'parent')),
        ]);
    }

    public function edit(Category $category): Response
    {
        $this->authorizeCategory($category);

        if ($category->is_system) {
            abort(403, 'No puedes editar categorías del sistema.');
        }

        $user = Auth::user();

        $parentCategories = Category::query()
            ->where(function ($query) use ($user) {
                $query->whereNull('user_id')
                    ->orWhere('user_id', $user->id);
            })
            ->whereNull('parent_id')
            ->where('is_system', false)
            ->where('id', '!=', $category->id)
            ->orderBy('name')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'uuid' => $c->uuid,
                'name' => $c->name,
                'type' => $c->type->value,
            ]);

        return Inertia::render('categories/edit', [
            'category' => $this->formatCategory($category),
            'parentCategories' => $parentCategories,
            'types' => collect([CategoryType::Expense, CategoryType::Income])->map(fn ($t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $this->authorizeCategory($category);

        if ($category->is_system) {
            abort(403, 'No puedes editar categorías del sistema.');
        }

        $data = $request->validated();

        // If subcategory, inherit type from parent
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

        // Move child categories to root before deleting
        $category->children()->update(['parent_id' => null]);

        $category->delete();

        return redirect()->route('categories.index')
            ->with('success', 'Categoría eliminada correctamente.');
    }

    private function authorizeCategory(Category $category): void
    {
        $user = Auth::user();

        // System categories are accessible to all users
        if ($category->user_id === null) {
            return;
        }

        // User can only access their own categories
        if ($category->user_id !== $user->id) {
            abort(403);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formatCategory(Category $category): array
    {
        return [
            'id' => $category->id,
            'uuid' => $category->uuid,
            'name' => $category->name,
            'full_name' => $category->full_name,
            'type' => $category->type->value,
            'type_label' => $category->type->label(),
            'icon' => $category->icon,
            'color' => $category->color,
            'is_system' => $category->is_system,
            'is_parent' => $category->isParent(),
            'parent_id' => $category->parent_id,
            'parent' => $category->relationLoaded('parent') && $category->parent ? [
                'id' => $category->parent->id,
                'uuid' => $category->parent->uuid,
                'name' => $category->parent->name,
            ] : null,
            'children' => $category->relationLoaded('children')
                ? $category->children->map(fn ($c) => $this->formatCategory($c))
                : [],
        ];
    }
}
