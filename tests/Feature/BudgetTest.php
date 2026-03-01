<?php

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

test('creates a budget with multiple categories and optional account', function () {
    $this->seed(CurrencySeeder::class);

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
    $this->seed(CurrencySeeder::class);

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
    $accountA = Account::factory()->checking()->for($user)->create();
    $accountB = Account::factory()->checking()->for($user)->create();
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
        'payment_method_id' => null,
        'amount' => 100,
        'description' => 'Gasto ciclo cuenta A',
        'exclude_from_budget' => false,
        'transaction_date' => '2026-02-15',
    ]);
    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $accountB->id,
        'category_id' => $childCategory->id,
        'payment_method_id' => null,
        'amount' => 40,
        'description' => 'Gasto ciclo cuenta B',
        'exclude_from_budget' => false,
        'transaction_date' => '2026-02-16',
    ]);
    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $accountA->id,
        'category_id' => $childCategory->id,
        'payment_method_id' => null,
        'amount' => 30,
        'description' => 'Gasto excluido',
        'exclude_from_budget' => true,
        'transaction_date' => '2026-02-17',
    ]);
    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $accountA->id,
        'category_id' => $childCategory->id,
        'payment_method_id' => null,
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

test('supports history scope and account-specific budget filtering', function () {
    Carbon::setTestNow('2026-02-20 10:00:00');

    $user = User::factory()->create();
    $accountA = Account::factory()->checking()->for($user)->create();
    $accountB = Account::factory()->checking()->for($user)->create();
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
