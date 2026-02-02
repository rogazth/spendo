<?php

use App\Mcp\Servers\SpendoServer;
use App\Mcp\Tools\CreateTransactionTool;
use App\Mcp\Tools\GetAccountsTool;
use App\Mcp\Tools\GetCategoriesTool;
use App\Mcp\Tools\GetFinancialSummaryTool;
use App\Mcp\Tools\GetPaymentMethodsTool;
use App\Mcp\Tools\GetTransactionsTool;
use App\Models\Account;
use App\Models\Category;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('GetFinancialSummaryTool', function () {
    it('returns financial summary for authenticated user', function () {
        $user = User::factory()->create();
        Account::factory()->checking()->for($user)->create();
        Account::factory()->savings()->for($user)->create();
        PaymentMethod::factory()->creditCard()->for($user)->create();

        $response = SpendoServer::actingAs($user)->tool(GetFinancialSummaryTool::class);

        $response->assertOk()
            ->assertSee('total_account_balance')
            ->assertSee('total_credit_debt')
            ->assertSee('net_balance');
    });

    it('returns error when user is not authenticated', function () {
        $response = SpendoServer::tool(GetFinancialSummaryTool::class);

        $response->assertHasErrors(['User not authenticated.']);
    });
});

describe('GetAccountsTool', function () {
    it('returns all active accounts for user', function () {
        $user = User::factory()->create();
        Account::factory()->checking()->for($user)->create(['name' => 'Test Checking']);
        Account::factory()->savings()->for($user)->create(['name' => 'Test Savings']);
        Account::factory()->inactive()->for($user)->create(['name' => 'Inactive Account']);

        $response = SpendoServer::actingAs($user)->tool(GetAccountsTool::class);

        $response->assertOk()
            ->assertSee('Test Checking')
            ->assertSee('Test Savings')
            ->assertDontSee('Inactive Account');
    });

    it('filters accounts by type', function () {
        $user = User::factory()->create();
        Account::factory()->checking()->for($user)->create(['name' => 'My Checking']);
        Account::factory()->savings()->for($user)->create(['name' => 'My Savings']);

        $response = SpendoServer::actingAs($user)->tool(GetAccountsTool::class, [
            'type' => 'checking',
        ]);

        $response->assertOk()
            ->assertSee('My Checking')
            ->assertDontSee('My Savings');
    });

    it('includes inactive accounts when requested', function () {
        $user = User::factory()->create();
        Account::factory()->inactive()->for($user)->create(['name' => 'Inactive Account']);

        $response = SpendoServer::actingAs($user)->tool(GetAccountsTool::class, [
            'include_inactive' => true,
        ]);

        $response->assertOk()->assertSee('Inactive Account');
    });

    it('returns error when user is not authenticated', function () {
        $response = SpendoServer::tool(GetAccountsTool::class);

        $response->assertHasErrors(['User not authenticated.']);
    });
});

describe('GetPaymentMethodsTool', function () {
    it('returns all active payment methods for user', function () {
        $user = User::factory()->create();
        PaymentMethod::factory()->creditCard()->for($user)->create(['name' => 'My Credit Card']);
        PaymentMethod::factory()->debitCard()->for($user)->create(['name' => 'My Debit Card']);

        $response = SpendoServer::actingAs($user)->tool(GetPaymentMethodsTool::class);

        $response->assertOk()
            ->assertSee('My Credit Card')
            ->assertSee('My Debit Card');
    });

    it('filters payment methods by type', function () {
        $user = User::factory()->create();
        PaymentMethod::factory()->creditCard()->for($user)->create(['name' => 'Credit']);
        PaymentMethod::factory()->debitCard()->for($user)->create(['name' => 'Debit']);

        $response = SpendoServer::actingAs($user)->tool(GetPaymentMethodsTool::class, [
            'type' => 'credit_card',
        ]);

        $response->assertOk()
            ->assertSee('Credit')
            ->assertDontSee('"name": "Debit"');
    });

    it('returns error when user is not authenticated', function () {
        $response = SpendoServer::tool(GetPaymentMethodsTool::class);

        $response->assertHasErrors(['User not authenticated.']);
    });
});

