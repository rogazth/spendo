<?php

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    Currency::updateOrCreate(['code' => 'CLP'], ['name' => 'Peso chileno', 'locale' => 'es-CL']);
    Currency::updateOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'locale' => 'en-US']);
});

// ---------------------------------------------------------------------------
// Guest access
// ---------------------------------------------------------------------------

test('guests are redirected to login', function () {
    $this->get('/budgets')->assertRedirect('/login');
});

// ---------------------------------------------------------------------------
// Index
// ---------------------------------------------------------------------------

test('index renders budgets page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/budgets')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('budgets/index')
            ->has('budgets.data')
            ->has('summary')
            ->has('accounts')
            ->has('categories')
        );
});

test('index aggregates summary by currency', function () {
    Carbon::setTestNow('2026-02-20 10:00:00');

    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['currency' => 'CLP']);
    $categoryClp = Category::factory()->expense()->for($user)->create();
    $categoryUsd = Category::factory()->expense()->for($user)->create();

    $clpBudget = Budget::factory()->for($user)->create([
        'currency' => 'CLP',
        'frequency' => 'monthly',
        'anchor_date' => '2026-02-01',
    ]);
    $clpBudget->items()->create(['category_id' => $categoryClp->id, 'amount' => 200000]);

    $usdBudget = Budget::factory()->for($user)->create([
        'currency' => 'USD',
        'frequency' => 'monthly',
        'anchor_date' => '2026-02-01',
    ]);
    $usdBudget->items()->create(['category_id' => $categoryUsd->id, 'amount' => 500]);

    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'category_id' => $categoryClp->id,
        'amount' => 5000,
        'currency' => 'CLP',
        'exclude_from_budget' => false,
        'transaction_date' => '2026-02-10',
    ]);

    $this->actingAs($user)->get('/budgets')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.CLP.budgeted', 200000)
            ->where('summary.CLP.spent', 5000)
            ->where('summary.CLP.remaining', 195000)
            ->where('summary.USD.budgeted', 500)
            ->where('summary.USD.spent', 0)
            ->where('summary.USD.remaining', 500)
        );

    Carbon::setTestNow();
});

test('index includes active accounts only', function () {
    $user = User::factory()->create();
    Account::factory()->for($user)->create(['name' => 'Activa', 'is_active' => true]);
    Account::factory()->for($user)->create(['name' => 'Inactiva', 'is_active' => false]);

    $this->actingAs($user)->get('/budgets')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('accounts', 1)
            ->where('accounts.0.name', 'Activa')
        );
});

// ---------------------------------------------------------------------------
// Store
// ---------------------------------------------------------------------------

test('store creates a budget with items', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['currency' => 'CLP']);
    $categoryA = Category::factory()->expense()->for($user)->create();
    $categoryB = Category::factory()->expense()->for($user)->create();

    $this->actingAs($user)->post('/budgets', [
        'name' => 'Presupuesto Mensual',
        'currency' => 'CLP',
        'frequency' => 'monthly',
        'anchor_date' => now()->toDateString(),
        'account_id' => $account->id,
        'items' => [
            ['category_id' => $categoryA->id, 'amount' => 100000],
            ['category_id' => $categoryB->id, 'amount' => 50000],
        ],
    ])->assertRedirect('/budgets');

    $budget = Budget::query()->where('name', 'Presupuesto Mensual')->firstOrFail();

    $this->assertDatabaseHas('budget_items', ['budget_id' => $budget->id, 'category_id' => $categoryA->id]);
    $this->assertDatabaseHas('budget_items', ['budget_id' => $budget->id, 'category_id' => $categoryB->id]);
});

