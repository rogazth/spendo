<?php

use App\Mcp\Servers\SpendoServer;
use App\Mcp\Tools\CreateTransactionTool;
use App\Mcp\Tools\GetAccountsTool;
use App\Mcp\Tools\GetCategoriesTool;
use App\Mcp\Tools\GetFinancialSummaryTool;
use App\Mcp\Tools\GetTransactionsTool;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('GetFinancialSummaryTool', function () {
    it('returns financial summary for authenticated user', function () {
        $user = User::factory()->create();
        Account::factory()->for($user)->create();
        Account::factory()->for($user)->create();

        $response = SpendoServer::actingAs($user)->tool(GetFinancialSummaryTool::class);

        $response->assertOk()
            ->assertSee('total_account_balance')
            ->assertSee('net_balance');
    });

    it('returns exact total_account_balance in major units', function () {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->income()->for($user)->create();

        SpendoServer::actingAs($user)->tool(CreateTransactionTool::class, [
            'type' => 'income',
            'amount' => 1000,
            'description' => 'Test income',
            'category_id' => $category->id,
            'account_id' => $account->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $response = SpendoServer::actingAs($user)->tool(GetFinancialSummaryTool::class);

        $response->assertOk()->assertSee('"total_account_balance": 1000');
    });

    it('returns error when user is not authenticated', function () {
        $response = SpendoServer::tool(GetFinancialSummaryTool::class);

        $response->assertHasErrors(['User not authenticated.']);
    });
});

describe('GetAccountsTool', function () {
    it('returns all active accounts for user', function () {
        $user = User::factory()->create();
        Account::factory()->for($user)->create(['name' => 'Test Checking']);
        Account::factory()->for($user)->create(['name' => 'Test Savings']);
        Account::factory()->inactive()->for($user)->create(['name' => 'Inactive Account']);

        $response = SpendoServer::actingAs($user)->tool(GetAccountsTool::class);

        $response->assertOk()
            ->assertSee('Test Checking')
            ->assertSee('Test Savings')
            ->assertDontSee('Inactive Account');
    });

    it('includes inactive accounts when requested', function () {
        $user = User::factory()->create();
        Account::factory()->inactive()->for($user)->create(['name' => 'Inactive Account']);

        $response = SpendoServer::actingAs($user)->tool(GetAccountsTool::class, [
            'include_inactive' => true,
        ]);

        $response->assertOk()->assertSee('Inactive Account');
    });

    it('returns only the authenticated user accounts', function () {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        Account::factory()->for($userA)->create(['name' => 'Account A']);
        Account::factory()->for($userB)->create(['name' => 'Account B']);

        $response = SpendoServer::actingAs($userA)->tool(GetAccountsTool::class);

        $response->assertOk()
            ->assertSee('Account A')
            ->assertDontSee('Account B');
    });

    it('returns error when user is not authenticated', function () {
        $response = SpendoServer::tool(GetAccountsTool::class);

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
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->expense()->for($user)->create();
        Transaction::factory()->expense()->for($user)->create([
            'account_id' => $account->id,
            'category_id' => $category->id,
            'description' => 'Test Transaction',
        ]);

        $response = SpendoServer::actingAs($user)->tool(GetTransactionsTool::class);

        $response->assertOk()->assertSee('Test Transaction');
    });

    it('returns only the authenticated user transactions', function () {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $accountA = Account::factory()->for($userA)->create();
        $accountB = Account::factory()->for($userB)->create();
        $category = Category::factory()->expense()->for($userA)->create();

        Transaction::factory()->expense()->for($userA)->create([
            'account_id' => $accountA->id,
            'category_id' => $category->id,
            'description' => 'User A transaction',
        ]);
        Transaction::factory()->expense()->for($userB)->create([
            'account_id' => $accountB->id,
            'description' => 'User B transaction',
        ]);

        $response = SpendoServer::actingAs($userA)->tool(GetTransactionsTool::class);

        $response->assertOk()
            ->assertSee('User A transaction')
            ->assertDontSee('User B transaction');
    });

    it('returns error when user is not authenticated', function () {
        $response = SpendoServer::tool(GetTransactionsTool::class);

        $response->assertHasErrors(['User not authenticated.']);
    });
});

describe('CreateTransactionTool - Authorization', function () {
    it('rejects cross-user account_id and creates no transaction', function () {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $accountA = Account::factory()->for($userA)->create();
        $category = Category::factory()->expense()->for($userB)->create();

        $response = SpendoServer::actingAs($userB)->tool(CreateTransactionTool::class, [
            'type' => 'expense',
            'amount' => 500,
            'description' => 'Cross-user attempt',
            'category_id' => $category->id,
            'account_id' => $accountA->id, // belongs to User A
            'transaction_date' => now()->toDateString(),
        ]);

        $response->assertHasErrors();
        expect(Transaction::where('user_id', $userB->id)->count())->toBe(0);
    });
});

describe('CreateTransactionTool', function () {
    it('creates an expense transaction', function () {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->expense()->for($user)->create();

        $response = SpendoServer::actingAs($user)->tool(CreateTransactionTool::class, [
            'type' => 'expense',
            'amount' => 15000,
            'description' => 'Lunch at restaurant',
            'category_id' => $category->id,
            'account_id' => $account->id,
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
        $account = Account::factory()->for($user)->create();
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
            'account_id' => 1,
            'transaction_date' => now()->toDateString(),
        ]);

        $response->assertHasErrors(['User not authenticated.']);
    });
});
