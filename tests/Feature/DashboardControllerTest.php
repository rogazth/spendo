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

test('guests are redirected to login', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

test('dashboard renders for authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('currencySummaries')
        );
});

test('the account list only holds active accounts and totals their balances', function () {
    $user = User::factory()->create();

    $main = Account::factory()->for($user)->create(['currency' => 'CLP', 'name' => 'Cuenta corriente']);
    $savings = Account::factory()->for($user)->create(['currency' => 'CLP', 'name' => 'Ahorro']);
    $inactive = Account::factory()->for($user)->inactive()->create(['currency' => 'CLP']);

    foreach ([[$main, 1000], [$savings, 5000], [$inactive, 9000]] as [$account, $amount]) {
        Transaction::factory()->income()->for($user)->create([
            'account_id' => $account->id,
            'amount' => $amount,
            'currency' => 'CLP',
        ]);
    }

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('currencySummaries.0.currency', 'CLP')
            ->where('currencySummaries.0.total', 6000)
            ->count('currencySummaries.0.accounts', 2)
        );
});

test('available subtracts what active budgets still reserve', function () {
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
            ->where('currencySummaries.0.total', 900)
            ->where('currencySummaries.0.reserved', 700)
            ->where('currencySummaries.0.available', 200)
        );
});

test('a healthy budget raises no alert', function () {
    Carbon::setTestNow('2026-02-20 10:00:00');

    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['currency' => 'CLP']);
    $category = Category::factory()->expense()->for($user)->create();

    $budget = Budget::factory()->for($user)->create([
        'currency' => 'CLP',
        'frequency' => 'monthly',
        'anchor_date' => '2026-02-01',
    ]);
    $budget->items()->create(['category_id' => $category->id, 'amount' => 1000]);

    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'category_id' => $category->id,
        'amount' => 100,
        'currency' => 'CLP',
        'transaction_date' => '2026-02-10',
    ]);

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->count('currencySummaries.0.alerts', 0));
});

test('an overspent budget is flagged with its excess', function () {
    Carbon::setTestNow('2026-02-20 10:00:00');

    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['currency' => 'CLP']);
    $groceries = Category::factory()->expense()->for($user)->create();

    $budget = Budget::factory()->for($user)->create([
        'name' => 'Mensual',
        'currency' => 'CLP',
        'frequency' => 'monthly',
        'anchor_date' => '2026-02-01',
    ]);
    $budget->items()->create(['category_id' => $groceries->id, 'amount' => 100]);

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
            ->count('currencySummaries.0.alerts', 1)
            ->where('currencySummaries.0.alerts.0.status', 'over')
            ->where('currencySummaries.0.alerts.0.overspend_amount', 50)
            ->where('currencySummaries.0.alerts.0.name', 'Mensual')
        );
});

test('a budget past 90 percent is flagged as tight', function () {
    Carbon::setTestNow('2026-02-20 10:00:00');

    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['currency' => 'CLP']);
    $category = Category::factory()->expense()->for($user)->create();

    $budget = Budget::factory()->for($user)->create([
        'currency' => 'CLP',
        'frequency' => 'monthly',
        'anchor_date' => '2026-02-01',
    ]);
    $budget->items()->create(['category_id' => $category->id, 'amount' => 1000]);

    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'category_id' => $category->id,
        'amount' => 950,
        'currency' => 'CLP',
        'transaction_date' => '2026-02-10',
    ]);

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->count('currencySummaries.0.alerts', 1)
            ->where('currencySummaries.0.alerts.0.status', 'tight')
            ->where('currencySummaries.0.alerts.0.percentage', 95)
        );
});

test('only active budgets in their cycle reserve money', function () {
    Carbon::setTestNow('2026-02-20 10:00:00');

    $user = User::factory()->create();
    Account::factory()->for($user)->create(['currency' => 'CLP']);
    $category = Category::factory()->expense()->for($user)->create();

    $inactive = Budget::factory()->for($user)->inactive()->create([
        'currency' => 'CLP',
        'anchor_date' => '2026-02-01',
    ]);
    $inactive->items()->create(['category_id' => $category->id, 'amount' => 999]);

    $future = Budget::factory()->for($user)->create([
        'currency' => 'CLP',
        'anchor_date' => '2026-12-01',
    ]);
    $future->items()->create(['category_id' => $category->id, 'amount' => 500]);

    $active = Budget::factory()->for($user)->create([
        'currency' => 'CLP',
        'anchor_date' => '2026-02-01',
    ]);
    $active->items()->create(['category_id' => $category->id, 'amount' => 200]);

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('currencySummaries.0.reserved', 200)
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
            ->where('currencySummaries.0.reserved', 1000)
            ->where('currencySummaries.1.currency', 'USD')
            ->where('currencySummaries.1.reserved', 5)
        );
});