test('store persists color and emoji', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['currency' => 'CLP']);
    $category = Category::factory()->expense()->for($user)->create();

    $this->actingAs($user)->post('/budgets', [
        'name' => 'Budget con estilo',
        'color' => '#10B981',
        'emoji' => '🏠',
        'currency' => 'CLP',
        'frequency' => 'monthly',
        'anchor_date' => now()->toDateString(),
        'account_id' => $account->id,
        'items' => [
            ['category_id' => $category->id, 'amount' => 100000],
        ],
    ])->assertRedirect('/budgets');

    $this->assertDatabaseHas('budgets', [
        'name' => 'Budget con estilo',
        'color' => '#10B981',
        'emoji' => '🏠',
    ]);
});

test('store rejects invalid color', function () {
    $user = User::factory()->create();
    $category = Category::factory()->expense()->for($user)->create();

    $this->actingAs($user)->post('/budgets', [
        'name' => 'Budget color malo',
        'color' => 'not-a-color',
        'currency' => 'CLP',
        'frequency' => 'monthly',
        'anchor_date' => now()->toDateString(),
        'items' => [
            ['category_id' => $category->id, 'amount' => 100000],
        ],
    ])->assertSessionHasErrors('color');
});

test('update modifies color and emoji', function () {
    $user = User::factory()->create();
    $budget = Budget::factory()->for($user)->create([
        'color' => '#6366F1',
        'emoji' => '💰',
    ]);
    $account = Account::factory()->for($user)->create(['currency' => $budget->currency]);
    $category = Category::factory()->expense()->for($user)->create();

    $this->actingAs($user)->from('/budgets')->put("/budgets/{$budget->uuid}", [
        'name' => $budget->name,
        'color' => '#EF4444',
        'emoji' => '✈️',
        'currency' => $budget->currency,
        'frequency' => $budget->frequency,
        'anchor_date' => $budget->anchor_date->toDateString(),
        'account_id' => $account->id,
        'items' => [
            ['category_id' => $category->id, 'amount' => 100000],
        ],
    ])->assertRedirect('/budgets');

    $this->assertDatabaseHas('budgets', [
        'id' => $budget->id,
        'color' => '#EF4444',
        'emoji' => '✈️',
    ]);
});

test('store creates budget with correct currency', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->usd()->create();
    $category = Category::factory()->expense()->for($user)->create();

    $this->actingAs($user)->post('/budgets', [
        'name' => 'Budget USD',
        'currency' => 'USD',
        'frequency' => 'monthly',
        'anchor_date' => now()->toDateString(),
        'account_id' => $account->id,
        'items' => [['category_id' => $category->id, 'amount' => 80000]],
    ])->assertRedirect('/budgets');

    $this->assertDatabaseHas('budgets', ['name' => 'Budget USD', 'currency' => 'USD']);
});

test('store rejects parent and child categories together', function () {
    $user = User::factory()->create();
    $parent = Category::factory()->expense()->for($user)->create(['parent_id' => null]);
    $child = Category::factory()->expense()->for($user)->create(['parent_id' => $parent->id]);

    $this->actingAs($user)->post('/budgets', [
        'name' => 'Inválido',
        'currency' => 'CLP',
        'frequency' => 'monthly',
        'anchor_date' => now()->toDateString(),
        'items' => [
            ['category_id' => $parent->id, 'amount' => 100000],
            ['category_id' => $child->id, 'amount' => 50000],
        ],
    ])->assertSessionHasErrors('items');
});

test('store validates required fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/budgets', [])
        ->assertSessionHasErrors(['name', 'currency', 'frequency', 'anchor_date', 'items']);
});

test('store rejects budget item with amount of zero', function () {
    $user = User::factory()->create();
    $category = Category::factory()->expense()->for($user)->create();

    $this->actingAs($user)->post('/budgets', [
        'name' => 'Invalid Budget',
        'currency' => 'CLP',
        'frequency' => 'monthly',
        'anchor_date' => now()->toDateString(),
        'items' => [['category_id' => $category->id, 'amount' => 0]],
    ])->assertSessionHasErrors('items.0.amount');
});

