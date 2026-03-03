<?php

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Instrument;
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
        $account = Account::factory()->for($user)->create();
        $instrument = Instrument::factory()->creditCard()->for($user)->create();

        // Set 500 (major units) → stored as 50000 cents → read back as 500.0
        $transaction = Transaction::factory()->expense()->for($user)->create([
            'account_id' => $account->id,
            'instrument_id' => $instrument->id,
            'amount' => 500,
        ]);

        expect($transaction->fresh()->amount)->toEqual(500);
    });

    it('multiplies by 100 on set', function () {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $instrument = Instrument::factory()->creditCard()->for($user)->create();

        $transaction = Transaction::factory()->expense()->for($user)->create([
            'account_id' => $account->id,
            'instrument_id' => $instrument->id,
            'amount' => 50, // $50 stored as 5000 cents
        ]);

        expect($transaction->getRawOriginal('amount'))->toBe(5000);
    });

    it('formatted_amount prefixes expenses with minus', function () {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $instrument = Instrument::factory()->creditCard()->for($user)->create();

        $transaction = Transaction::factory()->expense()->for($user)->create([
            'account_id' => $account->id,
            'instrument_id' => $instrument->id,
            'amount' => 200,
        ]);

        expect($transaction->formatted_amount)->toContain('-');
    });

    it('formatted_amount prefixes income with plus', function () {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

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
        $account = Account::factory()->for($user)->create();

        Transaction::factory()->income()->for($user)->create([
            'account_id' => $account->id,
            'instrument_id' => null,
            'amount' => 1000,
        ]);
        Transaction::factory()->expense()->for($user)->create([
            'account_id' => $account->id,
            'instrument_id' => null,
            'amount' => 300,
        ]);

        expect($account->current_balance)->toEqual(700);
    });

    it('returns zero when there are no transactions', function () {
        $account = Account::factory()->for(User::factory()->create())->create();

        expect($account->current_balance)->toEqual(0);
    });

    it('transfer_in increases balance and transfer_out decreases balance', function () {
        $user = User::factory()->create();
        $accountA = Account::factory()->for($user)->create();
        $accountB = Account::factory()->for($user)->create();

        Transaction::factory()->income()->for($user)->create([
            'account_id' => $accountA->id,
            'instrument_id' => null,
            'amount' => 1000,
        ]);
        Transaction::factory()->transferOut()->for($user)->create([
            'account_id' => $accountA->id,
            'amount' => 200,
        ]);
        Transaction::factory()->transferIn()->for($user)->create([
            'account_id' => $accountB->id,
            'amount' => 200,
        ]);

        expect($accountA->current_balance)->toEqual(800);
        expect($accountB->current_balance)->toEqual(200);
    });

    it('settlement does not affect account balance', function () {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $cc = Instrument::factory()->creditCard()->for($user)->create();

        Transaction::factory()->income()->for($user)->create([
            'account_id' => $account->id,
            'instrument_id' => null,
            'amount' => 1000,
        ]);
        Transaction::factory()->settlement()->for($user)->create([
            'account_id' => null,
            'instrument_id' => $cc->id,
            'amount' => 300,
        ]);

        expect($account->current_balance)->toEqual(1000);
    });

    it('soft-deleted transactions are excluded from balance', function () {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $income = Transaction::factory()->income()->for($user)->create([
            'account_id' => $account->id,
            'instrument_id' => null,
            'amount' => 1000,
        ]);
        Transaction::factory()->expense()->for($user)->create([
            'account_id' => $account->id,
            'instrument_id' => null,
            'amount' => 300,
        ]);

        $income->delete();

        expect($account->fresh()->current_balance)->toEqual(-300);
    });
});

// ---------------------------------------------------------------------------
// Instrument model
// ---------------------------------------------------------------------------

