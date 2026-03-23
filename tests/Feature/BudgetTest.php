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
});

test('creates a budget with multiple categories', function () {
    $user = User::factory()->create();
    $categoryA = Category::factory()->expense()->for($user)->create();
    $categoryB = Category::factory()->expense()->for($user)->create();

    $this->actingAs($user)->post('/budgets', [
        'name' => 'Budget test',
        'description' => 'Presupuesto principal',
        'currency' => 'CLP',
        'frequency' => 'monthly',
        'anchor_date' => now()->toDateString(),
        'ends_at' => null,
        'items' => [
            ['category_id' => $categoryA->id, 'amount' => 100000],
            ['category_id' => $categoryB->id, 'amount' => 60000],
        ],
    ])->assertRedirect('/budgets');

    $budget = Budget::query()->where('name', 'Budget test')->firstOrFail();
    expect($budget->frequency)->toBe('monthly');

    $this->assertDatabaseHas('budget_items', [
        'budget_id' => $budget->id,
        'category_id' => $categoryA->id,
    ]);
    $this->assertDatabaseHas('budget_items', [
        'budget_id' => $budget->id,
        'category_id' => $categoryB->id,
    ]);
});

test('rejects overlapping parent and child categories in the same budget', function () {
    $user = User::factory()->create();
    $parentCategory = Category::factory()->expense()->for($user)->create([
        'parent_id' => null,
        'name' => 'Comida',
    ]);
    $childCategory = Category::factory()->expense()->for($user)->create([
        'parent_id' => $parentCategory->id,
        'name' => 'Supermercado',
    ]);

    $this->actingAs($user)->post('/budgets', [
        'name' => 'Budget inválido',
        'currency' => 'CLP',
        'frequency' => 'monthly',
        'anchor_date' => now()->toDateString(),
        'items' => [
            ['category_id' => $parentCategory->id, 'amount' => 100000],
            ['category_id' => $childCategory->id, 'amount' => 50000],
        ],
    ])->assertSessionHasErrors('items');
});

test('calculates current cycle spending including children and excluding flagged transactions', function () {
    Carbon::setTestNow('2026-02-20 10:00:00');

    $user = User::factory()->create();
    $accountA = Account::factory()->for($user)->create(['currency' => 'CLP']);
    $accountB = Account::factory()->for($user)->create(['currency' => 'CLP']);
    $parentCategory = Category::factory()->expense()->for($user)->create([
        'parent_id' => null,
        'name' => 'Comida',
    ]);
    $childCategory = Category::factory()->expense()->for($user)->create([
        'parent_id' => $parentCategory->id,
        'name' => 'Supermercado',
    ]);

    $budget = Budget::factory()->for($user)->create([
        'name' => 'Food Budget',
        'currency' => 'CLP',
        'frequency' => 'monthly',
        'anchor_date' => '2026-01-10',
        'ends_at' => null,
    ]);
    $budget->items()->create([
        'category_id' => $parentCategory->id,
        'amount' => 500,
    ]);

    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $accountA->id,
        'category_id' => $childCategory->id,
        'amount' => 100,
        'currency' => 'CLP',
        'description' => 'Gasto ciclo cuenta A',
        'exclude_from_budget' => false,
        'transaction_date' => '2026-02-15',
    ]);
    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $accountB->id,
        'category_id' => $childCategory->id,
        'amount' => 40,
        'currency' => 'CLP',
        'description' => 'Gasto ciclo cuenta B',
        'exclude_from_budget' => false,
        'transaction_date' => '2026-02-16',
    ]);
    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $accountA->id,
        'category_id' => $childCategory->id,
        'amount' => 30,
        'currency' => 'CLP',
        'description' => 'Gasto excluido',
        'exclude_from_budget' => true,
        'transaction_date' => '2026-02-17',
    ]);
    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $accountA->id,
        'category_id' => $childCategory->id,
        'amount' => 60,
        'currency' => 'CLP',
        'description' => 'Gasto ciclo anterior',
        'exclude_from_budget' => false,
        'transaction_date' => '2026-01-20',
    ]);

    $response = $this->actingAs($user)->get("/budgets/{$budget->uuid}");

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('budgets/show')
        ->where('summary.spent', 140)
        ->where('categoryProgress.0.spent', 140)
        ->where('range.start', '2026-02-10')
        ->where('range.end', '2026-03-09')
    );

    Carbon::setTestNow();
});

test('only expense type counts toward budget spending', function () {
    Carbon::setTestNow('2026-02-20 10:00:00');

    $user = User::factory()->create();
    $accountA = Account::factory()->for($user)->create(['currency' => 'CLP']);
    $category = Category::factory()->expense()->for($user)->create();

    $budget = Budget::factory()->for($user)->create([
        'currency' => 'CLP',
        'frequency' => 'monthly',
        'anchor_date' => '2026-02-01',
    ]);
    $budget->items()->create(['category_id' => $category->id, 'amount' => 1000]);

    Transaction::factory()->income()->for($user)->create([
        'account_id' => $accountA->id,
        'category_id' => $category->id,
        'currency' => 'CLP',
        'amount' => 200,
        'exclude_from_budget' => false,
        'transaction_date' => '2026-02-10',
    ]);
    Transaction::factory()->transferOut()->for($user)->create([
        'account_id' => $accountA->id,
        'category_id' => $category->id,
        'currency' => 'CLP',
        'amount' => 100,
        'exclude_from_budget' => false,
        'transaction_date' => '2026-02-10',
    ]);

    $this->actingAs($user)->get("/budgets/{$budget->uuid}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.spent', 0)
        );

    Carbon::setTestNow();
});