test('store rejects duplicate category in same budget', function () {
    $user = User::factory()->create();
    $category = Category::factory()->expense()->for($user)->create();

    $this->actingAs($user)->post('/budgets', [
        'name' => 'Duplicate Cat Budget',
        'currency' => 'CLP',
        'frequency' => 'monthly',
        'anchor_date' => now()->toDateString(),
        'items' => [
            ['category_id' => $category->id, 'amount' => 1000],
            ['category_id' => $category->id, 'amount' => 500],
        ],
    ])->assertSessionHasErrors('items.0.category_id');
});

test('store rejects invalid currency code', function () {
    $user = User::factory()->create();
    $category = Category::factory()->expense()->for($user)->create();

    $this->actingAs($user)->post('/budgets', [
        'name' => 'Budget inválido',
        'currency' => 'XYZ',
        'frequency' => 'monthly',
        'anchor_date' => now()->toDateString(),
        'items' => [['category_id' => $category->id, 'amount' => 1000]],
    ])->assertSessionHasErrors('currency');
});

test('store rejects unknown currency code', function () {
    $user = User::factory()->create();
    $category = Category::factory()->expense()->for($user)->create();

    $this->actingAs($user)->post('/budgets', [
        'name' => 'Budget inválido',
        'currency' => 'GBP',
        'frequency' => 'monthly',
        'anchor_date' => now()->toDateString(),
        'items' => [['category_id' => $category->id, 'amount' => 1000]],
    ])->assertSessionHasErrors('currency');
});

test('store rejects ends_at before anchor_date', function () {
    $user = User::factory()->create();
    $category = Category::factory()->expense()->for($user)->create();

    $this->actingAs($user)->post('/budgets', [
        'name' => 'Fechas Inválidas',
        'currency' => 'CLP',
        'frequency' => 'monthly',
        'anchor_date' => '2026-03-01',
        'ends_at' => '2026-02-01',
        'items' => [['category_id' => $category->id, 'amount' => 1000]],
    ])->assertSessionHasErrors('ends_at');
});

// ---------------------------------------------------------------------------
// Show
// ---------------------------------------------------------------------------

test('show renders budget detail with spending summary', function () {
    Carbon::setTestNow('2026-02-20 10:00:00');

    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $category = Category::factory()->expense()->for($user)->create(['parent_id' => null]);

    $budget = Budget::factory()->for($user)->create([
        'currency' => 'CLP',
        'frequency' => 'monthly',
        'anchor_date' => '2026-02-01',
    ]);
    $budget->items()->create(['category_id' => $category->id, 'amount' => 200000]);

    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'category_id' => $category->id,
        'amount' => 500,
        'currency' => 'CLP',
        'exclude_from_budget' => false,
        'transaction_date' => '2026-02-10',
    ]);

    $this->actingAs($user)->get("/budgets/{$budget->uuid}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('budgets/show')
            ->where('summary.spent', 500)
        );

    Carbon::setTestNow();
});

