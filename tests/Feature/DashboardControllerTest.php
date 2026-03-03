<?php

use App\Models\Account;
use App\Models\Instrument;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

afterEach(function () {
    Carbon::setTestNow();
});

// ---------------------------------------------------------------------------
// Guest access
// ---------------------------------------------------------------------------

test('guests are redirected to login', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

// ---------------------------------------------------------------------------
// Dashboard data
// ---------------------------------------------------------------------------

test('dashboard renders for authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('dashboard')
            ->has('accounts')
            ->has('instruments')
            ->has('recentTransactions')
            ->has('summary')
        );
});

test('dashboard shows correct total account balance', function () {
    $user = User::factory()->create();
    $accountA = Account::factory()->for($user)->create();
    $accountB = Account::factory()->for($user)->create();

    // accountA: +1000 - 300 = 700
    Transaction::factory()->income()->for($user)->create(['account_id' => $accountA->id, 'amount' => 1000, 'instrument_id' => null]);
    Transaction::factory()->expense()->for($user)->create(['account_id' => $accountA->id, 'amount' => 300, 'instrument_id' => null]);
    // accountB: +500
    Transaction::factory()->income()->for($user)->create(['account_id' => $accountB->id, 'amount' => 500, 'instrument_id' => null]);

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.totalAccountBalance', 1200)
        );
});

test('dashboard shows correct credit debt', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $creditCard = Instrument::factory()->creditCard()->for($user)->create();

    // expense 800 (account debited at purchase), settlement 200 (instrument-only, account_id=null) → debt = 600
    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'instrument_id' => $creditCard->id,
        'amount' => 800,
    ]);
    Transaction::factory()->settlement()->for($user)->create([
        'account_id' => null,
        'instrument_id' => $creditCard->id,
        'amount' => 200,
    ]);

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.totalCreditDebt', 600)
        );
});

test('dashboard shows monthly transaction count', function () {
    Carbon::setTestNow('2026-02-15 12:00:00');

    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();

    Transaction::factory()->income()->for($user)->create([
        'account_id' => $account->id,
        'transaction_date' => '2026-02-10',
    ]);
    Transaction::factory()->income()->for($user)->create([
        'account_id' => $account->id,
        'transaction_date' => '2026-02-12',
    ]);
    // Previous month — should not be counted
    Transaction::factory()->income()->for($user)->create([
        'account_id' => $account->id,
        'transaction_date' => '2026-01-20',
    ]);

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.monthlyTransactionCount', 2)
        );
});

test('dashboard shows up to 10 recent transactions', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();

    Transaction::factory()->income()->for($user)->count(12)->create([
        'account_id' => $account->id,
    ]);

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->count('recentTransactions', 10)
        );
});

test('dashboard only shows active accounts', function () {
    $user = User::factory()->create();
    Account::factory()->for($user)->create(['is_active' => true]);
    Account::factory()->for($user)->create(['is_active' => false]);

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->count('accounts', 1)
        );
});
