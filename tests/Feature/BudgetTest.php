<?php

use App\Models\Account;
use App\Models\Budget;
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
});

test('creates a budget with multiple categories', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['currency' => 'CLP']);
    $categoryA = Category::factory()->expense()->for($user)->create();
    $categoryB = Category::factory()->expense()->for($user)->create();

    $this->actingAs($user)->post('/budgets', [
        'name' => 'Budget test',
        'description' => 'Presupuesto principal',
        'currency' => 'CLP',
        'frequency' => 'monthly',
        'anchor_date' => now()->toDateString(),
        'ends_at' => null,
        'account_id' => $account->id,
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
    $this->assertDatabaseHas('budgets', [
        'id' => $budget->id,
        'account_id' => $account->id,
    ]);
});

test('allows two budgets to share the same account', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['currency' => 'CLP']);
    $category = Category::factory()->expense()->for($user)->create();

    Budget::factory()->for($user)->create([
        'currency' => 'CLP',
        'account_id' => $account->id,
    ]);

    $this->actingAs($user)->post('/budgets', [
        'name' => 'Otro budget',
        'currency' => 'CLP',
        'frequency' => 'monthly',
        'anchor_date' => now()->toDateString(),
        'account_id' => $account->id,
        'items' => [
            ['category_id' => $category->id, 'amount' => 1000],
        ],
    ])->assertRedirect('/budgets')->assertSessionHasNoErrors();

    expect(Budget::query()->where('account_id', $account->id)->count())->toBe(2);
});

test('rejects a budget without an account', function () {
    $user = User::factory()->create();
    $category = Category::factory()->expense()->for($user)->create();

    $this->actingAs($user)->post('/budgets', [
        'name' => 'Sin cuenta',
        'currency' => 'CLP',
        'frequency' => 'monthly',
        'anchor_date' => now()->toDateString(),
        'items' => [
            ['category_id' => $category->id, 'amount' => 1000],
        ],
    ])->assertSessionHasErrors('account_id');
});

test('rejects a budget whose account currency differs', function () {
    $user = User::factory()->create();
    $usdAccount = Account::factory()->for($user)->usd()->create();
    $category = Category::factory()->expense()->for($user)->create();

    $this->actingAs($user)->post('/budgets', [
        'name' => 'Budget CLP',
        'currency' => 'CLP',
        'frequency' => 'monthly',
        'anchor_date' => now()->toDateString(),
        'account_id' => $usdAccount->id,
        'items' => [
            ['category_id' => $category->id, 'amount' => 1000],
        ],
    ])->assertSessionHasErrors('account_id');
});

test('rejects overlapping parent and child categories in the same budget', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['currency' => 'CLP']);
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
        'account_id' => $account->id,
        'items' => [
            ['category_id' => $parentCategory->id, 'amount' => 100000],
            ['category_id' => $childCategory->id, 'amount' => 50000],
        ],
    ])->assertSessionHasErrors('items');
});

test('calculates current cycle spending including children and excluding flagged transactions', function () {
    Carbon::setTestNow('2026-02-20 10:00:00');

    $user = User::factory()->create();
    UserSettings::factory()->for($user)->create(['budget_cycle_start_day' => 10]);
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
        ->where('summary.current_cycle_start', '2026-02-10')
        ->where('summary.current_cycle_end', '2026-03-09')
    );

    Carbon::setTestNow();
});

test('budget spending excludes income and transfers (only expenses count)', function () {
    Carbon::setTestNow('2026-02-20 10:00:00');

    $user = User::factory()->create();
    $accountA = Account::factory()->for($user)->create(['currency' => 'CLP']);
    $accountB = Account::factory()->for($user)->create(['currency' => 'CLP']);
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

    $transferOut = Transaction::factory()->transferOut()->for($user)->create([
        'account_id' => $accountA->id,
        'category_id' => $category->id,
        'currency' => 'CLP',
        'amount' => -100,
        'exclude_from_budget' => false,
        'transaction_date' => '2026-02-10',
    ]);
    $transferIn = Transaction::factory()->transferIn()->for($user)->create([
        'account_id' => $accountB->id,
        'category_id' => $category->id,
        'currency' => 'CLP',
        'amount' => 100,
        'exclude_from_budget' => false,
        'transaction_date' => '2026-02-10',
        'linked_transaction_id' => $transferOut->id,
    ]);
    $transferOut->update(['linked_transaction_id' => $transferIn->id]);

    $this->actingAs($user)->get("/budgets/{$budget->uuid}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.spent', 0)
        );

    Carbon::setTestNow();
});

