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

    $this->actingAs($user)->get("/transactions?account_ids[]={$accountA->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('transactions.meta.total', 2));
});

// ---------------------------------------------------------------------------
// Store — expense
// ---------------------------------------------------------------------------

test('store creates an expense transaction', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $category = Category::factory()->expense()->for($user)->create();

    $this->actingAs($user)->post('/transactions', [
        'type' => 'expense',
        'account_id' => $account->id,
        'category_id' => $category->id,
        'amount' => 5000,
        'currency' => 'CLP',
        'transaction_date' => '2026-02-15',
    ])->assertRedirect('/transactions');

    $this->assertDatabaseHas('transactions', [
        'user_id' => $user->id,
        'type' => 'expense',
        'account_id' => $account->id,
    ]);
});

test('store creates an income transaction', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();

    $this->actingAs($user)->post('/transactions', [
        'type' => 'income',
        'account_id' => $account->id,
        'amount' => 100000,
        'currency' => 'CLP',
        'transaction_date' => '2026-02-15',
    ])->assertRedirect('/transactions');

    $this->assertDatabaseHas('transactions', [
        'user_id' => $user->id,
        'type' => 'income',
        'account_id' => $account->id,
    ]);
});

test('store creates a transfer with two linked legs', function () {
    $user = User::factory()->create();
    $origin = Account::factory()->for($user)->create();
    $destination = Account::factory()->for($user)->create();

    $this->actingAs($user)->post('/transactions', [
        'type' => 'transfer',
        'origin_account_id' => $origin->id,
        'destination_account_id' => $destination->id,
        'amount' => 50000,
        'currency' => 'CLP',
        'transaction_date' => '2026-02-15',
    ])->assertRedirect('/transactions');

    $out = Transaction::query()->where('type', 'transfer_out')->where('account_id', $origin->id)->firstOrFail();
    $in = Transaction::query()->where('type', 'transfer_in')->where('account_id', $destination->id)->firstOrFail();

    expect($out->linked_transaction_id)->toBe($in->id);
    expect($in->linked_transaction_id)->toBe($out->id);
    expect($out->amount)->toEqual($in->amount);
});

test('destroy transfer_out soft-deletes the linked transfer_in', function () {
    $user = User::factory()->create();
    $origin = Account::factory()->for($user)->create();
    $destination = Account::factory()->for($user)->create();

    $this->actingAs($user)->post('/transactions', [
        'type' => 'transfer',
        'origin_account_id' => $origin->id,
        'destination_account_id' => $destination->id,
        'amount' => 10000,
        'currency' => 'CLP',
        'transaction_date' => '2026-02-15',
    ]);

    $out = Transaction::query()->where('type', 'transfer_out')->firstOrFail();
    $in = Transaction::query()->where('type', 'transfer_in')->firstOrFail();

    $this->actingAs($user)->delete("/transactions/{$out->uuid}")
        ->assertRedirect('/transactions');

    $this->assertSoftDeleted('transactions', ['id' => $out->id]);
    $this->assertSoftDeleted('transactions', ['id' => $in->id]);
});

test('store validates required fields for expense', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/transactions', [
        'type' => 'expense',
        'amount' => 100,
        'currency' => 'CLP',
        'transaction_date' => '2026-02-15',
    ])->assertSessionHasErrors(['account_id']);
});

test('store validates required accounts for transfer', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/transactions', [
        'type' => 'transfer',
        'amount' => 1000,
        'currency' => 'CLP',
        'transaction_date' => '2026-02-15',
    ])->assertSessionHasErrors(['origin_account_id', 'destination_account_id']);
});

test('store rejects transfer with same origin and destination account', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();

    $this->actingAs($user)->post('/transactions', [
        'type' => 'transfer',
        'origin_account_id' => $account->id,
        'destination_account_id' => $account->id,
        'amount' => 1000,
        'currency' => 'CLP',
        'transaction_date' => '2026-02-15',
    ])->assertSessionHasErrors(['origin_account_id', 'destination_account_id']);
});

test('store returns 404 when account belongs to another user', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $account = Account::factory()->for($owner)->create();

    $this->actingAs($other)->post('/transactions', [
        'type' => 'expense',
        'account_id' => $account->id,
        'amount' => 100,
        'currency' => 'CLP',
        'transaction_date' => '2026-02-15',
    ])->assertNotFound();
});

test('store returns 404 when transfer accounts belong to another user', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $ownerAccountA = Account::factory()->for($owner)->create();
    $ownerAccountB = Account::factory()->for($owner)->create();

    $this->actingAs($other)->post('/transactions', [
        'type' => 'transfer',
        'origin_account_id' => $ownerAccountA->id,
        'destination_account_id' => $ownerAccountB->id,
        'amount' => 1000,
        'currency' => 'CLP',
        'transaction_date' => '2026-02-15',
    ])->assertNotFound();
});

test('store rejects non-positive amount', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();

    $this->actingAs($user)->post('/transactions', [
        'type' => 'income',
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
        'type' => 'income',
        'account_id' => $account->id,
        'amount' => 100,
        'currency' => 'CLP',
        'transaction_date' => 'not-a-date',
    ])->assertSessionHasErrors('transaction_date');
});

// ---------------------------------------------------------------------------
// Update
// ---------------------------------------------------------------------------

test('update modifies an expense transaction', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();

    $transaction = Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'amount' => 1000,
        'currency' => 'CLP',
        'transaction_date' => '2026-01-10',
    ]);

    $this->actingAs($user)->put("/transactions/{$transaction->uuid}", [
        'type' => 'expense',
        'account_id' => $account->id,
        'amount' => 2000,
        'currency' => 'CLP',
        'transaction_date' => '2026-01-15',
    ])->assertRedirect('/transactions');

    expect($transaction->fresh()->amount)->toEqual(2000);
});

test('update returns 403 for another user transaction', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $account = Account::factory()->for($owner)->create();

    $transaction = Transaction::factory()->expense()->for($owner)->create([
        'account_id' => $account->id,
    ]);

    $this->actingAs($other)->put("/transactions/{$transaction->uuid}", [
        'type' => 'expense',
        'account_id' => $account->id,
        'amount' => 1,
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
