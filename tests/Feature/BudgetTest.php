<?php

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Instrument;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    Currency::updateOrCreate(['code' => 'CLP'], ['name' => 'Peso chileno', 'locale' => 'es-CL']);
});

test('creates a budget with multiple categories and optional account', function () {
    $user = User::factory()->create();
    $categoryA = Category::factory()->expense()->for($user)->create();
    $categoryB = Category::factory()->expense()->for($user)->create();

    $this->actingAs($user)->post('/budgets', [
        'name' => 'Budget test',
        'description' => 'Presupuesto principal',
        'account_id' => null,
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
    expect($budget->account_id)->toBeNull();
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
    $accountA = Account::factory()->for($user)->create();
    $accountB = Account::factory()->for($user)->create();
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
        'account_id' => null,
    ]);
    $budget->items()->create([
        'category_id' => $parentCategory->id,
        'amount' => 500,
    ]);

    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $accountA->id,
        'category_id' => $childCategory->id,
        'instrument_id' => null,
        'amount' => 100,
        'description' => 'Gasto ciclo cuenta A',
        'exclude_from_budget' => false,
        'transaction_date' => '2026-02-15',
    ]);
    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $accountB->id,
        'category_id' => $childCategory->id,
        'instrument_id' => null,
        'amount' => 40,
        'description' => 'Gasto ciclo cuenta B',
        'exclude_from_budget' => false,
        'transaction_date' => '2026-02-16',
    ]);
    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $accountA->id,
        'category_id' => $childCategory->id,
        'instrument_id' => null,
        'amount' => 30,
        'description' => 'Gasto excluido',
        'exclude_from_budget' => true,
        'transaction_date' => '2026-02-17',
    ]);
    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $accountA->id,
        'category_id' => $childCategory->id,
        'instrument_id' => null,
        'amount' => 60,
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

test('settlement is excluded from budget spending', function () {
    Carbon::setTestNow('2026-02-20 10:00:00');

    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $cc = Instrument::factory()->for($user)->create(['type' => 'credit_card']);
    $category = Category::factory()->expense()->for($user)->create();

    $budget = Budget::factory()->for($user)->create([
        'frequency' => 'monthly',
        'anchor_date' => '2026-02-01',
        'account_id' => null,
    ]);
    $budget->items()->create(['category_id' => $category->id, 'amount' => 500]);

    Transaction::factory()->settlement()->for($user)->create([
        'account_id' => null,
        'instrument_id' => $cc->id,
        'category_id' => $category->id,
        'exclude_from_budget' => false,
        'transaction_date' => '2026-02-10',
    ]);

    $this->actingAs($user)->get("/budgets/{$budget->uuid}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('budgets/show')
            ->where('summary.spent', 0)
        );

    Carbon::setTestNow();
});

test('only expense type counts toward budget spending', function () {
    Carbon::setTestNow('2026-02-20 10:00:00');

    $user = User::factory()->create();
    $accountA = Account::factory()->for($user)->create();
    $accountB = Account::factory()->for($user)->create();
    $category = Category::factory()->expense()->for($user)->create();

    $budget = Budget::factory()->for($user)->create([
        'frequency' => 'monthly',
        'anchor_date' => '2026-02-01',
        'account_id' => null,
    ]);
    $budget->items()->create(['category_id' => $category->id, 'amount' => 1000]);

    // Income in the budget category
    Transaction::factory()->income()->for($user)->create([
        'account_id' => $accountA->id,
        'category_id' => $category->id,
        'instrument_id' => null,
        'amount' => 200,
        'exclude_from_budget' => false,
        'transaction_date' => '2026-02-10',
    ]);
    // Transfer out in the budget category
    Transaction::factory()->transferOut()->for($user)->create([
        'account_id' => $accountA->id,
        'category_id' => $category->id,
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

test('cycle boundary: exact anchor date is included in budget spending', function () {
    Carbon::setTestNow('2026-02-20 10:00:00');

    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $category = Category::factory()->expense()->for($user)->create();

    $budget = Budget::factory()->for($user)->create([
        'frequency' => 'monthly',
        'anchor_date' => '2026-02-10',
        'account_id' => null,
    ]);
    $budget->items()->create(['category_id' => $category->id, 'amount' => 1000]);

    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'category_id' => $category->id,
        'instrument_id' => null,
        'amount' => 50,
        'exclude_from_budget' => false,
        'transaction_date' => '2026-02-10', // exactly on anchor_date
    ]);

    $this->actingAs($user)->get("/budgets/{$budget->uuid}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.spent', 50)
        );

    Carbon::setTestNow();
});

test('cycle boundary: exact end date is included in budget spending', function () {
    // At now=Mar 9, anchor=Feb 10 monthly → cycle is Feb 10 – Mar 9. Mar 9 is the last day.
    Carbon::setTestNow('2026-03-09 10:00:00');

    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $category = Category::factory()->expense()->for($user)->create();

    $budget = Budget::factory()->for($user)->create([
        'frequency' => 'monthly',
        'anchor_date' => '2026-02-10',
        'account_id' => null,
    ]);
    $budget->items()->create(['category_id' => $category->id, 'amount' => 1000]);

    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'category_id' => $category->id,
        'instrument_id' => null,
        'amount' => 75,
        'exclude_from_budget' => false,
        'transaction_date' => '2026-03-09', // exactly the last day of the cycle
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
    $account = Account::factory()->for($user)->create();
    $category = Category::factory()->expense()->for($user)->create();

    $budget = Budget::factory()->for($user)->create([
        'frequency' => 'monthly',
        'anchor_date' => '2026-02-10',
        'account_id' => null,
    ]);
    $budget->items()->create(['category_id' => $category->id, 'amount' => 1000]);

    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'category_id' => $category->id,
        'instrument_id' => null,
        'amount' => 100,
        'exclude_from_budget' => false,
        'transaction_date' => '2026-02-09', // one day before anchor_date
    ]);

    $this->actingAs($user)->get("/budgets/{$budget->uuid}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.spent', 0)
        );

    Carbon::setTestNow();
});

