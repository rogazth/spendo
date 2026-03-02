<?php

use App\Models\Category;
use App\Models\Currency;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    Currency::updateOrCreate(['code' => 'CLP'], ['name' => 'Peso chileno', 'locale' => 'es-CL']);
});

// ---------------------------------------------------------------------------
// Guest access
// ---------------------------------------------------------------------------

test('guests are redirected to login', function () {
    $this->get('/categories')->assertRedirect('/login');
});

// ---------------------------------------------------------------------------
// Index
// ---------------------------------------------------------------------------

test('index renders categories page with expense and income groups', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/categories')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('categories/index')
            ->has('expenseCategories')
            ->has('incomeCategories')
            ->has('parentCategories')
        );
});

// ---------------------------------------------------------------------------
// Store
// ---------------------------------------------------------------------------

test('store creates an expense category', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/categories', [
        'name' => 'Viajes',
        'type' => 'expense',
        'color' => '#FF5733',
    ])->assertRedirect('/categories');

    $this->assertDatabaseHas('categories', [
        'user_id' => $user->id,
        'name' => 'Viajes',
        'type' => 'expense',
        'is_system' => false,
    ]);
});

test('store creates a subcategory inheriting parent type', function () {
    $user = User::factory()->create();
    $parent = Category::factory()->expense()->for($user)->create(['name' => 'Comida']);

    $this->actingAs($user)->post('/categories', [
        'name' => 'Supermercado',
        'type' => 'expense',
        'parent_id' => $parent->id,
    ])->assertRedirect('/categories');

    $child = Category::query()->where('name', 'Supermercado')->firstOrFail();
    expect($child->parent_id)->toBe($parent->id);
    expect($child->type->value)->toBe('expense');
});

test('store validates required fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/categories', [])
        ->assertSessionHasErrors(['name']);
});

// ---------------------------------------------------------------------------
// Show
// ---------------------------------------------------------------------------

test('show renders category detail page', function () {
    $user = User::factory()->create();
    $category = Category::factory()->expense()->for($user)->create();

    $this->actingAs($user)->get("/categories/{$category->uuid}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('categories/show', false)
            ->has('category')
        );
});

test('show allows access to system categories', function () {
    $user = User::factory()->create();
    $system = Category::factory()->system()->create();

    $this->actingAs($user)->get("/categories/{$system->uuid}")
        ->assertOk();
});

test('show returns 403 for another user category', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $category = Category::factory()->expense()->for($owner)->create();

    $this->actingAs($other)->get("/categories/{$category->uuid}")
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// Update
// ---------------------------------------------------------------------------

test('update modifies a user category', function () {
    $user = User::factory()->create();
    $category = Category::factory()->expense()->for($user)->create(['name' => 'Original']);

    $this->actingAs($user)->put("/categories/{$category->uuid}", [
        'name' => 'Actualizada',
        'type' => 'expense',
    ])->assertRedirect('/categories');

    expect($category->fresh()->name)->toBe('Actualizada');
});

test('update returns 403 for system categories', function () {
    $user = User::factory()->create();
    $system = Category::factory()->system()->create();

    $this->actingAs($user)->put("/categories/{$system->uuid}", [
        'name' => 'Hack',
        'type' => 'expense',
    ])->assertForbidden();
});

test('update returns 403 for another user category', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $category = Category::factory()->expense()->for($owner)->create();

    $this->actingAs($other)->put("/categories/{$category->uuid}", [
        'name' => 'Hack',
        'type' => 'expense',
    ])->assertForbidden();
});

// ---------------------------------------------------------------------------
// Destroy
// ---------------------------------------------------------------------------

test('destroy soft-deletes category and orphans its children', function () {
    $user = User::factory()->create();
    $parent = Category::factory()->expense()->for($user)->create();
    $child = Category::factory()->expense()->for($user)->create(['parent_id' => $parent->id]);

    $this->actingAs($user)->delete("/categories/{$parent->uuid}")
        ->assertRedirect('/categories');

    $this->assertSoftDeleted('categories', ['id' => $parent->id]);
    expect($child->fresh()->parent_id)->toBeNull();
});

test('destroy returns 403 for system categories', function () {
    $user = User::factory()->create();
    $system = Category::factory()->system()->create();

    $this->actingAs($user)->delete("/categories/{$system->uuid}")
        ->assertForbidden();
});

test('destroy returns 403 for another user category', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $category = Category::factory()->expense()->for($owner)->create();

    $this->actingAs($other)->delete("/categories/{$category->uuid}")
        ->assertForbidden();
});
