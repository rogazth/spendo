<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Currency;
use App\Models\PaymentMethod;
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
            ->has('paymentMethods')
            ->has('categories')
            ->has('filters')
        );
});

test('index filters by date range', function () {
    $user = User::factory()->create();
    $account = Account::factory()->checking()->for($user)->create();

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
    $accountA = Account::factory()->checking()->for($user)->create();
    $accountB = Account::factory()->checking()->for($user)->create();

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
    $account = Account::factory()->checking()->for($user)->create();
    $pm = PaymentMethod::factory()->creditCard()->for($user)->create();
    $category = Category::factory()->expense()->for($user)->create();

    $this->actingAs($user)->post('/transactions', [
        'type' => 'expense',
        'account_id' => $account->id,
        'payment_method_id' => $pm->id,
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
    $account = Account::factory()->checking()->for($user)->create();

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
    $origin = Account::factory()->checking()->for($user)->create();
    $destination = Account::factory()->savings()->for($user)->create();

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
});

test('store validates required fields for expense', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/transactions', [
        'type' => 'expense',
        'amount' => 100,
        'currency' => 'CLP',
        'transaction_date' => '2026-02-15',
    ])->assertSessionHasErrors(['account_id', 'payment_method_id']);
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

test('store rejects invalid currency', function () {
    $user = User::factory()->create();
    $account = Account::factory()->checking()->for($user)->create();

    $this->actingAs($user)->post('/transactions', [
        'type' => 'income',
        'account_id' => $account->id,
        'amount' => 100,
        'currency' => 'XXX',
        'transaction_date' => '2026-02-15',
    ])->assertSessionHasErrors('currency');
});

test('store rejects transfer with same origin and destination account', function () {
    $user = User::factory()->create();
    $account = Account::factory()->checking()->for($user)->create();

    $this->actingAs($user)->post('/transactions', [
        'type' => 'transfer',
        'origin_account_id' => $account->id,
        'destination_account_id' => $account->id,
        'amount' => 1000,
        'currency' => 'CLP',
        'transaction_date' => '2026-02-15',
    ])->assertSessionHasErrors(['origin_account_id', 'destination_account_id']);
});

test('store returns 403 when account belongs to another user', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $account = Account::factory()->checking()->for($owner)->create();
    $pm = PaymentMethod::factory()->creditCard()->for($other)->create();

    $this->actingAs($other)->post('/transactions', [
        'type' => 'expense',
        'account_id' => $account->id,
        'payment_method_id' => $pm->id,
        'amount' => 100,
        'currency' => 'CLP',
        'transaction_date' => '2026-02-15',
    ])->assertForbidden();
});

test('store returns 403 when payment method belongs to another user', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $account = Account::factory()->checking()->for($other)->create();
    $pm = PaymentMethod::factory()->creditCard()->for($owner)->create();

    $this->actingAs($other)->post('/transactions', [
        'type' => 'expense',
        'account_id' => $account->id,
        'payment_method_id' => $pm->id,
        'amount' => 100,
        'currency' => 'CLP',
        'transaction_date' => '2026-02-15',
    ])->assertForbidden();
});

// ---------------------------------------------------------------------------
// Update
// ---------------------------------------------------------------------------

test('update modifies an expense transaction', function () {
    $user = User::factory()->create();
    $account = Account::factory()->checking()->for($user)->create();
    $pm = PaymentMethod::factory()->creditCard()->for($user)->create();

    $transaction = Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'payment_method_id' => $pm->id,
        'amount' => 1000,
        'currency' => 'CLP',
        'transaction_date' => '2026-01-10',
    ]);

    $this->actingAs($user)->put("/transactions/{$transaction->uuid}", [
        'type' => 'expense',
        'account_id' => $account->id,
        'payment_method_id' => $pm->id,
        'amount' => 2000,
        'currency' => 'CLP',
        'transaction_date' => '2026-01-15',
    ])->assertRedirect('/transactions');

    expect($transaction->fresh()->amount)->toEqual(2000);
});

test('update returns 403 for another user transaction', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $account = Account::factory()->checking()->for($owner)->create();
    $pm = PaymentMethod::factory()->creditCard()->for($owner)->create();

    $transaction = Transaction::factory()->expense()->for($owner)->create([
        'account_id' => $account->id,
        'payment_method_id' => $pm->id,
    ]);

    $this->actingAs($other)->put("/transactions/{$transaction->uuid}", [
        'type' => 'expense',
        'account_id' => $account->id,
        'payment_method_id' => $pm->id,
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
    $account = Account::factory()->checking()->for($user)->create();
    $pm = PaymentMethod::factory()->creditCard()->for($user)->create();

    $transaction = Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'payment_method_id' => $pm->id,
    ]);

    $this->actingAs($user)->delete("/transactions/{$transaction->uuid}")
        ->assertRedirect('/transactions');

    $this->assertSoftDeleted('transactions', ['id' => $transaction->id]);
});

test('destroy returns 403 for another user transaction', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $account = Account::factory()->checking()->for($owner)->create();
    $pm = PaymentMethod::factory()->creditCard()->for($owner)->create();

    $transaction = Transaction::factory()->expense()->for($owner)->create([
        'account_id' => $account->id,
        'payment_method_id' => $pm->id,
    ]);

    $this->actingAs($other)->delete("/transactions/{$transaction->uuid}")
        ->assertForbidden();
});
