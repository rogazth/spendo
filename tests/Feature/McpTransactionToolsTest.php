<?php

use App\Enums\CategoryType;
use App\Mcp\Servers\SpendoServer;
use App\Mcp\Tools\CreateTransactionTool;
use App\Mcp\Tools\GetTransactionsTool;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\User;

// ─── CreateTransactionTool - Transfer ───────────────────────────────────────

describe('CreateTransactionTool - Transfer', function () {
    it('creates a transfer between accounts', function () {
        $user = User::factory()->create();
        $origin = Account::factory()->checking()->for($user)->create(['name' => 'Origin']);
        $dest = Account::factory()->savings()->for($user)->create(['name' => 'Destination']);

        Category::factory()->create([
            'user_id' => null,
            'name' => 'Transferencia',
            'type' => CategoryType::System,
            'is_system' => true,
        ]);

        $response = SpendoServer::actingAs($user)->tool(CreateTransactionTool::class, [
            'type' => 'transfer',
            'amount' => 100000,
            'description' => 'Savings deposit',
            'origin_account_id' => $origin->id,
            'destination_account_id' => $dest->id,
        ]);

        $response->assertOk()
            ->assertSee('Transfer created successfully')
            ->assertSee('transfer_out')
            ->assertSee('transfer_in');

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'type' => 'transfer_out',
            'account_id' => $origin->id,
            'amount' => 10000000,
        ]);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'type' => 'transfer_in',
            'account_id' => $dest->id,
            'amount' => 10000000,
        ]);
    });

    it('rejects transfer to same account', function () {
        $user = User::factory()->create();
        $account = Account::factory()->checking()->for($user)->create();

        $response = SpendoServer::actingAs($user)->tool(CreateTransactionTool::class, [
            'type' => 'transfer',
            'amount' => 100000,
            'description' => 'Self transfer',
            'origin_account_id' => $account->id,
            'destination_account_id' => $account->id,
        ]);

        $response->assertHasErrors(['must be different']);
    });

    it('rejects transfer without origin account', function () {
        $user = User::factory()->create();
        $dest = Account::factory()->savings()->for($user)->create();

        $response = SpendoServer::actingAs($user)->tool(CreateTransactionTool::class, [
            'type' => 'transfer',
            'amount' => 100000,
            'description' => 'Missing origin',
            'destination_account_id' => $dest->id,
        ]);

        $response->assertHasErrors(['Origin account is required']);
    });
});

// ─── CreateTransactionTool - Settlement ─────────────────────────────────────

describe('CreateTransactionTool - Settlement', function () {
    it('creates a credit card settlement', function () {
        $user = User::factory()->create();
        $account = Account::factory()->checking()->for($user)->create();
        $creditCard = PaymentMethod::factory()->creditCard()->for($user)->create(['name' => 'Visa']);

        Category::factory()->create([
            'user_id' => null,
            'name' => 'Pago Tarjeta',
            'type' => CategoryType::System,
            'is_system' => true,
        ]);

        $response = SpendoServer::actingAs($user)->tool(CreateTransactionTool::class, [
            'type' => 'settlement',
            'amount' => 500000,
            'description' => 'Credit card payment',
            'account_id' => $account->id,
            'payment_method_id' => $creditCard->id,
        ]);

        $response->assertOk()
            ->assertSee('Settlement created successfully')
            ->assertSee('Credit card debt reduced');

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'type' => 'settlement',
            'amount' => 50000000,
        ]);
    });

    it('rejects settlement for non-credit card', function () {
        $user = User::factory()->create();
        $account = Account::factory()->checking()->for($user)->create();
        $debit = PaymentMethod::factory()->debitCard()->for($user)->create([
            'linked_account_id' => $account->id,
        ]);

        $response = SpendoServer::actingAs($user)->tool(CreateTransactionTool::class, [
            'type' => 'settlement',
            'amount' => 500000,
            'description' => 'Bad settlement',
            'account_id' => $account->id,
            'payment_method_id' => $debit->id,
        ]);

        $response->assertHasErrors(['credit card']);
    });
});

// ─── CreateTransactionTool - Idempotency ────────────────────────────────────

