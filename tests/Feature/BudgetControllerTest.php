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
        ->assertInertia(fn (Assert $page) => $page->component('budgets/index'));
});

// ---------------------------------------------------------------------------
// Store
// ---------------------------------------------------------------------------

test('store creates a budget with items', function () {
    $user = User::factory()->create();
    $categoryA = Category::factory()->expense()->for($user)->create();
    $categoryB = Category::factory()->expense()->for($user)->create();

    $this->actingAs($user)->post('/budgets', [
        'name' => 'Presupuesto Mensual',
        'currency' => 'CLP',
        'frequency' => 'monthly',
        'anchor_date' => now()->toDateString(),
        'items' => [
            ['category_id' => $categoryA->id, 'amount' => 100000],
            ['category_id' => $categoryB->id, 'amount' => 50000],
        ],
    ])->assertRedirect('/budgets');

    $budget = Budget::query()->where('name', 'Presupuesto Mensual')->firstOrFail();

    $this->assertDatabaseHas('budget_items', ['budget_id' => $budget->id, 'category_id' => $categoryA->id]);
    $this->assertDatabaseHas('budget_items', ['budget_id' => $budget->id, 'category_id' => $categoryB->id]);
});

test('store links budget to an account', function () {
    $user = User::factory()->create();
    $account = Account::factory()->checking()->for($user)->create(['currency' => 'CLP']);
    $category = Category::factory()->expense()->for($user)->create();

    $this->actingAs($user)->post('/budgets', [
        'name' => 'Budget Cuenta',
        'currency' => 'CLP',
        'frequency' => 'monthly',
        'anchor_date' => now()->toDateString(),
        'account_id' => $account->id,
        'items' => [['category_id' => $category->id, 'amount' => 80000]],
    ])->assertRedirect('/budgets');

    $this->assertDatabaseHas('budgets', ['name' => 'Budget Cuenta', 'account_id' => $account->id]);
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

test('store rejects account with mismatched currency', function () {
    $user = User::factory()->create();
    $account = Account::factory()->checking()->for($user)->create(['currency' => 'USD']);
    $category = Category::factory()->expense()->for($user)->create();

    $this->actingAs($user)->post('/budgets', [
        'name' => 'Currency Mismatch',
        'currency' => 'CLP',
        'frequency' => 'monthly',
        'anchor_date' => now()->toDateString(),
        'account_id' => $account->id,
        'items' => [['category_id' => $category->id, 'amount' => 1000]],
    ])->assertSessionHasErrors('account_id');
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
    $account = Account::factory()->checking()->for($user)->create();
    $category = Category::factory()->expense()->for($user)->create(['parent_id' => null]);

    $budget = Budget::factory()->for($user)->create([
        'currency' => 'CLP',
        'frequency' => 'monthly',
        'anchor_date' => '2026-02-01',
        'account_id' => null,
    ]);
    $budget->items()->create(['category_id' => $category->id, 'amount' => 200000]);

    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'category_id' => $category->id,
        'payment_method_id' => null,
        'amount' => 500,
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