test('show returns 403 for another user budget', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $category = Category::factory()->expense()->for($owner)->create();
    $budget = Budget::factory()->for($owner)->create([
        'currency' => 'CLP',
        'frequency' => 'monthly',
        'anchor_date' => now()->toDateString(),
    ]);
    $budget->items()->create(['category_id' => $category->id, 'amount' => 10000]);

    $this->actingAs($other)->get("/budgets/{$budget->uuid}")
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// Update
// ---------------------------------------------------------------------------

test('update modifies the budget and replaces items', function () {
    $user = User::factory()->create();
    $original = Category::factory()->expense()->for($user)->create();
    $replacement = Category::factory()->expense()->for($user)->create();

    $budget = Budget::factory()->for($user)->create([
        'name' => 'Original',
        'currency' => 'CLP',
        'frequency' => 'monthly',
        'anchor_date' => '2026-02-01',
    ]);
    $budget->items()->create(['category_id' => $original->id, 'amount' => 100000]);
    $account = Account::factory()->for($user)->create(['currency' => 'CLP']);

    $this->actingAs($user)->from("/budgets/{$budget->uuid}")->put("/budgets/{$budget->uuid}", [
        'name' => 'Editado',
        'currency' => 'CLP',
        'frequency' => 'monthly',
        'anchor_date' => '2026-02-01',
        'account_id' => $account->id,
        'items' => [['category_id' => $replacement->id, 'amount' => 250000]],
    ])->assertRedirect("/budgets/{$budget->uuid}");

    $this->assertDatabaseHas('budgets', ['id' => $budget->id, 'name' => 'Editado']);
    $this->assertDatabaseHas('budget_items', [
        'budget_id' => $budget->id,
        'category_id' => $replacement->id,
    ]);
    $this->assertDatabaseMissing('budget_items', [
        'budget_id' => $budget->id,
        'category_id' => $original->id,
    ]);
});

test('update returns 403 for another user budget', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $ownerCategory = Category::factory()->expense()->for($owner)->create();
    $otherCategory = Category::factory()->expense()->for($other)->create();

    $budget = Budget::factory()->for($owner)->create([
        'currency' => 'CLP',
        'frequency' => 'monthly',
        'anchor_date' => now()->toDateString(),
    ]);
    $budget->items()->create(['category_id' => $ownerCategory->id, 'amount' => 10000]);
    $otherAccount = Account::factory()->for($other)->create(['currency' => 'CLP']);

    $this->actingAs($other)->put("/budgets/{$budget->uuid}", [
        'name' => 'Hackeado',
        'currency' => 'CLP',
        'frequency' => 'monthly',
        'anchor_date' => now()->toDateString(),
        'account_id' => $otherAccount->id,
        'items' => [['category_id' => $otherCategory->id, 'amount' => 1000]],
    ])->assertForbidden();
});

// ---------------------------------------------------------------------------
// Destroy
// ---------------------------------------------------------------------------

test('destroy removes the budget', function () {
    $user = User::factory()->create();
    $category = Category::factory()->expense()->for($user)->create();

    $budget = Budget::factory()->for($user)->create([
        'currency' => 'CLP',
        'frequency' => 'monthly',
        'anchor_date' => now()->toDateString(),
    ]);
    $budget->items()->create(['category_id' => $category->id, 'amount' => 10000]);

    $this->actingAs($user)->delete("/budgets/{$budget->uuid}")
        ->assertRedirect('/budgets');

    $this->assertSoftDeleted('budgets', ['id' => $budget->id]);
});

test('destroy returns 403 for another user budget', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $category = Category::factory()->expense()->for($owner)->create();

    $budget = Budget::factory()->for($owner)->create([
        'currency' => 'CLP',
        'frequency' => 'monthly',
        'anchor_date' => now()->toDateString(),
    ]);
    $budget->items()->create(['category_id' => $category->id, 'amount' => 10000]);

    $this->actingAs($other)->delete("/budgets/{$budget->uuid}")
        ->assertForbidden();

    $this->assertDatabaseHas('budgets', ['id' => $budget->id]);
});

// ---------------------------------------------------------------------------
// Toggle active
// ---------------------------------------------------------------------------

test('toggle-active flips the budget active flag', function () {
    $user = User::factory()->create();
    $budget = Budget::factory()->for($user)->create([
        'currency' => 'CLP',
        'is_active' => true,
    ]);

    $this->actingAs($user)->patch("/budgets/{$budget->uuid}/toggle-active")
        ->assertRedirect();
    expect($budget->fresh()->is_active)->toBeFalse();

    $this->actingAs($user)->patch("/budgets/{$budget->uuid}/toggle-active")
        ->assertRedirect();
    expect($budget->fresh()->is_active)->toBeTrue();
});

test('toggle-active returns 403 for another user budget', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $budget = Budget::factory()->for($owner)->create([
        'currency' => 'CLP',
        'is_active' => true,
    ]);

    $this->actingAs($other)->patch("/budgets/{$budget->uuid}/toggle-active")
        ->assertForbidden();

    expect($budget->fresh()->is_active)->toBeTrue();
});