describe('CreateTransactionTool - Idempotency', function () {
    it('deduplicates transactions with same idempotency key', function () {
        $user = User::factory()->create();
        $account = Account::factory()->checking()->for($user)->create();
        $category = Category::factory()->income()->for($user)->create();

        $params = [
            'type' => 'income',
            'amount' => 500000,
            'description' => 'Salary',
            'category_id' => $category->id,
            'account_id' => $account->id,
            'idempotency_key' => 'salary-march-2026',
        ];

        // First call
        $response1 = SpendoServer::actingAs($user)->tool(CreateTransactionTool::class, $params);
        $response1->assertOk()->assertSee('Income created successfully');

        // Second call with same key
        $response2 = SpendoServer::actingAs($user)->tool(CreateTransactionTool::class, $params);
        $response2->assertOk()->assertSee('already exists (idempotent)');

        // Only one transaction in DB
        expect(Transaction::where('user_id', $user->id)->count())->toBe(1);
    });
});

// ─── CreateTransactionTool - Money Contract ─────────────────────────────────

describe('CreateTransactionTool - Money Contract', function () {
    it('stores amount correctly in cents from major units', function () {
        $user = User::factory()->create();
        $account = Account::factory()->checking()->for($user)->create();
        $pm = PaymentMethod::factory()->debitCard()->for($user)->create([
            'linked_account_id' => $account->id,
        ]);
        $category = Category::factory()->expense()->for($user)->create();

        $response = SpendoServer::actingAs($user)->tool(CreateTransactionTool::class, [
            'type' => 'expense',
            'amount' => 58900, // 58,900 CLP
            'description' => 'Grocery shopping',
            'category_id' => $category->id,
            'payment_method_id' => $pm->id,
        ]);

        $response->assertOk();

        // Verify stored as cents
        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'amount' => 5890000, // 58900 * 100
        ]);

        // Verify response contains major units
        $transaction = Transaction::where('user_id', $user->id)->first();
        expect($transaction->amount)->toEqual(58900);
    });
});

// ─── GetTransactionsTool - Enhanced ─────────────────────────────────────────

describe('GetTransactionsTool - Enhanced', function () {
    it('returns totals block', function () {
        $user = User::factory()->create();
        $account = Account::factory()->checking()->for($user)->create();
        $category = Category::factory()->expense()->for($user)->create();
        Transaction::factory()->expense()->for($user)->create([
            'account_id' => $account->id,
            'category_id' => $category->id,
        ]);

        $response = SpendoServer::actingAs($user)->tool(GetTransactionsTool::class);

        $response->assertOk()
            ->assertSee('totals')
            ->assertSee('total_debit')
            ->assertSee('total_credit')
            ->assertSee('pagination');
    });

    it('filters by budget', function () {
        $user = User::factory()->create();
        $cat1 = Category::factory()->expense()->for($user)->create(['name' => 'Budget Cat']);
        $cat2 = Category::factory()->expense()->for($user)->create(['name' => 'Other Cat']);
        $account = Account::factory()->checking()->for($user)->create();

        $budget = Budget::factory()->for($user)->create([
            'anchor_date' => now()->startOfMonth(),
        ]);
        $budget->items()->create(['category_id' => $cat1->id, 'amount' => 10000000]);

        Transaction::factory()->expense()->for($user)->create([
            'account_id' => $account->id,
            'category_id' => $cat1->id,
            'description' => 'Budget expense',
            'transaction_date' => now(),
        ]);

        Transaction::factory()->expense()->for($user)->create([
            'account_id' => $account->id,
            'category_id' => $cat2->id,
            'description' => 'Non-budget expense',
            'transaction_date' => now(),
        ]);

        $response = SpendoServer::actingAs($user)->tool(GetTransactionsTool::class, [
            'budget_id' => $budget->id,
        ]);

        $response->assertOk()
            ->assertSee('Budget expense')
            ->assertDontSee('Non-budget expense');
    });

    it('supports pagination', function () {
        $user = User::factory()->create();
        $account = Account::factory()->checking()->for($user)->create();
        $category = Category::factory()->expense()->for($user)->create();

        for ($i = 0; $i < 5; $i++) {
            Transaction::factory()->expense()->for($user)->create([
                'account_id' => $account->id,
                'category_id' => $category->id,
                'description' => "Transaction {$i}",
            ]);
        }

        $response = SpendoServer::actingAs($user)->tool(GetTransactionsTool::class, [
            'page' => 1,
            'per_page' => 2,
        ]);

        $response->assertOk()
            ->assertSee('"per_page": 2')
            ->assertSee('"total_pages": 3');
    });
});