test('biweekly frequency computes correct cycle range', function () {
    // Anchor March 1, test date March 10 → range is March 1–14
    Carbon::setTestNow('2026-03-10 10:00:00');

    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $category = Category::factory()->expense()->for($user)->create();

    $budget = Budget::factory()->for($user)->create([
        'frequency' => 'biweekly',
        'anchor_date' => '2026-03-01',
        'account_id' => null,
    ]);
    $budget->items()->create(['category_id' => $category->id, 'amount' => 1000]);

    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'category_id' => $category->id,
        'instrument_id' => null,
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

test('supports history scope and account-specific budget filtering', function () {
    Carbon::setTestNow('2026-02-20 10:00:00');

    $user = User::factory()->create();
    $accountA = Account::factory()->for($user)->create();
    $accountB = Account::factory()->for($user)->create();
    $parentCategory = Category::factory()->expense()->for($user)->create([
        'parent_id' => null,
    ]);
    $childCategory = Category::factory()->expense()->for($user)->create([
        'parent_id' => $parentCategory->id,
    ]);

    $sharedBudget = Budget::factory()->for($user)->create([
        'frequency' => 'monthly',
        'anchor_date' => '2026-01-10',
        'ends_at' => null,
        'account_id' => null,
    ]);
    $sharedBudget->items()->create([
        'category_id' => $parentCategory->id,
        'amount' => 800,
    ]);

    $accountBudget = Budget::factory()->for($user)->create([
        'frequency' => 'monthly',
        'anchor_date' => '2026-01-10',
        'ends_at' => null,
        'account_id' => $accountA->id,
    ]);
    $accountBudget->items()->create([
        'category_id' => $parentCategory->id,
        'amount' => 500,
    ]);

    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $accountA->id,
        'category_id' => $childCategory->id,
        'amount' => 100,
        'description' => 'Shared current A',
        'exclude_from_budget' => false,
        'transaction_date' => '2026-02-15',
    ]);
    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $accountB->id,
        'category_id' => $childCategory->id,
        'amount' => 40,
        'description' => 'Shared current B',
        'exclude_from_budget' => false,
        'transaction_date' => '2026-02-16',
    ]);
    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $accountA->id,
        'category_id' => $childCategory->id,
        'amount' => 60,
        'description' => 'Shared historical',
        'exclude_from_budget' => false,
        'transaction_date' => '2026-01-20',
    ]);

    $historyResponse = $this->actingAs($user)->get(
        "/budgets/{$sharedBudget->uuid}?scope=history",
    );

    $historyResponse->assertOk();
    $historyResponse->assertInertia(fn (Assert $page) => $page
        ->component('budgets/show')
        ->where('scope', 'history')
        ->where('range.start', '2026-01-10')
        ->where('range.end', '2026-02-20')
        ->where('transactions.meta.total', 3)
    );

    $accountResponse = $this->actingAs($user)->get("/budgets/{$accountBudget->uuid}");

    $accountResponse->assertOk();
    $accountResponse->assertInertia(fn (Assert $page) => $page
        ->component('budgets/show')
        ->where('summary.spent', 100)
        ->where('transactions.meta.total', 1)
    );

    Carbon::setTestNow();
});
