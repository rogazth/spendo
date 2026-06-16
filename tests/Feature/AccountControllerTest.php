<?php

use App\Models\Account;
use App\Models\Currency;
use App\Models\User;
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
    $this->get('/accounts')->assertRedirect('/login');
});

// ---------------------------------------------------------------------------
// Index
// ---------------------------------------------------------------------------

test('index renders accounts page for authenticated user', function () {
    $user = User::factory()->create();
    Account::factory()->for($user)->create();

    $this->actingAs($user)->get('/accounts')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('accounts/index')
            ->has('currencySummaries')
            ->has('totals')
        );
});

test('index groups accounts by currency with summary aggregates', function () {
    $user = User::factory()->create();

    $clpDefault = Account::factory()->for($user)->create([
        'name' => 'Banco CLP',
        'currency' => 'CLP',
        'is_default' => true,
    ]);
    $clpSavings = Account::factory()->for($user)->create([
        'name' => 'Ahorro CLP',
        'currency' => 'CLP',
        'is_default' => false,
    ]);
    $usd = Account::factory()->for($user)->create([
        'name' => 'Cuenta USD',
        'currency' => 'USD',
        'is_default' => false,
    ]);

    \App\Models\Transaction::factory()->for($user)->for($clpDefault)->create([
        'amount' => 500000,
        'currency' => 'CLP',
    ]);
    \App\Models\Transaction::factory()->for($user)->for($clpSavings)->create([
        'amount' => 200000,
        'currency' => 'CLP',
    ]);
    \App\Models\Transaction::factory()->for($user)->for($usd)->create([
        'amount' => -15000,
        'currency' => 'USD',
    ]);

    $this->actingAs($user)->get('/accounts')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('accounts/index')
            ->has('currencySummaries', 2)
            ->where('currencySummaries.0.currency', 'CLP')
            ->where('currencySummaries.0.accounts_count', 2)
            ->where('currencySummaries.0.total', 700000)
            ->where('currencySummaries.0.reserved_total', 0)
            ->where('currencySummaries.0.available', 700000)
            ->has('currencySummaries.0.accounts', 2)
            ->has('currencySummaries.0.accounts.0.budgets', 0)
            ->where('currencySummaries.1.currency', 'USD')
            ->where('currencySummaries.1.total', -15000)
            ->where('totals.accounts', 3)
            ->where('totals.currencies', 2)
            ->where('totals.budgeted', 0)
            ->where('totals.default_name', 'Banco CLP')
        );
});

test('index reports reserved and available accounting for budgets', function () {
    $user = User::factory()->create();
    \App\Models\UserSettings::factory()->for($user)->create(['budget_cycle_start_day' => 1]);

    $account = Account::factory()->for($user)->create([
        'name' => 'Banco CLP',
        'currency' => 'CLP',
        'is_default' => true,
    ]);
    $category = \App\Models\Category::factory()->expense()->for($user)->create();

    \App\Models\Transaction::factory()->for($user)->for($account)->create([
        'amount' => 500000,
        'currency' => 'CLP',
    ]);

    $budget = \App\Models\Budget::factory()->for($user)->create([
        'currency' => 'CLP',
        'frequency' => 'monthly',
        'anchor_date' => now()->startOfMonth()->toDateString(),
    ]);
    $budget->items()->create(['category_id' => $category->id, 'amount' => 120000]);
    $budget->update(['account_id' => $account->id]);

    \App\Models\Transaction::factory()->expense()->for($user)->for($account)->create([
        'category_id' => $category->id,
        'amount' => -50000,
        'currency' => 'CLP',
        'exclude_from_budget' => false,
        'transaction_date' => now()->toDateString(),
    ]);

    // budgeted = 120000, spent = 50000 -> reserved = 70000.
    // balance = 500000 - 50000 = 450000 -> available = 450000 - 70000 = 380000.
    $this->actingAs($user)->get('/accounts')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('currencySummaries.0.reserved_total', 70000)
            ->where('currencySummaries.0.available', 380000)
            ->where('currencySummaries.0.accounts.0.current_balance', 450000)
            ->where('currencySummaries.0.accounts.0.reserved', 70000)
            ->where('currencySummaries.0.accounts.0.available', 380000)
            ->has('currencySummaries.0.accounts.0.budgets', 1)
            ->where('currencySummaries.0.accounts.0.budgets.0.name', $budget->name)
            ->where('currencySummaries.0.accounts.0.budgets.0.reserved', 70000)
        );
});

