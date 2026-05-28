<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Transaction;
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
    $this->get('/transactions')->assertRedirect('/login');
});

// ---------------------------------------------------------------------------
// Index
// ---------------------------------------------------------------------------

test('index renders transactions page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/transactions')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('transactions/index')
            ->has('transactions')
            ->has('accounts')
            ->has('categories')
            ->has('filters')
        );
});

test('index filters by date range', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();

    Transaction::factory()->income()->for($user)->create([
        'account_id' => $account->id,
        'transaction_date' => '2026-01-15',
    ]);
    Transaction::factory()->income()->for($user)->create([
        'account_id' => $account->id,
        'transaction_date' => '2026-02-15',
    ]);

    $this->actingAs($user)->get('/transactions?date_from=2026-02-01&date_to=2026-02-28')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('transactions.meta.total', 1)
        );
});

test('index filters by account', function () {
    $user = User::factory()->create();
    $accountA = Account::factory()->for($user)->create();
    $accountB = Account::factory()->for($user)->create();

    Transaction::factory()->income()->for($user)->create(['account_id' => $accountA->id]);
    Transaction::factory()->income()->for($user)->create(['account_id' => $accountA->id]);
    Transaction::factory()->income()->for($user)->create(['account_id' => $accountB->id]);

    $this->actingAs($user)->get("/transactions?account_ids[]={$accountA->id}&dates=all")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('transactions.meta.total', 2));
});

test('index defaults to user default account when no account filter is set', function () {
    $user = User::factory()->create();
    $defaultAccount = Account::factory()->for($user)->create(['is_default' => true]);
    $otherAccount = Account::factory()->for($user)->create(['is_default' => false]);

    Transaction::factory()->income()->for($user)->create(['account_id' => $defaultAccount->id]);
    Transaction::factory()->income()->for($user)->create(['account_id' => $otherAccount->id]);

    $this->actingAs($user)->get('/transactions?dates=all')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('transactions.meta.total', 1)
            ->where('filters.account_ids', [$defaultAccount->id])
        );
});

test('index summary reports income and expenses per currency', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['currency' => 'CLP']);

    Transaction::factory()->income()->for($user)->create([
        'account_id' => $account->id,
        'currency' => 'CLP',
        'amount' => 150000,
    ]);
    Transaction::factory()->income()->for($user)->create([
        'account_id' => $account->id,
        'currency' => 'CLP',
        'amount' => 50000,
    ]);
    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'currency' => 'CLP',
        'amount' => -30000,
    ]);

    $this->actingAs($user)->get("/transactions?account_ids[]={$account->id}&dates=all")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.CLP.income', 200000)
            ->where('summary.CLP.expenses', 30000)
            ->where('summary.CLP.net', 170000)
        );
});

// ---------------------------------------------------------------------------
// Store — expense / income (signed amount)
// ---------------------------------------------------------------------------

test('store creates an expense transaction (negative amount)', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $category = Category::factory()->for($user)->create();

    $this->actingAs($user)->post('/transactions', [
        'account_id' => $account->id,
        'category_id' => $category->id,
        'amount' => -5000,
        'currency' => 'CLP',
        'transaction_date' => '2026-02-15',
    ])->assertRedirect('/transactions');

    $this->assertDatabaseHas('transactions', [
        'user_id' => $user->id,
        'account_id' => $account->id,
        'amount' => -500000,
    ]);
});

test('store creates an income transaction (positive amount)', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();

    $this->actingAs($user)->post('/transactions', [
        'account_id' => $account->id,
        'amount' => 100000,
        'currency' => 'CLP',
        'transaction_date' => '2026-02-15',
    ])->assertRedirect('/transactions');

    $this->assertDatabaseHas('transactions', [
        'user_id' => $user->id,
        'account_id' => $account->id,
        'amount' => 10000000,
    ]);
});

test('store rejects legacy transaction type field', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();

    $this->actingAs($user)->post('/transactions', [
        'type' => 'expense',
        'account_id' => $account->id,
        'amount' => 1000,
        'transaction_date' => '2026-02-15',
    ])->assertSessionHasErrors('type');

    expect(Transaction::where('user_id', $user->id)->count())->toBe(0);
});

test('store validates required account_id', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/transactions', [
        'amount' => -100,
        'currency' => 'CLP',
        'transaction_date' => '2026-02-15',
    ])->assertSessionHasErrors('account_id');
});

test('store rejects zero amount', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();

    $this->actingAs($user)->post('/transactions', [
        'account_id' => $account->id,
        'amount' => 0,
        'currency' => 'CLP',
        'transaction_date' => '2026-02-15',
    ])->assertSessionHasErrors('amount');
});

test('store rejects invalid transaction_date', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();

    $this->actingAs($user)->post('/transactions', [
        'account_id' => $account->id,
        'amount' => 100,
        'currency' => 'CLP',
        'transaction_date' => 'not-a-date',
    ])->assertSessionHasErrors('transaction_date');
});

test('store returns 404 when account belongs to another user', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $account = Account::factory()->for($owner)->create();

    $this->actingAs($other)->post('/transactions', [
        'account_id' => $account->id,
        'amount' => -100,
        'currency' => 'CLP',
        'transaction_date' => '2026-02-15',
    ])->assertNotFound();
});

// ---------------------------------------------------------------------------
// Store transfer — POST /transfers
// ---------------------------------------------------------------------------