test('budget only counts transactions matching its currency', function () {
    Carbon::setTestNow('2026-02-20 10:00:00');

    $user = User::factory()->create();
    $clpAccount = Account::factory()->for($user)->create(['currency' => 'CLP']);
    $usdAccount = Account::factory()->for($user)->create(['currency' => 'USD']);
    $category = Category::factory()->expense()->for($user)->create();

    $clpBudget = Budget::factory()->for($user)->create([
        'currency' => 'CLP',
        'frequency' => 'monthly',
        'anchor_date' => '2026-02-01',
    ]);
    $clpBudget->items()->create(['category_id' => $category->id, 'amount' => 1000]);

    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $clpAccount->id,
        'category_id' => $category->id,
        'currency' => 'CLP',
        'amount' => 300,
        'exclude_from_budget' => false,
        'transaction_date' => '2026-02-10',
    ]);
    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $usdAccount->id,
        'category_id' => $category->id,
        'currency' => 'USD',
        'amount' => 100,
        'exclude_from_budget' => false,
        'transaction_date' => '2026-02-10',
    ]);

    $this->actingAs($user)->get("/budgets/{$clpBudget->uuid}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.spent', 300)
        );

    Carbon::setTestNow();
});

test('cycle boundary: exact anchor date is included in budget spending', function () {
    Carbon::setTestNow('2026-02-20 10:00:00');

    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['currency' => 'CLP']);
    $category = Category::factory()->expense()->for($user)->create();

    $budget = Budget::factory()->for($user)->create([
        'currency' => 'CLP',
        'frequency' => 'monthly',
        'anchor_date' => '2026-02-10',
    ]);
    $budget->items()->create(['category_id' => $category->id, 'amount' => 1000]);

    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'category_id' => $category->id,
        'currency' => 'CLP',
        'amount' => 50,
        'exclude_from_budget' => false,
        'transaction_date' => '2026-02-10',
    ]);

    $this->actingAs($user)->get("/budgets/{$budget->uuid}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.spent', 50)
        );

    Carbon::setTestNow();
});

test('cycle boundary: exact end date is included in budget spending', function () {
    Carbon::setTestNow('2026-03-09 10:00:00');

    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['currency' => 'CLP']);
    $category = Category::factory()->expense()->for($user)->create();

    $budget = Budget::factory()->for($user)->create([
        'currency' => 'CLP',
        'frequency' => 'monthly',
        'anchor_date' => '2026-02-10',
    ]);
    $budget->items()->create(['category_id' => $category->id, 'amount' => 1000]);

    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'category_id' => $category->id,
        'currency' => 'CLP',
        'amount' => 75,
        'exclude_from_budget' => false,
        'transaction_date' => '2026-03-09',
    ]);

    $this->actingAs($user)->get("/budgets/{$budget->uuid}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.spent', 75)
            ->where('range.end', '2026-03-09')
        );

    Carbon::setTestNow();
});

test('transaction one day before cycle start is excluded from budget', function () {
    Carbon::setTestNow('2026-02-20 10:00:00');

    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['currency' => 'CLP']);
    $category = Category::factory()->expense()->for($user)->create();

    $budget = Budget::factory()->for($user)->create([
        'currency' => 'CLP',
        'frequency' => 'monthly',
        'anchor_date' => '2026-02-10',
    ]);
    $budget->items()->create(['category_id' => $category->id, 'amount' => 1000]);

    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'category_id' => $category->id,
        'currency' => 'CLP',
        'amount' => 100,
        'exclude_from_budget' => false,
        'transaction_date' => '2026-02-09',
    ]);

    $this->actingAs($user)->get("/budgets/{$budget->uuid}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.spent', 0)
        );

    Carbon::setTestNow();
});

test('biweekly frequency computes correct cycle range', function () {
    Carbon::setTestNow('2026-03-10 10:00:00');

    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['currency' => 'CLP']);
    $category = Category::factory()->expense()->for($user)->create();

    $budget = Budget::factory()->for($user)->create([
        'currency' => 'CLP',
        'frequency' => 'biweekly',
        'anchor_date' => '2026-03-01',
    ]);
    $budget->items()->create(['category_id' => $category->id, 'amount' => 1000]);

    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'category_id' => $category->id,
        'currency' => 'CLP',
        'amount' => 80,
        'exclude_from_budget' => false,
        'transaction_date' => '2026-03-10',
    ]);

    $this->actingAs($user)->get("/budgets/{$budget->uuid}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('range.start', '2026-03-01')
            ->where('range.end', '2026-03-14')
            ->where('summary.spent', 80)
        );

    Carbon::setTestNow();
});

test('history scope returns all transactions since anchor date', function () {
    Carbon::setTestNow('2026-02-20 10:00:00');

    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['currency' => 'CLP']);
    $category = Category::factory()->expense()->for($user)->create();

    $budget = Budget::factory()->for($user)->create([
        'currency' => 'CLP',
        'frequency' => 'monthly',
        'anchor_date' => '2026-01-10',
        'ends_at' => null,
    ]);
    $budget->items()->create([
        'category_id' => $category->id,
        'amount' => 800,
    ]);

    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'category_id' => $category->id,
        'currency' => 'CLP',
        'amount' => 100,
        'exclude_from_budget' => false,
        'transaction_date' => '2026-02-15',
    ]);
    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'category_id' => $category->id,
        'currency' => 'CLP',
        'amount' => 60,
        'exclude_from_budget' => false,
        'transaction_date' => '2026-01-20',
    ]);

    $this->actingAs($user)->get("/budgets/{$budget->uuid}?scope=history")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('budgets/show')
            ->where('scope', 'history')
            ->where('range.start', '2026-01-10')
            ->where('range.end', '2026-02-20')
            ->where('transactions.meta.total', 2)
        );

    Carbon::setTestNow();
});