test('index surfaces overspend and reserves caps from every active budget, matching the dashboard', function () {
    $user = User::factory()->create();
    \App\Models\UserSettings::factory()->for($user)->create(['budget_cycle_start_day' => 1]);

    $budgeted = Account::factory()->for($user)->create([
        'name' => 'Banco CLP',
        'currency' => 'CLP',
        'is_default' => true,
    ]);
    $free = Account::factory()->for($user)->create([
        'name' => 'Efectivo',
        'currency' => 'CLP',
        'is_default' => false,
    ]);

    $catOver = \App\Models\Category::factory()->expense()->for($user)->create();
    $catUnder = \App\Models\Category::factory()->expense()->for($user)->create();
    $catGhost = \App\Models\Category::factory()->expense()->for($user)->create();

    // Seed balances: budgeted account 1,000,000 income, free account 250,000.
    \App\Models\Transaction::factory()->for($user)->for($budgeted)->create([
        'amount' => 1000000,
        'currency' => 'CLP',
    ]);
    \App\Models\Transaction::factory()->for($user)->for($free)->create([
        'amount' => 250000,
        'currency' => 'CLP',
    ]);

    // Hogar: scoped to the budgeted account. One item overspent, one under.
    $hogar = \App\Models\Budget::factory()->for($user)->create([
        'currency' => 'CLP',
        'frequency' => 'monthly',
        'anchor_date' => now()->startOfMonth()->toDateString(),
    ]);
    $hogar->items()->create(['category_id' => $catOver->id, 'amount' => 300000]);
    $hogar->items()->create(['category_id' => $catUnder->id, 'amount' => 300000]);
    $hogar->update(['account_id' => $budgeted->id]);

    \App\Models\Transaction::factory()->expense()->for($user)->for($budgeted)->create([
        'category_id' => $catOver->id,
        'amount' => -500000, // over its 300k cap by 200k
        'currency' => 'CLP',
        'exclude_from_budget' => false,
        'transaction_date' => now()->toDateString(),
    ]);
    \App\Models\Transaction::factory()->expense()->for($user)->for($budgeted)->create([
        'category_id' => $catUnder->id,
        'amount' => -100000, // under its 300k cap by 200k
        'currency' => 'CLP',
        'exclude_from_budget' => false,
        'transaction_date' => now()->toDateString(),
    ]);

    // Fantasma: active budget with NO accounts attached and no spending. Its cap
    // is fully reserved. The dashboard counts it; the accounts page must too.
    $fantasma = \App\Models\Budget::factory()->for($user)->create([
        'currency' => 'CLP',
        'frequency' => 'monthly',
        'anchor_date' => now()->startOfMonth()->toDateString(),
    ]);
    $fantasma->items()->create(['category_id' => $catGhost->id, 'amount' => 150000]);

    // total cash      = 400,000 (budgeted) + 250,000 (free) = 650,000
    // reserved        = 200,000 (Hogar under-item) + 150,000 (Fantasma) = 350,000
    // overspend       = 200,000 (Hogar over-item)
    // available/ready = 650,000 - 350,000 = 300,000
    $this->actingAs($user)->get('/accounts')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('currencySummaries.0.currency', 'CLP')
            ->where('currencySummaries.0.total', 650000)
            ->where('currencySummaries.0.reserved_total', 350000)
            ->where('currencySummaries.0.unassigned_reserved', 150000)
            ->where('currencySummaries.0.overspend_total', 200000)
            ->where('currencySummaries.0.available', 300000)
            ->where('currencySummaries.0.accounts.0.name', 'Banco CLP')
            ->where('currencySummaries.0.accounts.0.reserved', 200000)
            ->where('currencySummaries.0.accounts.0.available', 200000)
            ->where('currencySummaries.0.accounts.0.budgets.0.name', $hogar->name)
            ->where('currencySummaries.0.accounts.0.budgets.0.reserved', 200000)
            ->where('currencySummaries.0.accounts.0.budgets.0.overspend', 200000)
        );
});

