<?php

use App\Enums\CategoryType;
use App\Mcp\Servers\SpendoServer;
use App\Mcp\Tools\CreateAccountTool;
use App\Mcp\Tools\CreateBudgetTool;
use App\Mcp\Tools\CreateCategoryTool;
use App\Mcp\Tools\CreatePaymentMethodTool;
use App\Mcp\Tools\GetBudgetMetricsTool;
use App\Mcp\Tools\GetBudgetsTool;
use App\Mcp\Tools\GetCategoriesTool;
use App\Mcp\Tools\GetTransactionsTool;
use App\Mcp\Tools\UpdateAccountTool;
use App\Mcp\Tools\UpdateBudgetTool;
use App\Mcp\Tools\UpdateCategoryTool;
use App\Mcp\Tools\UpdatePaymentMethodTool;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Currency;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\User;

beforeEach(function () {
    Currency::updateOrCreate(['code' => 'CLP'], ['name' => 'Peso chileno', 'locale' => 'es-CL']);
    Currency::updateOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'locale' => 'en-US']);
});

// ─── CreateAccountTool ──────────────────────────────────────────────────────

describe('CreateAccountTool', function () {
    it('creates a checking account', function () {
        $user = User::factory()->create();

        $response = SpendoServer::actingAs($user)->tool(CreateAccountTool::class, [
            'name' => 'Cuenta Corriente BCI',
            'type' => 'checking',
            'currency' => 'CLP',
        ]);

        $response->assertOk()
            ->assertSee('Cuenta Corriente BCI')
            ->assertSee('created successfully');

        $this->assertDatabaseHas('accounts', [
            'user_id' => $user->id,
            'name' => 'Cuenta Corriente BCI',
            'type' => 'checking',
            'currency' => 'CLP',
        ]);
    });

    it('creates an account with initial balance', function () {
        $user = User::factory()->create();
        Category::factory()->create([
            'user_id' => null,
            'name' => 'Balance Inicial',
            'type' => CategoryType::System,
            'is_system' => true,
        ]);

        $response = SpendoServer::actingAs($user)->tool(CreateAccountTool::class, [
            'name' => 'Savings Account',
            'type' => 'savings',
            'currency' => 'CLP',
            'initial_balance' => 500000,
        ]);

        $response->assertOk()->assertSee('Savings Account');

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'amount' => 50000000, // 500000 * 100
            'description' => 'Balance inicial',
        ]);
    });

    it('rejects duplicate account names', function () {
        $user = User::factory()->create();
        Account::factory()->checking()->for($user)->create(['name' => 'My Account']);

        $response = SpendoServer::actingAs($user)->tool(CreateAccountTool::class, [
            'name' => 'My Account',
            'type' => 'checking',
            'currency' => 'CLP',
        ]);

        $response->assertHasErrors(['already exists']);
    });

    it('returns error when not authenticated', function () {
        $response = SpendoServer::tool(CreateAccountTool::class, [
            'name' => 'Test',
            'type' => 'checking',
            'currency' => 'CLP',
        ]);

        $response->assertHasErrors(['User not authenticated.']);
    });
});

// ─── UpdateAccountTool ──────────────────────────────────────────────────────

describe('UpdateAccountTool', function () {
    it('updates an account name', function () {
        $user = User::factory()->create();
        $account = Account::factory()->checking()->for($user)->create(['name' => 'Old Name']);

        $response = SpendoServer::actingAs($user)->tool(UpdateAccountTool::class, [
            'account_id' => $account->id,
            'name' => 'New Name',
        ]);

        $response->assertOk()->assertSee('New Name');

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'name' => 'New Name',
        ]);
    });

    it('prevents updating to an existing name', function () {
        $user = User::factory()->create();
        Account::factory()->checking()->for($user)->create(['name' => 'Existing']);
        $account = Account::factory()->checking()->for($user)->create(['name' => 'My Account']);

        $response = SpendoServer::actingAs($user)->tool(UpdateAccountTool::class, [
            'account_id' => $account->id,
            'name' => 'Existing',
        ]);

        $response->assertHasErrors(['already exists']);
    });

    it('does not allow updating another user account', function () {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $account = Account::factory()->checking()->for($other)->create();

        $response = SpendoServer::actingAs($user)->tool(UpdateAccountTool::class, [
            'account_id' => $account->id,
            'name' => 'Hacked',
        ]);

        $response->assertHasErrors(['Account not found.']);
    });
});

// ─── CreatePaymentMethodTool ────────────────────────────────────────────────