test('budget spending excludes transactions from accounts not associated with the budget', function () {
    Carbon::setTestNow('2026-02-20 10:00:00');

    $user = User::factory()->create();
    $includedAccount = Account::factory()->for($user)->create([
        'currency' => 'CLP',
    ]);
    $excludedAccount = Account::factory()->for($user)->create([
        'currency' => 'CLP',
    ]);
    $category = Category::factory()->expense()->for($user)->create();

    $budget = Budget::factory()->for($user)->create([
        'currency' => 'CLP',
        'frequency' => 'monthly',
        'anchor_date' => '2026-02-01',
    ]);
    $budget->items()->create(['category_id' => $category->id, 'amount' => 1000]);
    $budget->update(['account_id' => $includedAccount->id]);

    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $includedAccount->id,
        'category_id' => $category->id,
        'currency' => 'CLP',
        'amount' => 100,
        'exclude_from_budget' => false,
        'transaction_date' => '2026-02-10',
    ]);
    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $excludedAccount->id,
        'category_id' => $category->id,
        'currency' => 'CLP',
        'amount' => 900,
        'exclude_from_budget' => false,
        'transaction_date' => '2026-02-10',
    ]);

    $this->actingAs($user)->get("/budgets/{$budget->uuid}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.spent', 100)
            ->where('categoryProgress.0.spent', 100)
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
    UserSettings::factory()->for($user)->create(['budget_cycle_start_day' => 10]);
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
    UserSettings::factory()->for($user)->create(['budget_cycle_start_day' => 10]);
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
            ->where('summary.current_cycle_end', '2026-03-09')
        );

    Carbon::setTestNow();
});

test('transaction one day before cycle start is excluded from budget', function () {
    Carbon::setTestNow('2026-02-20 10:00:00');

    $user = User::factory()->create();
    UserSettings::factory()->for($user)->create(['budget_cycle_start_day' => 10]);
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
            ->where('summary.current_cycle_start', '2026-03-01')
            ->where('summary.current_cycle_end', '2026-03-14')
            ->where('summary.spent', 80)
        );

    Carbon::setTestNow();
});

test('show only reflects current cycle spending', function () {
    Carbon::setTestNow('2026-02-20 10:00:00');

    $user = User::factory()->create();
    UserSettings::factory()->for($user)->create(['budget_cycle_start_day' => 10]);
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

    // In current cycle (2026-02-10 .. 2026-03-09): counted.
    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'category_id' => $category->id,
        'currency' => 'CLP',
        'amount' => 100,
        'exclude_from_budget' => false,
        'transaction_date' => '2026-02-15',
    ]);
    // Outside current cycle: ignored.
    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'category_id' => $category->id,
        'currency' => 'CLP',
        'amount' => 60,
        'exclude_from_budget' => false,
        'transaction_date' => '2026-01-20',
    ]);

    $this->actingAs($user)->get("/budgets/{$budget->uuid}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('budgets/show')
            ->where('summary.spent', 100)
            ->where('summary.current_cycle_start', '2026-02-10')
            ->where('summary.current_cycle_end', '2026-03-09')
            ->missing('transactions')
            ->missing('scope')
        );

    Carbon::setTestNow();
});

test('monthly budget cycle follows the user global cycle start day, not the anchor', function () {
    Carbon::setTestNow('2026-06-10 10:00:00');

    $user = User::factory()->create();
    UserSettings::factory()->for($user)->create(['budget_cycle_start_day' => 29]);
    $account = Account::factory()->for($user)->create(['currency' => 'CLP']);
    $category = Category::factory()->expense()->for($user)->create();

    // Anchor day (3rd) is intentionally different from the global cycle day (29th).
    $budget = Budget::factory()->for($user)->create([
        'currency' => 'CLP',
        'frequency' => 'monthly',
        'anchor_date' => '2026-01-03',
        'ends_at' => null,
    ]);
    $budget->items()->create(['category_id' => $category->id, 'amount' => 1000]);

    // In cycle (2026-05-29 .. 2026-06-28): counted.
    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'category_id' => $category->id,
        'currency' => 'CLP',
        'amount' => 120,
        'exclude_from_budget' => false,
        'transaction_date' => '2026-06-05',
    ]);
    // Before the cycle start: ignored.
    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'category_id' => $category->id,
        'currency' => 'CLP',
        'amount' => 70,
        'exclude_from_budget' => false,
        'transaction_date' => '2026-05-28',
    ]);

    $this->actingAs($user)->get("/budgets/{$budget->uuid}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.current_cycle_start', '2026-05-29')
            ->where('summary.current_cycle_end', '2026-06-28')
            ->where('summary.spent', 120)
        );

    Carbon::setTestNow();
});
