<?php

use App\Mcp\Servers\SpendoServer;
use App\Mcp\Tools\DeleteAccountTool;
use App\Mcp\Tools\DeleteInstrumentTool;
use App\Mcp\Tools\DeleteTransactionTool;
use App\Models\Account;
use App\Models\Instrument;
use App\Models\Transaction;
use App\Models\User;

// ─── DeleteAccountTool ───────────────────────────────────────────────────────

describe('DeleteAccountTool', function () {
    it('deletes an account and all its transactions', function () {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['name' => 'Checking']);

        Transaction::factory()->expense()->for($user)->count(3)->create([
            'account_id' => $account->id,
            'instrument_id' => null,
        ]);

        $response = SpendoServer::actingAs($user)->tool(DeleteAccountTool::class, [
            'account_id' => $account->id,
        ]);

        $response->assertOk()
            ->assertSee('Checking')
            ->assertSee('3');

        $this->assertDatabaseMissing('accounts', ['id' => $account->id]);
        expect(Transaction::query()->where('account_id', $account->id)->count())->toBe(0);
    });

    it('returns error for account belonging to another user', function () {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $account = Account::factory()->for($other)->create();

        SpendoServer::actingAs($user)->tool(DeleteAccountTool::class, [
            'account_id' => $account->id,
        ])->assertHasErrors(['Account not found.']);

        $this->assertDatabaseHas('accounts', ['id' => $account->id]);
    });

    it('returns error when account does not exist', function () {
        $user = User::factory()->create();

        SpendoServer::actingAs($user)->tool(DeleteAccountTool::class, [
            'account_id' => 999999,
        ])->assertHasErrors(['Account not found.']);
    });
});

// ─── DeleteInstrumentTool ─────────────────────────────────────────────────────

describe('DeleteInstrumentTool', function () {
    it('deletes an instrument and all its transactions', function () {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $instrument = Instrument::factory()->for($user)->create(['name' => 'Visa Gold']);

        Transaction::factory()->expense()->for($user)->count(2)->create([
            'account_id' => $account->id,
            'instrument_id' => $instrument->id,
        ]);

        $response = SpendoServer::actingAs($user)->tool(DeleteInstrumentTool::class, [
            'instrument_id' => $instrument->id,
        ]);

        $response->assertOk()
            ->assertSee('Visa Gold')
            ->assertSee('2');

        $this->assertDatabaseMissing('instruments', ['id' => $instrument->id]);
        expect(Transaction::query()->where('instrument_id', $instrument->id)->count())->toBe(0);
    });

    it('returns error for instrument belonging to another user', function () {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $instrument = Instrument::factory()->for($other)->create();

        SpendoServer::actingAs($user)->tool(DeleteInstrumentTool::class, [
            'instrument_id' => $instrument->id,
        ])->assertHasErrors(['Instrument not found.']);

        $this->assertDatabaseHas('instruments', ['id' => $instrument->id]);
    });

    it('returns error when instrument does not exist', function () {
        $user = User::factory()->create();

        SpendoServer::actingAs($user)->tool(DeleteInstrumentTool::class, [
            'instrument_id' => 999999,
        ])->assertHasErrors(['Instrument not found.']);
    });
});

// ─── DeleteTransactionTool ────────────────────────────────────────────────────

describe('DeleteTransactionTool', function () {
    it('deletes a single expense transaction', function () {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $transaction = Transaction::factory()->expense()->for($user)->create([
            'account_id' => $account->id,
            'instrument_id' => null,
            'description' => 'Lunch',
        ]);

        $response = SpendoServer::actingAs($user)->tool(DeleteTransactionTool::class, [
            'transaction_id' => $transaction->id,
        ]);

        $response->assertOk()->assertSee('Lunch');
        $this->assertDatabaseMissing('transactions', ['id' => $transaction->id, 'deleted_at' => null]);
    });

    it('deletes both legs of a transfer', function () {
        $user = User::factory()->create();
        $accountA = Account::factory()->for($user)->create();
        $accountB = Account::factory()->for($user)->create();

        $out = Transaction::factory()->transferOut()->for($user)->create([
            'account_id' => $accountA->id,
            'description' => 'Transfer',
        ]);
        $in = Transaction::factory()->transferIn()->for($user)->create([
            'account_id' => $accountB->id,
            'description' => 'Transfer',
            'linked_transaction_id' => $out->id,
        ]);
        $out->update(['linked_transaction_id' => $in->id]);

        $response = SpendoServer::actingAs($user)->tool(DeleteTransactionTool::class, [
            'transaction_id' => $out->id,
        ]);

        $response->assertOk()->assertSee('linked transfer leg');
        $this->assertDatabaseMissing('transactions', ['id' => $out->id, 'deleted_at' => null]);
        $this->assertDatabaseMissing('transactions', ['id' => $in->id, 'deleted_at' => null]);
    });

    it('returns error for transaction belonging to another user', function () {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $account = Account::factory()->for($other)->create();

        $transaction = Transaction::factory()->expense()->for($other)->create([
            'account_id' => $account->id,
            'instrument_id' => null,
        ]);

        SpendoServer::actingAs($user)->tool(DeleteTransactionTool::class, [
            'transaction_id' => $transaction->id,
        ])->assertHasErrors(['Transaction not found.']);

        $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'deleted_at' => null]);
    });

    it('returns error when transaction does not exist', function () {
        $user = User::factory()->create();

        SpendoServer::actingAs($user)->tool(DeleteTransactionTool::class, [
            'transaction_id' => 999999,
        ])->assertHasErrors(['Transaction not found.']);
    });
});
