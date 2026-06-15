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

afterEach(function () {
    Carbon::setTestNow();
});

// ---------------------------------------------------------------------------
// Guest access
// ---------------------------------------------------------------------------

test('guests are redirected to login', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

// ---------------------------------------------------------------------------
// Dashboard shape
// ---------------------------------------------------------------------------

test('dashboard renders for authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('currencySummaries')
        );
});

// ---------------------------------------------------------------------------
// Currency summaries
// ---------------------------------------------------------------------------

test('cash_on_hand includes all active accounts', function () {
    $user = User::factory()->create();

    $main = Account::factory()->for($user)->create(['currency' => 'CLP']);
    $savings = Account::factory()->for($user)->create(['currency' => 'CLP']);
    $inactive = Account::factory()->for($user)->inactive()->create(['currency' => 'CLP']);

    Transaction::factory()->income()->for($user)->create([
        'account_id' => $main->id,
        'amount' => 1000,
        'currency' => 'CLP',
    ]);
    Transaction::factory()->income()->for($user)->create([
        'account_id' => $savings->id,
        'amount' => 5000,
        'currency' => 'CLP',
    ]);
    Transaction::factory()->income()->for($user)->create([
        'account_id' => $inactive->id,
        'amount' => 9000,
        'currency' => 'CLP',
    ]);

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('currencySummaries.0.currency', 'CLP')
            ->where('currencySummaries.0.cash_on_hand', 6000)
        );
});

test('ready_to_assign subtracts total reserved across active budgets', function () {
    Carbon::setTestNow('2026-02-20 10:00:00');

    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['currency' => 'CLP']);
    Transaction::factory()->income()->for($user)->create([
        'account_id' => $account->id,
        'amount' => 1000,
        'currency' => 'CLP',
    ]);

    $groceries = Category::factory()->expense()->for($user)->create();
    $rent = Category::factory()->expense()->for($user)->create();

    $budget = Budget::factory()->for($user)->create([
        'currency' => 'CLP',
        'frequency' => 'monthly',
        'anchor_date' => '2026-02-01',
    ]);
    $budget->items()->create(['category_id' => $groceries->id, 'amount' => 300]);
    $budget->items()->create(['category_id' => $rent->id, 'amount' => 500]);

    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'category_id' => $groceries->id,
        'amount' => 100,
        'currency' => 'CLP',
        'transaction_date' => '2026-02-10',
    ]);

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('currencySummaries.0.cash_on_hand', 900)
            ->where('currencySummaries.0.total_reserved', 700)
            ->where('currencySummaries.0.ready_to_assign', 200)
        );
});

test('budget overspend is flagged at budget level but does not increase reserved', function () {
    Carbon::setTestNow('2026-02-20 10:00:00');

    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['currency' => 'CLP']);
    $groceries = Category::factory()->expense()->for($user)->create();
    $rent = Category::factory()->expense()->for($user)->create();

    $budget = Budget::factory()->for($user)->create([
        'currency' => 'CLP',
        'frequency' => 'monthly',
        'anchor_date' => '2026-02-01',
    ]);
    $budget->items()->create(['category_id' => $groceries->id, 'amount' => 100]);
    $budget->items()->create(['category_id' => $rent->id, 'amount' => 500]);

    // Groceries overspent: budgeted 100, spent 150 → overspend = 50
    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'category_id' => $groceries->id,
        'amount' => 150,
        'currency' => 'CLP',
        'transaction_date' => '2026-02-10',
    ]);

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('currencySummaries.0.total_overspend', 50)
            ->where('currencySummaries.0.budgets.0.has_overspend', true)
            ->where('currencySummaries.0.budgets.0.overspend_amount', 50)
            // Reserved only counts under-budget items (rent), groceries clamped at 0
            ->where('currencySummaries.0.budgets.0.reserved', 500)
        );
});

test('only active budgets in current cycle are counted', function () {
    Carbon::setTestNow('2026-02-20 10:00:00');

    $user = User::factory()->create();
    Account::factory()->for($user)->create(['currency' => 'CLP']);
    $category = Category::factory()->expense()->for($user)->create();

    // Inactive budget — should not be counted
    $inactive = Budget::factory()->for($user)->inactive()->create([
        'currency' => 'CLP',
        'anchor_date' => '2026-02-01',
    ]);
    $inactive->items()->create(['category_id' => $category->id, 'amount' => 999]);

    // Future-anchor budget — should not be counted
    $future = Budget::factory()->for($user)->create([
        'currency' => 'CLP',
        'anchor_date' => '2026-12-01',
    ]);
    $future->items()->create(['category_id' => $category->id, 'amount' => 500]);

    // Active budget within cycle — should be counted
    $active = Budget::factory()->for($user)->create([
        'currency' => 'CLP',
        'anchor_date' => '2026-02-01',
    ]);
    $active->items()->create(['category_id' => $category->id, 'amount' => 200]);

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('currencySummaries.0.total_reserved', 200)
            ->count('currencySummaries.0.budgets', 1)
            ->where('currencySummaries.0.budgets.0.name', $active->name)
        );
});