describe('CreatePaymentMethodTool', function () {
    it('creates a credit card', function () {
        $user = User::factory()->create();

        $response = SpendoServer::actingAs($user)->tool(CreatePaymentMethodTool::class, [
            'name' => 'Visa BCI',
            'type' => 'credit_card',
            'credit_limit' => 2000000,
            'billing_cycle_day' => 15,
            'payment_due_day' => 5,
        ]);

        $response->assertOk()->assertSee('Visa BCI');

        $this->assertDatabaseHas('payment_methods', [
            'user_id' => $user->id,
            'name' => 'Visa BCI',
            'type' => 'credit_card',
            'credit_limit' => 200000000, // 2000000 * 100
        ]);
    });

    it('creates a debit card linked to account', function () {
        $user = User::factory()->create();
        $account = Account::factory()->checking()->for($user)->create();

        $response = SpendoServer::actingAs($user)->tool(CreatePaymentMethodTool::class, [
            'name' => 'Débito BCI',
            'type' => 'debit_card',
            'linked_account_id' => $account->id,
        ]);

        $response->assertOk()->assertSee('Débito BCI');
    });

    it('rejects duplicate names', function () {
        $user = User::factory()->create();
        PaymentMethod::factory()->creditCard()->for($user)->create(['name' => 'My Card']);

        $response = SpendoServer::actingAs($user)->tool(CreatePaymentMethodTool::class, [
            'name' => 'My Card',
            'type' => 'credit_card',
        ]);

        $response->assertHasErrors(['already exists']);
    });

    it('rejects invalid linked account', function () {
        $user = User::factory()->create();

        $response = SpendoServer::actingAs($user)->tool(CreatePaymentMethodTool::class, [
            'name' => 'Debit',
            'type' => 'debit_card',
            'linked_account_id' => 99999,
        ]);

        $response->assertHasErrors(['Linked account not found']);
    });
});

// ─── UpdatePaymentMethodTool ────────────────────────────────────────────────

describe('UpdatePaymentMethodTool', function () {
    it('updates a payment method', function () {
        $user = User::factory()->create();
        $pm = PaymentMethod::factory()->creditCard()->for($user)->create(['name' => 'Old Card']);

        $response = SpendoServer::actingAs($user)->tool(UpdatePaymentMethodTool::class, [
            'payment_method_id' => $pm->id,
            'name' => 'New Card',
        ]);

        $response->assertOk()->assertSee('New Card');
    });

    it('does not allow updating another user payment method', function () {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $pm = PaymentMethod::factory()->creditCard()->for($other)->create();

        $response = SpendoServer::actingAs($user)->tool(UpdatePaymentMethodTool::class, [
            'payment_method_id' => $pm->id,
            'name' => 'Hacked',
        ]);

        $response->assertHasErrors(['Payment method not found.']);
    });
});

// ─── CreateCategoryTool ─────────────────────────────────────────────────────

describe('CreateCategoryTool', function () {
    it('creates an expense category', function () {
        $user = User::factory()->create();

        $response = SpendoServer::actingAs($user)->tool(CreateCategoryTool::class, [
            'name' => 'Groceries',
            'type' => 'expense',
        ]);

        $response->assertOk()->assertSee('Groceries');

        $this->assertDatabaseHas('categories', [
            'user_id' => $user->id,
            'name' => 'Groceries',
            'type' => 'expense',
        ]);
    });

    it('creates a subcategory', function () {
        $user = User::factory()->create();
        $parent = Category::factory()->expense()->for($user)->create(['name' => 'Food']);

        $response = SpendoServer::actingAs($user)->tool(CreateCategoryTool::class, [
            'name' => 'Restaurants',
            'parent_id' => $parent->id,
        ]);

        $response->assertOk()->assertSee('Restaurants');

        $this->assertDatabaseHas('categories', [
            'name' => 'Restaurants',
            'parent_id' => $parent->id,
            'type' => 'expense', // inherited from parent
        ]);
    });

    it('rejects duplicate category names', function () {
        $user = User::factory()->create();
        Category::factory()->expense()->for($user)->create(['name' => 'Food']);

        $response = SpendoServer::actingAs($user)->tool(CreateCategoryTool::class, [
            'name' => 'Food',
            'type' => 'expense',
        ]);

        $response->assertHasErrors(['already exists']);
    });

    it('requires type when no parent provided', function () {
        $user = User::factory()->create();

        $response = SpendoServer::actingAs($user)->tool(CreateCategoryTool::class, [
            'name' => 'Orphan',
        ]);

        $response->assertHasErrors(['type']);
    });
});