test('index puts the default account first within its currency group', function () {
    $user = User::factory()->create();

    Account::factory()->for($user)->create([
        'name' => 'Otra',
        'currency' => 'CLP',
        'is_default' => false,
        'sort_order' => 0,
    ]);
    $default = Account::factory()->for($user)->create([
        'name' => 'Principal',
        'currency' => 'CLP',
        'is_default' => true,
        'sort_order' => 5,
    ]);

    $this->actingAs($user)->get('/accounts')
        ->assertInertia(fn (Assert $page) => $page
            ->where('currencySummaries.0.accounts.0.uuid', $default->uuid)
            ->where('currencySummaries.0.accounts.0.is_default', true)
        );
});

// ---------------------------------------------------------------------------
// Store
// ---------------------------------------------------------------------------

test('store creates an account', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/accounts', [
        'name' => 'Mi Cuenta',
        'currency' => 'CLP',
    ])->assertRedirect('/accounts');

    $this->assertDatabaseHas('accounts', [
        'user_id' => $user->id,
        'name' => 'Mi Cuenta',
        'currency' => 'CLP',
    ]);
});

test('store creates an income transaction for initial balance', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/accounts', [
        'name' => 'Cuenta con saldo',
        'currency' => 'CLP',
        'initial_balance' => 100000,
    ])->assertRedirect('/accounts');

    $account = Account::query()->where('name', 'Cuenta con saldo')->firstOrFail();

    $this->assertDatabaseHas('transactions', [
        'user_id' => $user->id,
        'account_id' => $account->id,
        'amount' => 10000000,
        'description' => 'Balance inicial',
    ]);
});

test('store validates required fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/accounts', [])
        ->assertSessionHasErrors(['name', 'currency']);
});

test('store rejects duplicate account names for the same user', function () {
    $user = User::factory()->create();
    Account::factory()->for($user)->create(['name' => 'Mi Banco']);

    $this->actingAs($user)->post('/accounts', [
        'name' => 'Mi Banco',
        'currency' => 'CLP',
    ])->assertSessionHasErrors('name');
});

test('store rejects invalid currency code', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/accounts', [
        'name' => 'Cuenta Inválida',
        'currency' => 'XYZ',
    ])->assertSessionHasErrors('currency');
});

test('store rejects negative initial_balance', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/accounts', [
        'name' => 'Cuenta Negativa',
        'currency' => 'CLP',
        'initial_balance' => -100,
    ])->assertSessionHasErrors('initial_balance');
});

test('store sets default and clears other defaults', function () {
    $user = User::factory()->create();
    $existing = Account::factory()->for($user)->create(['is_default' => true]);

    $this->actingAs($user)->post('/accounts', [
        'name' => 'Nueva Cuenta',
        'currency' => 'CLP',
        'is_default' => true,
    ]);

    expect($existing->fresh()->is_default)->toBeFalse();
    $new = Account::query()->where('name', 'Nueva Cuenta')->firstOrFail();
    expect($new->is_default)->toBeTrue();
});

// ---------------------------------------------------------------------------
// Update
// ---------------------------------------------------------------------------

test('update modifies an account', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['name' => 'Original']);

    $this->actingAs($user)->put("/accounts/{$account->uuid}", [
        'name' => 'Actualizada',
        'currency' => 'CLP',
    ])->assertRedirect('/accounts');

    expect($account->fresh()->name)->toBe('Actualizada');
});

test('update returns 403 for another user account', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $account = Account::factory()->for($owner)->create();

    $this->actingAs($other)->put("/accounts/{$account->uuid}", [
        'name' => 'Hack',
        'currency' => 'CLP',
    ])->assertForbidden();
});

// ---------------------------------------------------------------------------
// makeDefault
// ---------------------------------------------------------------------------

test('makeDefault sets account as default', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['is_default' => false]);

    $this->actingAs($user)->patch("/accounts/{$account->uuid}/make-default")
        ->assertRedirect();

    expect($account->fresh()->is_default)->toBeTrue();
});

test('makeDefault returns 403 for another user account', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $account = Account::factory()->for($owner)->create();

    $this->actingAs($other)->patch("/accounts/{$account->uuid}/make-default")
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// Destroy
// ---------------------------------------------------------------------------

test('destroy soft-deletes an account', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();

    $this->actingAs($user)->delete("/accounts/{$account->uuid}")
        ->assertRedirect('/accounts');

    $this->assertModelMissing($account);
});

test('destroy returns 403 for another user account', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $account = Account::factory()->for($owner)->create();

    $this->actingAs($other)->delete("/accounts/{$account->uuid}")
        ->assertForbidden();
});