describe('Instrument', function () {
    it('credit_limit accessor round-trips through the accessor', function () {
        // Set 1000 (major units) → stored as 100000 cents → read back as 1000.0
        $instrument = Instrument::factory()->creditCard()->for(User::factory()->create())->create([
            'credit_limit' => 1000,
        ]);

        expect($instrument->fresh()->credit_limit)->toEqual(1000);
    });

    it('current_debt sums expense minus settlement', function () {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $instrument = Instrument::factory()->creditCard()->for($user)->create();

        Transaction::factory()->expense()->for($user)->create([
            'account_id' => $account->id,
            'instrument_id' => $instrument->id,
            'amount' => 500,
        ]);
        Transaction::factory()->settlement()->for($user)->create([
            'account_id' => null,
            'instrument_id' => $instrument->id,
            'amount' => 200,
        ]);

        expect($instrument->current_debt)->toEqual(300);
    });

    it('debt is isolated between two credit cards', function () {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $cardA = Instrument::factory()->creditCard()->for($user)->create();
        $cardB = Instrument::factory()->creditCard()->for($user)->create();

        Transaction::factory()->expense()->for($user)->create([
            'account_id' => $account->id,
            'instrument_id' => $cardA->id,
            'amount' => 300,
        ]);
        Transaction::factory()->expense()->for($user)->create([
            'account_id' => $account->id,
            'instrument_id' => $cardA->id,
            'amount' => 200,
        ]);
        Transaction::factory()->expense()->for($user)->create([
            'account_id' => $account->id,
            'instrument_id' => $cardB->id,
            'amount' => 500,
        ]);

        expect($cardA->current_debt)->toEqual(500);
        expect($cardB->current_debt)->toEqual(500);
    });

    it('multiple expenses and settlements sum correctly', function () {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $cc = Instrument::factory()->creditCard()->for($user)->create();

        Transaction::factory()->expense()->for($user)->create(['account_id' => $account->id, 'instrument_id' => $cc->id, 'amount' => 500]);
        Transaction::factory()->expense()->for($user)->create(['account_id' => $account->id, 'instrument_id' => $cc->id, 'amount' => 300]);
        Transaction::factory()->settlement()->for($user)->create(['account_id' => null, 'instrument_id' => $cc->id, 'amount' => 200]);
        Transaction::factory()->settlement()->for($user)->create(['account_id' => null, 'instrument_id' => $cc->id, 'amount' => 100]);

        expect($cc->current_debt)->toEqual(500);
    });

    it('available_credit subtracts debt from limit', function () {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        // credit_limit: 1000 (major) → stored 100000 cents → reads 1000.0
        $instrument = Instrument::factory()->creditCard()->for($user)->create([
            'credit_limit' => 1000,
        ]);

        // expense: 200 (major) → stored 20000 cents → debt reads 200.0
        Transaction::factory()->expense()->for($user)->create([
            'account_id' => $account->id,
            'instrument_id' => $instrument->id,
            'amount' => 200,
        ]);

        expect($instrument->available_credit)->toEqual(800);
    });

    it('available_credit is null for non-credit-card instruments', function () {
        $instrument = Instrument::factory()->checking()->for(User::factory()->create())->create();

        expect($instrument->available_credit)->toBeNull();
    });

    it('available_credit is null when credit_limit is null', function () {
        $instrument = Instrument::factory()->creditCard()->for(User::factory()->create())->create([
            'credit_limit' => null,
        ]);

        expect($instrument->available_credit)->toBeNull();
    });

    it('current_balance for credit card equals negative of current_debt', function () {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $cc = Instrument::factory()->creditCard()->for($user)->create();

        Transaction::factory()->expense()->for($user)->create([
            'account_id' => $account->id,
            'instrument_id' => $cc->id,
            'amount' => 500,
        ]);

        expect($cc->current_balance)->toEqual(-500);
    });

    it('current_balance for bank instrument subtracts outgoing settlements', function () {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $bank = Instrument::factory()->checking()->for($user)->create();
        $cc = Instrument::factory()->creditCard()->for($user)->create();

        Transaction::factory()->income()->for($user)->create([
            'account_id' => $account->id,
            'instrument_id' => $bank->id,
            'amount' => 1000,
        ]);
        Transaction::factory()->settlement()->for($user)->create([
            'account_id' => null,
            'instrument_id' => $cc->id,
            'from_instrument_id' => $bank->id,
            'amount' => 300,
        ]);

        expect($bank->current_balance)->toEqual(700);
    });

    it('current_debt is zero for non-credit-card instruments', function () {
        $instrument = Instrument::factory()->checking()->for(User::factory()->create())->create();

        expect($instrument->current_debt)->toEqual(0);
    });
});

// ---------------------------------------------------------------------------
// Transaction instrument_amount & exchange_rate
// ---------------------------------------------------------------------------

describe('Transaction instrument_amount and exchange_rate', function () {
    it('instrument_amount round-trips through the accessor', function () {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $instrument = Instrument::factory()->creditCard()->for($user)->create();

        $transaction = Transaction::factory()->expense()->for($user)->create([
            'account_id' => $account->id,
            'instrument_id' => $instrument->id,
            'amount' => 500,
            'instrument_amount' => 500,
        ]);

        // Stored as cents internally
        expect($transaction->getRawOriginal('instrument_amount'))->toBe(50000);
        // Read back as major units
        expect($transaction->fresh()->instrument_amount)->toEqual(500);
    });

    it('exchange_rate persists without conversion', function () {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $instrument = Instrument::factory()->creditCard()->for($user)->create();

        $transaction = Transaction::factory()->expense()->for($user)->create([
            'account_id' => $account->id,
            'instrument_id' => $instrument->id,
            'amount' => 500,
            'exchange_rate' => 0.00125,
        ]);

        expect((float) $transaction->fresh()->exchange_rate)->toEqual(0.00125);
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
