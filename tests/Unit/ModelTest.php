<?php

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
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

        $transaction = Transaction::factory()->expense()->for($user)->create([
            'account_id' => $account->id,
            'amount' => 500,
        ]);

        expect($transaction->fresh()->amount)->toEqual(500);
    });

    it('multiplies by 100 on set', function () {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $transaction = Transaction::factory()->expense()->for($user)->create([
            'account_id' => $account->id,
            'amount' => 50,
        ]);

        expect($transaction->getRawOriginal('amount'))->toBe(5000);
    });

    it('formatted_amount prefixes expenses with minus', function () {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $transaction = Transaction::factory()->expense()->for($user)->create([
            'account_id' => $account->id,
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
            'amount' => 1000,
        ]);
        Transaction::factory()->expense()->for($user)->create([
            'account_id' => $account->id,
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

    it('soft-deleted transactions are excluded from balance', function () {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $income = Transaction::factory()->income()->for($user)->create([
            'account_id' => $account->id,
            'amount' => 1000,
        ]);
        Transaction::factory()->expense()->for($user)->create([
            'account_id' => $account->id,
            'amount' => 300,
        ]);

        $income->delete();

        expect($account->fresh()->current_balance)->toEqual(-300);
    });
});

// ---------------------------------------------------------------------------
// Budget model
// ---------------------------------------------------------------------------

describe('Budget total_budgeted', function () {
    it('sums budget items and divides by 100', function () {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();

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