test('store transfer creates two linked legs with opposite signs', function () {
    $user = User::factory()->create();
    $origin = Account::factory()->for($user)->create();
    $destination = Account::factory()->for($user)->create();

    $this->actingAs($user)->post('/transfers', [
        'origin_account_id' => $origin->id,
        'destination_account_id' => $destination->id,
        'amount' => 50000,
        'transaction_date' => '2026-02-15',
    ])->assertRedirect('/transactions');

    $out = Transaction::query()
        ->where('account_id', $origin->id)
        ->whereNotNull('linked_transaction_id')
        ->firstOrFail();
    $in = Transaction::query()
        ->where('account_id', $destination->id)
        ->whereNotNull('linked_transaction_id')
        ->firstOrFail();

    expect($out->linked_transaction_id)->toBe($in->id);
    expect($in->linked_transaction_id)->toBe($out->id);
    expect($out->amount)->toBeLessThan(0);
    expect($in->amount)->toBeGreaterThan(0);
    expect(abs($out->amount))->toEqual($in->amount);
});

test('store transfer requires both accounts', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/transfers', [
        'amount' => 1000,
        'transaction_date' => '2026-02-15',
    ])->assertSessionHasErrors(['origin_account_id', 'destination_account_id']);
});

test('store transfer rejects same origin and destination account', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();

    $this->actingAs($user)->post('/transfers', [
        'origin_account_id' => $account->id,
        'destination_account_id' => $account->id,
        'amount' => 1000,
        'transaction_date' => '2026-02-15',
    ])->assertSessionHasErrors(['origin_account_id', 'destination_account_id']);
});

test('store transfer returns 404 when accounts belong to another user', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $ownerAccountA = Account::factory()->for($owner)->create();
    $ownerAccountB = Account::factory()->for($owner)->create();

    $this->actingAs($other)->post('/transfers', [
        'origin_account_id' => $ownerAccountA->id,
        'destination_account_id' => $ownerAccountB->id,
        'amount' => 1000,
        'transaction_date' => '2026-02-15',
    ])->assertNotFound();
});

// ---------------------------------------------------------------------------
// Update
// ---------------------------------------------------------------------------

test('update modifies an expense transaction', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();

    $transaction = Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'amount' => -1000,
        'currency' => 'CLP',
        'transaction_date' => '2026-01-10',
    ]);

    $this->actingAs($user)->put("/transactions/{$transaction->uuid}", [
        'account_id' => $account->id,
        'amount' => -2000,
        'currency' => 'CLP',
        'transaction_date' => '2026-01-15',
    ])->assertRedirect('/transactions');

    expect($transaction->fresh()->amount)->toEqual(-2000);
});

test('update rejects legacy transaction type field', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();

    $transaction = Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'amount' => -1000,
        'currency' => 'CLP',
        'transaction_date' => '2026-01-10',
    ]);

    $this->actingAs($user)->put("/transactions/{$transaction->uuid}", [
        'type' => 'income',
        'account_id' => $account->id,
        'amount' => 2000,
        'transaction_date' => '2026-01-15',
    ])->assertSessionHasErrors('type');

    expect($transaction->fresh()->amount)->toEqual(-1000);
});

test('update returns 403 for another user transaction', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $account = Account::factory()->for($owner)->create();

    $transaction = Transaction::factory()->expense()->for($owner)->create([
        'account_id' => $account->id,
    ]);

    $this->actingAs($other)->put("/transactions/{$transaction->uuid}", [
        'account_id' => $account->id,
        'amount' => -1,
        'currency' => 'CLP',
        'transaction_date' => '2026-01-01',
    ])->assertForbidden();
});

// ---------------------------------------------------------------------------
// Destroy
// ---------------------------------------------------------------------------

test('destroy soft-deletes a transaction', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();

    $transaction = Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
    ]);

    $this->actingAs($user)->delete("/transactions/{$transaction->uuid}")
        ->assertRedirect('/transactions');

    $this->assertSoftDeleted('transactions', ['id' => $transaction->id]);
});

test('destroy a transfer leg soft-deletes the linked leg', function () {
    $user = User::factory()->create();
    $origin = Account::factory()->for($user)->create();
    $destination = Account::factory()->for($user)->create();

    $this->actingAs($user)->post('/transfers', [
        'origin_account_id' => $origin->id,
        'destination_account_id' => $destination->id,
        'amount' => 10000,
        'transaction_date' => '2026-02-15',
    ]);

    $out = Transaction::query()
        ->where('account_id', $origin->id)
        ->whereNotNull('linked_transaction_id')
        ->firstOrFail();
    $in = Transaction::query()
        ->where('account_id', $destination->id)
        ->whereNotNull('linked_transaction_id')
        ->firstOrFail();

    $this->actingAs($user)->delete("/transactions/{$out->uuid}")
        ->assertRedirect('/transactions');

    $this->assertSoftDeleted('transactions', ['id' => $out->id]);
    $this->assertSoftDeleted('transactions', ['id' => $in->id]);
});

test('destroy returns 403 for another user transaction', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $account = Account::factory()->for($owner)->create();

    $transaction = Transaction::factory()->expense()->for($owner)->create([
        'account_id' => $account->id,
    ]);

    $this->actingAs($other)->delete("/transactions/{$transaction->uuid}")
        ->assertForbidden();
});