// ─── UpdateCategoryTool ─────────────────────────────────────────────────────

describe('UpdateCategoryTool', function () {
    it('updates a category name', function () {
        $user = User::factory()->create();
        $category = Category::factory()->expense()->for($user)->create(['name' => 'Old']);

        $response = SpendoServer::actingAs($user)->tool(UpdateCategoryTool::class, [
            'category_id' => $category->id,
            'name' => 'New',
        ]);

        $response->assertOk()->assertSee('New');
    });

    it('rejects system category updates', function () {
        $user = User::factory()->create();
        $system = Category::factory()->create([
            'user_id' => null,
            'name' => 'System Cat',
            'type' => CategoryType::System,
            'is_system' => true,
        ]);

        $response = SpendoServer::actingAs($user)->tool(UpdateCategoryTool::class, [
            'category_id' => $system->id,
            'name' => 'Hacked',
        ]);

        $response->assertHasErrors(['not found']);
    });

    it('rejects editing global non-system categories', function () {
        $user = User::factory()->create();
        $global = Category::factory()->create([
            'user_id' => null,
            'name' => 'Shared Category',
            'type' => CategoryType::Expense,
            'is_system' => false,
        ]);

        $response = SpendoServer::actingAs($user)->tool(UpdateCategoryTool::class, [
            'category_id' => $global->id,
            'name' => 'Hijacked',
        ]);

        $response->assertHasErrors(['not found']);
    });
});

// ─── CreateBudgetTool ───────────────────────────────────────────────────────

describe('CreateBudgetTool', function () {
    it('creates a monthly budget with items', function () {
        $user = User::factory()->create();
        $cat1 = Category::factory()->expense()->for($user)->create(['name' => 'Rent']);
        $cat2 = Category::factory()->expense()->for($user)->create(['name' => 'Groceries']);

        $response = SpendoServer::actingAs($user)->tool(CreateBudgetTool::class, [
            'name' => 'House',
            'currency' => 'CLP',
            'frequency' => 'monthly',
            'anchor_date' => '2026-03-01',
            'items' => [
                ['category_id' => $cat1->id, 'amount' => 572000],
                ['category_id' => $cat2->id, 'amount' => 330000],
            ],
        ]);

        $response->assertOk()
            ->assertSee('House')
            ->assertSee('created successfully');

        $this->assertDatabaseHas('budgets', [
            'user_id' => $user->id,
            'name' => 'House',
            'frequency' => 'monthly',
        ]);

        $this->assertDatabaseHas('budget_items', [
            'category_id' => $cat1->id,
            'amount' => 57200000, // 572000 * 100
        ]);

        $this->assertDatabaseHas('budget_items', [
            'category_id' => $cat2->id,
            'amount' => 33000000, // 330000 * 100
        ]);
    });

    it('rejects parent and child categories in same budget', function () {
        $user = User::factory()->create();
        $parent = Category::factory()->expense()->for($user)->create(['name' => 'Food']);
        $child = Category::factory()->expense()->for($user)->create([
            'name' => 'Restaurants',
            'parent_id' => $parent->id,
        ]);

        $response = SpendoServer::actingAs($user)->tool(CreateBudgetTool::class, [
            'name' => 'Test',
            'currency' => 'CLP',
            'frequency' => 'monthly',
            'anchor_date' => '2026-03-01',
            'items' => [
                ['category_id' => $parent->id, 'amount' => 100000],
                ['category_id' => $child->id, 'amount' => 50000],
            ],
        ]);

        $response->assertHasErrors(['Cannot mix a parent category']);
    });

    it('rejects duplicate categories', function () {
        $user = User::factory()->create();
        $cat = Category::factory()->expense()->for($user)->create(['name' => 'Rent']);

        $response = SpendoServer::actingAs($user)->tool(CreateBudgetTool::class, [
            'name' => 'Test',
            'currency' => 'CLP',
            'frequency' => 'monthly',
            'anchor_date' => '2026-03-01',
            'items' => [
                ['category_id' => $cat->id, 'amount' => 100000],
                ['category_id' => $cat->id, 'amount' => 200000],
            ],
        ]);

        $response->assertHasErrors(['Duplicate category']);
    });

    it('validates account currency matches budget currency', function () {
        $user = User::factory()->create();
        $account = Account::factory()->checking()->for($user)->create(['currency' => 'USD']);
        $cat = Category::factory()->expense()->for($user)->create();

        $response = SpendoServer::actingAs($user)->tool(CreateBudgetTool::class, [
            'name' => 'Test',
            'currency' => 'CLP',
            'frequency' => 'monthly',
            'anchor_date' => '2026-03-01',
            'account_id' => $account->id,
            'items' => [
                ['category_id' => $cat->id, 'amount' => 100000],
            ],
        ]);

        $response->assertHasErrors(['currency']);
    });
});

