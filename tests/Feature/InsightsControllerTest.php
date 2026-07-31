<?php

use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserSettings;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    Currency::updateOrCreate(['code' => 'CLP'], ['name' => 'Peso chileno', 'locale' => 'es-CL']);
    Carbon::setTestNow('2026-07-31');
});

afterEach(function () {
    Carbon::setTestNow();
});

test('guests are redirected to login', function () {
    $this->get('/insights')->assertRedirect('/login');
});

test('insights render an empty state without data', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/insights')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('insights')
            ->count('sections', 0)
        );
});

test('cash flow reports income and expense for the last twelve months', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['currency' => 'CLP']);
    $category = Category::factory()->expense()->for($user)->create();

    Transaction::factory()->income()->for($user)->create([
        'account_id' => $account->id,
        'amount' => 900000,
        'currency' => 'CLP',
        'transaction_date' => '2026-07-05',
    ]);
    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'category_id' => $category->id,
        'amount' => 250000,
        'currency' => 'CLP',
        'transaction_date' => '2026-07-09',
    ]);

    $this->actingAs($user)->get('/insights')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('sections.0.currency', 'CLP')
            ->has('sections.0.cash_flow', 12)
            ->where('sections.0.cash_flow.11.month', '2026-07')
            ->where('sections.0.cash_flow.11.income', 900000)
            ->where('sections.0.cash_flow.11.expense', 250000)
            ->where('sections.0.cash_flow.11.net', 650000)
        );
});

test('the compliance grid marks a cycle as over when spending exceeded the cap', function () {
    $user = User::factory()->create();
    UserSettings::factory()->for($user)->create(['budget_cycle_start_day' => 1]);
    $account = Account::factory()->for($user)->create(['currency' => 'CLP']);
    $category = Category::factory()->expense()->for($user)->create();

    $budget = Budget::factory()->for($user)->create([
        'account_id' => $account->id,
        'currency' => 'CLP',
        'frequency' => 'monthly',
        'anchor_date' => '2026-05-01',
    ]);
    BudgetItem::factory()->for($budget)->for($category)->create(['amount' => 100000]);

    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'category_id' => $category->id,
        'amount' => 130000,
        'currency' => 'CLP',
        'transaction_date' => '2026-06-15',
    ]);

    $this->actingAs($user)->get('/insights')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('sections.0.budgets.0.name', $budget->name)
            ->where('sections.0.budgets.0.total', 2)
            ->where('sections.0.budgets.0.kept', 1)
            ->where('sections.0.budgets.0.cycles.1.start', '2026-06-01')
            ->where('sections.0.budgets.0.cycles.1.status', 'over')
        );
});
