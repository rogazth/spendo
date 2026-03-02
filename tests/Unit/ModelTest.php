<?php

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\User;

// ---------------------------------------------------------------------------
// HasUuid trait
// ---------------------------------------------------------------------------

describe('HasUuid', function () {
    it('auto-generates a uuid on model creation', function () {
        $user = User::factory()->create();

        expect($user->uuid)->not->toBeNull();
        expect($user->uuid)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i');
    });

    it('uses uuid as the route key', function () {
        $user = User::factory()->create();

        expect($user->getRouteKeyName())->toBe('uuid');
    });

    it('does not overwrite an explicitly set uuid', function () {
        $customUuid = '11111111-2222-4333-8444-555555555555';
        $user = User::factory()->create(['uuid' => $customUuid]);

        expect($user->uuid)->toBe($customUuid);
    });
});

// ---------------------------------------------------------------------------
// Transaction model
// ---------------------------------------------------------------------------

describe('Transaction amount accessor', function () {
    it('round-trips amount through the accessor', function () {
        $user = User::factory()->create();
        $account = Account::factory()->checking()->for($user)->create();
        $pm = PaymentMethod::factory()->creditCard()->for($user)->create();

        // Set 500 (major units) → stored as 50000 cents → read back as 500.0
        $transaction = Transaction::factory()->expense()->for($user)->create([
            'account_id' => $account->id,
            'payment_method_id' => $pm->id,
            'amount' => 500,
        ]);

        expect($transaction->fresh()->amount)->toEqual(500);
    });

    it('multiplies by 100 on set', function () {
        $user = User::factory()->create();
        $account = Account::factory()->checking()->for($user)->create();
        $pm = PaymentMethod::factory()->creditCard()->for($user)->create();

        $transaction = Transaction::factory()->expense()->for($user)->create([
            'account_id' => $account->id,
            'payment_method_id' => $pm->id,
            'amount' => 50, // $50 stored as 5000 cents
        ]);

        expect($transaction->getRawOriginal('amount'))->toBe(5000);
    });

    it('formatted_amount prefixes expenses with minus', function () {
        $user = User::factory()->create();
        $account = Account::factory()->checking()->for($user)->create();
        $pm = PaymentMethod::factory()->creditCard()->for($user)->create();

        $transaction = Transaction::factory()->expense()->for($user)->create([
            'account_id' => $account->id,
            'payment_method_id' => $pm->id,
            'amount' => 200,
        ]);

        expect($transaction->formatted_amount)->toContain('-');
    });

    it('formatted_amount prefixes income with plus', function () {
        $user = User::factory()->create();
        $account = Account::factory()->checking()->for($user)->create();

        $transaction = Transaction::factory()->income()->for($user)->create([
            'account_id' => $account->id,
            'amount' => 200,
        ]);

        expect($transaction->formatted_amount)->toContain('+');
    });
});

// ---------------------------------------------------------------------------
// Account model
// ---------------------------------------------------------------------------

describe('Account current_balance', function () {
    it('calculates balance from income and expense transactions', function () {
        $user = User::factory()->create();
        $account = Account::factory()->checking()->for($user)->create();

        Transaction::factory()->income()->for($user)->create([
            'account_id' => $account->id,
            'payment_method_id' => null,
            'amount' => 1000,
        ]);
        Transaction::factory()->expense()->for($user)->create([
            'account_id' => $account->id,
            'payment_method_id' => null,
            'amount' => 300,
        ]);

        expect($account->current_balance)->toEqual(700);
    });

    it('returns zero when there are no transactions', function () {
        $account = Account::factory()->checking()->for(User::factory()->create())->create();

        expect($account->current_balance)->toEqual(0);
    });
});

// ---------------------------------------------------------------------------
// PaymentMethod model
// ---------------------------------------------------------------------------

describe('PaymentMethod', function () {
    it('credit_limit accessor round-trips through the accessor', function () {
        // Set 1000 (major units) → stored as 100000 cents → read back as 1000.0
        $pm = PaymentMethod::factory()->creditCard()->for(User::factory()->create())->create([
            'credit_limit' => 1000,
        ]);

        expect($pm->fresh()->credit_limit)->toEqual(1000);
    });

    it('current_debt sums expense minus settlement', function () {
        $user = User::factory()->create();
        $account = Account::factory()->checking()->for($user)->create();
        $pm = PaymentMethod::factory()->creditCard()->for($user)->create();

        Transaction::factory()->expense()->for($user)->create([
            'account_id' => $account->id,
            'payment_method_id' => $pm->id,
            'amount' => 500,
        ]);
        Transaction::factory()->settlement()->for($user)->create([
            'account_id' => $account->id,
            'payment_method_id' => $pm->id,
            'amount' => 200,
        ]);

        expect($pm->current_debt)->toEqual(300);
    });

    it('available_credit subtracts debt from limit', function () {
        $user = User::factory()->create();
        $account = Account::factory()->checking()->for($user)->create();
        // credit_limit: 1000 (major) → stored 100000 cents → reads 1000.0
        $pm = PaymentMethod::factory()->creditCard()->for($user)->create([
            'credit_limit' => 1000,
        ]);

        // expense: 200 (major) → stored 20000 cents → debt reads 200.0
        Transaction::factory()->expense()->for($user)->create([
            'account_id' => $account->id,
            'payment_method_id' => $pm->id,
            'amount' => 200,
        ]);

        expect($pm->available_credit)->toEqual(800);
    });

    it('current_debt is zero for non-credit-card payment methods', function () {
        $pm = PaymentMethod::factory()->debitCard()->for(User::factory()->create())->create();

        expect($pm->current_debt)->toEqual(0);
    });
});

// ---------------------------------------------------------------------------
// Budget model
// ---------------------------------------------------------------------------

describe('Budget total_budgeted', function () {
    it('sums budget items and divides by 100', function () {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();

        // 500 major → 50000 cents stored, 300 major → 30000 cents stored
        // total_budgeted = (50000 + 30000) / 100 = 800.0
        $budget->items()->create([
            'category_id' => Category::factory()->expense()->for($user)->create()->id,
            'amount' => 500,
        ]);
        $budget->items()->create([
            'category_id' => Category::factory()->expense()->for($user)->create()->id,
            'amount' => 300,
        ]);

        expect($budget->total_budgeted)->toEqual(800);
    });

    it('returns zero when there are no items', function () {
        $budget = Budget::factory()->for(User::factory()->create())->create();

        expect($budget->total_budgeted)->toEqual(0);
    });
});
