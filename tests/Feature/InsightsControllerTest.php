<?php

use App\Models\Account;
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

function insightsUser(int $cycleStartDay = 1): array
{
    $user = User::factory()->create();
    UserSettings::factory()->for($user)->create(['budget_cycle_start_day' => $cycleStartDay]);
    $account = Account::factory()->for($user)->create(['currency' => 'CLP']);

    return [$user, $account];
}

test('guests are redirected to login', function () {
    $this->get('/insights')->assertRedirect('/login');
});

test('insights render an empty state without data', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/insights')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('insights')
            ->count('year', 0)
            ->count('cycle', 0)
            ->where('years', [2026])
        );
});

test('the year series covers january through the cycle in progress', function () {
    [$user, $account] = insightsUser();
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
            ->where('year.0.currency', 'CLP')
            ->has('year.0.cash_flow', 7)
            ->where('year.0.cash_flow.0.start', '2026-01-01')
            ->where('year.0.cash_flow.6.start', '2026-07-01')
            ->where('year.0.cash_flow.6.income', 900000)
            ->where('year.0.cash_flow.6.expense', 250000)
            ->where('year.0.income', 900000)
            ->where('year.0.expense', 250000)
            ->where('year.0.net', 650000)
        );
});

test('year buckets follow the cut-off day, not the calendar month', function () {
    [$user, $account] = insightsUser(28);
    $category = Category::factory()->expense()->for($user)->create();

    // The cycle labelled "enero" runs Dec 28 – Jan 27.
    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'category_id' => $category->id,
        'amount' => 400000,
        'currency' => 'CLP',
        'transaction_date' => '2025-12-30',
    ]);

    $this->actingAs($user)->get('/insights')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('year.0.cash_flow.0.start', '2025-12-28')
            ->where('year.0.cash_flow.0.end', '2026-01-27')
            ->where('year.0.cash_flow.0.expense', 400000)
            ->where('year.0.start', '2025-12-28')
        );
});

test('the year breakdown ranks categories and nests subcategories', function () {
    [$user, $account] = insightsUser();
    $food = Category::factory()->expense()->for($user)->create(['name' => 'Comida']);
    $delivery = Category::factory()->expense()->for($user)->create([
        'name' => 'Delivery',
        'parent_id' => $food->id,
    ]);
    $transport = Category::factory()->expense()->for($user)->create(['name' => 'Transporte']);

    foreach ([[$food->id, 100000, '2026-03-04'], [$delivery->id, 60000, '2026-04-10'], [$transport->id, 40000, '2026-05-02']] as [$categoryId, $amount, $date]) {
        Transaction::factory()->expense()->for($user)->create([
            'account_id' => $account->id,
            'category_id' => $categoryId,
            'amount' => $amount,
            'currency' => 'CLP',
            'transaction_date' => $date,
        ]);
    }

    $this->actingAs($user)->get('/insights')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('year.0.categories.total', 200000)
            ->where('year.0.categories.rows.0.name', 'Comida')
            ->where('year.0.categories.rows.0.amount', 160000)
            ->where('year.0.categories.rows.0.share', 80)
            ->where('year.0.categories.rows.0.children.0.name', 'Sin subcategoría')
            ->where('year.0.categories.rows.0.children.0.amount', 100000)
            ->where('year.0.categories.rows.0.children.1.name', 'Delivery')
            ->where('year.0.categories.rows.0.children.1.amount', 60000)
            ->where('year.0.categories.rows.1.name', 'Transporte')
        );
});

test('the cycle block defaults to the cycle in progress', function () {
    [$user, $account] = insightsUser(28);
    $category = Category::factory()->expense()->for($user)->create(['name' => 'Comida']);

    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'category_id' => $category->id,
        'amount' => 30000,
        'currency' => 'CLP',
        'transaction_date' => '2026-07-30',
    ]);
    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'category_id' => $category->id,
        'amount' => 90000,
        'currency' => 'CLP',
        'transaction_date' => '2026-07-01',
    ]);

    $this->actingAs($user)->get('/insights')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('selectedCycle.start', '2026-07-28')
            ->where('selectedCycle.end', '2026-08-27')
            ->where('selectedCycle.is_current', true)
            ->where('cycle.0.expense', 30000)
            ->where('cycle.0.categories.rows.0.name', 'Comida')
            ->where('cycle.0.categories.rows.0.amount', 30000)
            ->where('cycle.0.categories.rows.0.delta', -60000)
        );
});

test('a previous cycle can be selected from the querystring', function () {
    [$user, $account] = insightsUser(28);
    $category = Category::factory()->expense()->for($user)->create(['name' => 'Comida']);

    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'category_id' => $category->id,
        'amount' => 90000,
        'currency' => 'CLP',
        'transaction_date' => '2026-07-01',
    ]);

    $this->actingAs($user)->get('/insights?cycle=2026-06-28')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('selectedCycle.start', '2026-06-28')
            ->where('selectedCycle.is_current', false)
            ->where('cycle.0.expense', 90000)
            ->where('cycle.0.categories.rows.0.amount', 90000)
            ->where('cycle.0.largest_expenses.0.amount', 90000)
        );
});

test('an unknown cycle or year falls back to the current one', function () {
    [$user, $account] = insightsUser();

    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'amount' => 1000,
        'currency' => 'CLP',
        'transaction_date' => '2026-07-10',
    ]);

    $this->actingAs($user)->get('/insights?cycle=1999-01-01&year=1999')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('selectedYear', 2026)
            ->where('selectedCycle.start', '2026-07-01')
        );
});

test('past years stay selectable and compare against the year before', function () {
    [$user, $account] = insightsUser();
    $category = Category::factory()->expense()->for($user)->create(['name' => 'Comida']);

    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'category_id' => $category->id,
        'amount' => 50000,
        'currency' => 'CLP',
        'transaction_date' => '2025-02-10',
    ]);
    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'category_id' => $category->id,
        'amount' => 80000,
        'currency' => 'CLP',
        'transaction_date' => '2024-02-10',
    ]);

    $this->actingAs($user)->get('/insights?year=2025')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('years', [2026, 2025, 2024])
            ->where('selectedYear', 2025)
            ->has('year.0.cash_flow', 12)
            ->where('year.0.categories.rows.0.amount', 50000)
            ->where('year.0.categories.rows.0.delta', -30000)
        );
});