describe('GetCategoriesTool', function () {
    it('returns user categories', function () {
        $user = User::factory()->create();
        Category::factory()->expense()->for($user)->create(['name' => 'Food']);
        Category::factory()->income()->for($user)->create(['name' => 'Salary']);

        $response = SpendoServer::actingAs($user)->tool(GetCategoriesTool::class);

        $response->assertOk()
            ->assertSee('Food')
            ->assertSee('Salary');
    });

    it('filters categories by type', function () {
        $user = User::factory()->create();
        Category::factory()->expense()->for($user)->create(['name' => 'Food']);
        Category::factory()->income()->for($user)->create(['name' => 'Salary']);

        $response = SpendoServer::actingAs($user)->tool(GetCategoriesTool::class, [
            'type' => 'expense',
        ]);

        $response->assertOk()
            ->assertSee('Food')
            ->assertDontSee('Salary');
    });

    it('returns error when user is not authenticated', function () {
        $response = SpendoServer::tool(GetCategoriesTool::class);

        $response->assertHasErrors(['User not authenticated.']);
    });
});

describe('GetTransactionsTool', function () {
    it('returns user transactions', function () {
        $user = User::factory()->create();
        $account = Account::factory()->checking()->for($user)->create();
        $category = Category::factory()->expense()->for($user)->create();
        Transaction::factory()->expense()->for($user)->create([
            'account_id' => $account->id,
            'category_id' => $category->id,
            'description' => 'Test Transaction',
        ]);

        $response = SpendoServer::actingAs($user)->tool(GetTransactionsTool::class);

        $response->assertOk()->assertSee('Test Transaction');
    });

    it('returns error when user is not authenticated', function () {
        $response = SpendoServer::tool(GetTransactionsTool::class);

        $response->assertHasErrors(['User not authenticated.']);
    });
});

describe('CreateTransactionTool', function () {
    it('creates an expense transaction', function () {
        $user = User::factory()->create();
        $account = Account::factory()->checking()->for($user)->create();
        $paymentMethod = PaymentMethod::factory()->debitCard()->for($user)->create([
            'linked_account_id' => $account->id,
        ]);
        $category = Category::factory()->expense()->for($user)->create();

        $response = SpendoServer::actingAs($user)->tool(CreateTransactionTool::class, [
            'type' => 'expense',
            'amount' => 15000,
            'description' => 'Lunch at restaurant',
            'category_id' => $category->id,
            'payment_method_id' => $paymentMethod->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $response->assertOk()->assertSee('Lunch at restaurant');

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'amount' => 1500000, // 15000 * 100 (stored as cents)
            'description' => 'Lunch at restaurant',
        ]);
    });

    it('creates an income transaction', function () {
        $user = User::factory()->create();
        $account = Account::factory()->checking()->for($user)->create();
        $category = Category::factory()->income()->for($user)->create();

        $response = SpendoServer::actingAs($user)->tool(CreateTransactionTool::class, [
            'type' => 'income',
            'amount' => 500000,
            'description' => 'Monthly salary',
            'category_id' => $category->id,
            'account_id' => $account->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $response->assertOk()->assertSee('Monthly salary');

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'amount' => 50000000, // 500000 * 100 (stored as cents)
            'description' => 'Monthly salary',
        ]);
    });

    it('returns error when user is not authenticated', function () {
        $response = SpendoServer::tool(CreateTransactionTool::class, [
            'type' => 'expense',
            'amount' => 15000,
            'description' => 'Test',
            'category_id' => 1,
            'payment_method_id' => 1,
            'transaction_date' => now()->toDateString(),
        ]);

        $response->assertHasErrors(['User not authenticated.']);
    });
});