// ─── UpdateBudgetTool ───────────────────────────────────────────────────────

describe('UpdateBudgetTool', function () {
    it('updates budget name', function () {
        $user = User::factory()->create();
        $cat = Category::factory()->expense()->for($user)->create();
        $budget = Budget::factory()->for($user)->create(['name' => 'Old Budget']);
        $budget->items()->create(['category_id' => $cat->id, 'amount' => 10000000]);

        $response = SpendoServer::actingAs($user)->tool(UpdateBudgetTool::class, [
            'budget_id' => $budget->id,
            'name' => 'New Budget',
        ]);

        $response->assertOk()->assertSee('New Budget');
    });

    it('replaces all items when items provided', function () {
        $user = User::factory()->create();
        $cat1 = Category::factory()->expense()->for($user)->create(['name' => 'Cat1']);
        $cat2 = Category::factory()->expense()->for($user)->create(['name' => 'Cat2']);
        $budget = Budget::factory()->for($user)->create();
        $budget->items()->create(['category_id' => $cat1->id, 'amount' => 10000000]);

        $response = SpendoServer::actingAs($user)->tool(UpdateBudgetTool::class, [
            'budget_id' => $budget->id,
            'items' => [
                ['category_id' => $cat2->id, 'amount' => 200000],
            ],
        ]);

        $response->assertOk();

        $this->assertDatabaseMissing('budget_items', [
            'budget_id' => $budget->id,
            'category_id' => $cat1->id,
        ]);

        $this->assertDatabaseHas('budget_items', [
            'budget_id' => $budget->id,
            'category_id' => $cat2->id,
            'amount' => 20000000,
        ]);
    });

    it('does not allow updating another user budget', function () {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $budget = Budget::factory()->for($other)->create();

        $response = SpendoServer::actingAs($user)->tool(UpdateBudgetTool::class, [
            'budget_id' => $budget->id,
            'name' => 'Hacked',
        ]);

        $response->assertHasErrors(['Budget not found.']);
    });
});

// ─── GetBudgetsTool ─────────────────────────────────────────────────────────

describe('GetBudgetsTool', function () {
    it('returns active budgets with cycle progress', function () {
        $user = User::factory()->create();
        $cat = Category::factory()->expense()->for($user)->create(['name' => 'Groceries']);
        $budget = Budget::factory()->for($user)->create([
            'name' => 'House',
            'anchor_date' => now()->startOfMonth(),
        ]);
        $budget->items()->create(['category_id' => $cat->id, 'amount' => 33000000]);

        $response = SpendoServer::actingAs($user)->tool(GetBudgetsTool::class);

        $response->assertOk()
            ->assertSee('House')
            ->assertSee('total_budgeted')
            ->assertSee('current_cycle');
    });

    it('excludes inactive budgets by default', function () {
        $user = User::factory()->create();
        Budget::factory()->for($user)->create(['name' => 'Active Budget', 'is_active' => true]);
        Budget::factory()->inactive()->for($user)->create(['name' => 'Inactive Budget']);

        $response = SpendoServer::actingAs($user)->tool(GetBudgetsTool::class);

        $response->assertOk()
            ->assertSee('Active Budget')
            ->assertDontSee('Inactive Budget');
    });

    it('includes inactive budgets when requested', function () {
        $user = User::factory()->create();
        Budget::factory()->inactive()->for($user)->create(['name' => 'Inactive Budget']);

        $response = SpendoServer::actingAs($user)->tool(GetBudgetsTool::class, [
            'include_inactive' => true,
        ]);

        $response->assertOk()->assertSee('Inactive Budget');
    });

    it('returns error when not authenticated', function () {
        $response = SpendoServer::tool(GetBudgetsTool::class);

        $response->assertHasErrors(['User not authenticated.']);
    });
});

// ─── GetBudgetMetricsTool ───────────────────────────────────────────────────