test('budgets are listed sorted by reserved desc', function () {
    Carbon::setTestNow('2026-02-20 10:00:00');

    $user = User::factory()->create();
    Account::factory()->for($user)->create(['currency' => 'CLP']);
    $catA = Category::factory()->expense()->for($user)->create();
    $catB = Category::factory()->expense()->for($user)->create();

    $small = Budget::factory()->for($user)->create([
        'name' => 'Small',
        'currency' => 'CLP',
        'anchor_date' => '2026-02-01',
    ]);
    $small->items()->create(['category_id' => $catA->id, 'amount' => 50]);

    $large = Budget::factory()->for($user)->create([
        'name' => 'Large',
        'currency' => 'CLP',
        'anchor_date' => '2026-02-01',
    ]);
    $large->items()->create(['category_id' => $catB->id, 'amount' => 800]);

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('currencySummaries.0.budgets.0.name', 'Large')
            ->where('currencySummaries.0.budgets.1.name', 'Small')
        );
});

test('daily_spent series is cumulative from cycle_start through today', function () {
    Carbon::setTestNow('2026-02-05 10:00:00');

    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['currency' => 'CLP']);
    $groceries = Category::factory()->expense()->for($user)->create();

    $budget = Budget::factory()->for($user)->create([
        'currency' => 'CLP',
        'frequency' => 'monthly',
        'anchor_date' => '2026-02-01',
    ]);
    $budget->items()->create(['category_id' => $groceries->id, 'amount' => 1000]);

    // Day 1: spend 10
    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'category_id' => $groceries->id,
        'amount' => 10,
        'currency' => 'CLP',
        'transaction_date' => '2026-02-01',
    ]);
    // Day 3: spend 5
    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'category_id' => $groceries->id,
        'amount' => 5,
        'currency' => 'CLP',
        'transaction_date' => '2026-02-03',
    ]);
    // Day 5: spend 7
    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'category_id' => $groceries->id,
        'amount' => 7,
        'currency' => 'CLP',
        'transaction_date' => '2026-02-05',
    ]);
    // Future-dated (should not appear in series)
    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'category_id' => $groceries->id,
        'amount' => 99,
        'currency' => 'CLP',
        'transaction_date' => '2026-02-15',
    ]);

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            // 5 points: Feb 1..Feb 5 inclusive
            ->count('currencySummaries.0.budgets.0.daily_spent', 5)
            ->where('currencySummaries.0.budgets.0.daily_spent.0', 10)
            ->where('currencySummaries.0.budgets.0.daily_spent.1', 10)
            ->where('currencySummaries.0.budgets.0.daily_spent.2', 15)
            ->where('currencySummaries.0.budgets.0.daily_spent.3', 15)
            ->where('currencySummaries.0.budgets.0.daily_spent.4', 22)
        );
});

test('separate summary entries per currency', function () {
    Carbon::setTestNow('2026-02-20 10:00:00');

    $user = User::factory()->create();
    Account::factory()->for($user)->create(['currency' => 'CLP']);
    Account::factory()->for($user)->usd()->create();

    $catClp = Category::factory()->expense()->for($user)->create();
    $catUsd = Category::factory()->expense()->for($user)->create();

    $clpBudget = Budget::factory()->for($user)->create([
        'currency' => 'CLP',
        'anchor_date' => '2026-02-01',
    ]);
    $clpBudget->items()->create(['category_id' => $catClp->id, 'amount' => 1000]);

    $usdBudget = Budget::factory()->for($user)->create([
        'currency' => 'USD',
        'anchor_date' => '2026-02-01',
    ]);
    $usdBudget->items()->create(['category_id' => $catUsd->id, 'amount' => 5]);

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->count('currencySummaries', 2)
            ->where('currencySummaries.0.currency', 'CLP')
            ->where('currencySummaries.0.total_reserved', 1000)
            ->where('currencySummaries.1.currency', 'USD')
            ->where('currencySummaries.1.total_reserved', 5)
        );
});
