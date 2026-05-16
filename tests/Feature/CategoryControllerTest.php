<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
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

test('index renders categories page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/categories')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('categories/index')
            ->has('categories')
            ->has('parentCategories')
            ->has('totals')
            ->has('period')
        );
});

test('index includes current-month aggregates per category', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['currency' => 'CLP']);

    $parent = Category::factory()->for($user)->create([
        'name' => 'Comida',
        'parent_id' => null,
    ]);
    $child = Category::factory()->for($user)->create([
        'name' => 'Supermercado',
        'parent_id' => $parent->id,
    ]);
    $idle = Category::factory()->for($user)->create([
        'name' => 'Sin uso',
        'parent_id' => null,
    ]);

    $today = CarbonImmutable::now()->startOfMonth()->addDays(2);

    Transaction::factory()->for($user)->for($account)->for($parent)->create([
        'amount' => -30000,
        'currency' => 'CLP',
        'transaction_date' => $today,
    ]);
    Transaction::factory()->for($user)->for($account)->for($child)->create([
        'amount' => -50000,
        'currency' => 'CLP',
        'transaction_date' => $today,
    ]);
    Transaction::factory()->for($user)->for($account)->for($child)->create([
        'amount' => 5000,
        'currency' => 'CLP',
        'transaction_date' => $today,
    ]);

    $this->actingAs($user)->get('/categories')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('totals.in_use', 1)
            ->where('totals.idle', 1)
            ->where('totals.total_spent', 80000)
            ->where('totals.total_income', 5000)
            ->where('totals.top_category.name', 'Comida')
            ->where('totals.top_category.total_spent', 80000)
        );
});

test('index excludes transfers and prior-month transactions from aggregates', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['currency' => 'CLP']);
    $other = Account::factory()->for($user)->create(['currency' => 'CLP']);

    $category = Category::factory()->for($user)->create(['parent_id' => null]);

    Transaction::factory()->for($user)->for($account)->for($category)->create([
        'amount' => -10000,
        'currency' => 'CLP',
        'transaction_date' => CarbonImmutable::now()->subMonth(),
    ]);

    $transferOut = Transaction::factory()->for($user)->for($account)->for($category)->create([
        'amount' => -7000,
        'currency' => 'CLP',
        'transaction_date' => CarbonImmutable::now()->startOfMonth(),
    ]);
    $transferIn = Transaction::factory()->for($user)->for($other)->for($category)->create([
        'amount' => 7000,
        'currency' => 'CLP',
        'transaction_date' => CarbonImmutable::now()->startOfMonth(),
        'linked_transaction_id' => $transferOut->id,
    ]);
    $transferOut->update(['linked_transaction_id' => $transferIn->id]);

    Transaction::factory()->for($user)->for($account)->for($category)->create([
        'amount' => -2500,
        'currency' => 'CLP',
        'transaction_date' => CarbonImmutable::now()->startOfMonth()->addDays(3),
    ]);

    $this->actingAs($user)->get('/categories')
        ->assertInertia(fn (Assert $page) => $page
            ->where('totals.total_spent', 2500)
            ->where('totals.in_use', 1)
        );
});

// ---------------------------------------------------------------------------
// Store
// ---------------------------------------------------------------------------

test('store creates a category', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/categories', [
        'name' => 'Viajes',
        'color' => '#FF5733',
    ])->assertRedirect('/categories');

    $this->assertDatabaseHas('categories', [
        'user_id' => $user->id,
        'name' => 'Viajes',
        'is_system' => false,
    ]);
});

test('store creates a subcategory', function () {
    $user = User::factory()->create();
    $parent = Category::factory()->for($user)->create(['name' => 'Comida']);

    $this->actingAs($user)->post('/categories', [
        'name' => 'Supermercado',
        'parent_id' => $parent->id,
    ])->assertRedirect('/categories');

    $child = Category::query()->where('name', 'Supermercado')->firstOrFail();
    expect($child->parent_id)->toBe($parent->id);
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
    $category = Category::factory()->for($user)->create();

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
    $category = Category::factory()->for($owner)->create();

    $this->actingAs($other)->get("/categories/{$category->uuid}")
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// Update
// ---------------------------------------------------------------------------

test('update modifies a user category', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create(['name' => 'Original']);

    $this->actingAs($user)->put("/categories/{$category->uuid}", [
        'name' => 'Actualizada',
    ])->assertRedirect('/categories');

    expect($category->fresh()->name)->toBe('Actualizada');
});

test('update returns 403 for system categories', function () {
    $user = User::factory()->create();
    $system = Category::factory()->system()->create();

    $this->actingAs($user)->put("/categories/{$system->uuid}", [
        'name' => 'Hack',
    ])->assertForbidden();
});

test('update returns 403 for another user category', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $category = Category::factory()->for($owner)->create();

    $this->actingAs($other)->put("/categories/{$category->uuid}", [
        'name' => 'Hack',
    ])->assertForbidden();
});

// ---------------------------------------------------------------------------
// Destroy
// ---------------------------------------------------------------------------

test('destroy soft-deletes category and orphans its children', function () {
    $user = User::factory()->create();
    $parent = Category::factory()->for($user)->create();
    $child = Category::factory()->for($user)->create(['parent_id' => $parent->id]);

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
    $category = Category::factory()->for($owner)->create();

    $this->actingAs($other)->delete("/categories/{$category->uuid}")
        ->assertForbidden();
});