describe('GetBudgetMetricsTool', function () {
    it('returns current cycle metrics with category breakdown', function () {
        $user = User::factory()->create();
        $cat = Category::factory()->expense()->for($user)->create(['name' => 'Groceries']);
        $account = Account::factory()->checking()->for($user)->create();
        $pm = PaymentMethod::factory()->debitCard()->for($user)->create([
            'linked_account_id' => $account->id,
        ]);

        $budget = Budget::factory()->for($user)->create([
            'name' => 'House',
            'anchor_date' => now()->startOfMonth(),
        ]);
        $budget->items()->create(['category_id' => $cat->id, 'amount' => 33000000]); // 330000 CLP

        // Create an expense within the cycle
        Transaction::factory()->expense()->for($user)->create([
            'account_id' => $account->id,
            'payment_method_id' => $pm->id,
            'category_id' => $cat->id,
            'amount' => 5000000, // 50000 CLP in cents
            'transaction_date' => now(),
        ]);

        $response = SpendoServer::actingAs($user)->tool(GetBudgetMetricsTool::class, [
            'budget_id' => $budget->id,
        ]);

        $response->assertOk()
            ->assertSee('House')
            ->assertSee('budgeted_amount')
            ->assertSee('spent_amount')
            ->assertSee('remaining_amount')
            ->assertSee('Groceries');
    });

    it('returns error for nonexistent budget', function () {
        $user = User::factory()->create();

        $response = SpendoServer::actingAs($user)->tool(GetBudgetMetricsTool::class, [
            'budget_id' => 99999,
        ]);

        $response->assertHasErrors(['Budget not found.']);
    });

    it('requires dates for custom scope', function () {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();

        $response = SpendoServer::actingAs($user)->tool(GetBudgetMetricsTool::class, [
            'budget_id' => $budget->id,
            'scope' => 'custom',
        ]);

        $response->assertHasErrors(['start_date and end_date are required']);
    });
});

// ─── Regression Tests ─────────────────────────────────────────────────────

describe('GetCategoriesTool - Cross-user isolation', function () {
    it('does not leak another user subcategories under shared parents', function () {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $globalParent = Category::factory()->create([
            'user_id' => null,
            'parent_id' => null,
            'type' => CategoryType::Expense,
            'is_system' => false,
            'name' => 'SharedParent',
        ]);

        $childA = Category::factory()->for($userA)->create([
            'parent_id' => $globalParent->id,
            'type' => CategoryType::Expense,
            'name' => 'UserA Private',
        ]);

        $response = SpendoServer::actingAs($userB)->tool(GetCategoriesTool::class, [
            'type' => 'expense',
        ]);

        $response->assertOk()
            ->assertSee('SharedParent')
            ->assertDontSee('UserA Private');
    });
});

describe('GetTransactionsTool - Pagination safety', function () {
    it('handles per_page=0 without crashing', function () {
        $user = User::factory()->create();
        $account = Account::factory()->checking()->for($user)->create();
        $category = Category::factory()->expense()->for($user)->create();
        Transaction::factory()->expense()->for($user)->create([
            'account_id' => $account->id,
            'category_id' => $category->id,
        ]);

        $response = SpendoServer::actingAs($user)->tool(GetTransactionsTool::class, [
            'per_page' => 0,
        ]);

        $response->assertOk()
            ->assertSee('pagination');
    });
});

describe('UpdateBudgetTool - Validation errors', function () {
    it('returns error for invalid categories instead of throwing', function () {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();

        $response = SpendoServer::actingAs($user)->tool(UpdateBudgetTool::class, [
            'budget_id' => $budget->id,
            'items' => [
                ['category_id' => 99999, 'amount' => 100000],
            ],
        ]);

        $response->assertHasErrors(['not found']);
    });

    it('returns error for duplicate categories instead of throwing', function () {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();
        $cat = Category::factory()->expense()->for($user)->create();

        $response = SpendoServer::actingAs($user)->tool(UpdateBudgetTool::class, [
            'budget_id' => $budget->id,
            'items' => [
                ['category_id' => $cat->id, 'amount' => 100000],
                ['category_id' => $cat->id, 'amount' => 200000],
            ],
        ]);

        $response->assertHasErrors(['Duplicate']);
    });

    it('returns error for parent-child overlap instead of throwing', function () {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();
        $parent = Category::factory()->expense()->for($user)->create(['name' => 'ParentCat']);
        $child = Category::factory()->expense()->for($user)->create([
            'name' => 'ChildCat',
            'parent_id' => $parent->id,
        ]);

        $response = SpendoServer::actingAs($user)->tool(UpdateBudgetTool::class, [
            'budget_id' => $budget->id,
            'items' => [
                ['category_id' => $parent->id, 'amount' => 100000],
                ['category_id' => $child->id, 'amount' => 50000],
            ],
        ]);

        $response->assertHasErrors(['Cannot mix']);
    });
});
