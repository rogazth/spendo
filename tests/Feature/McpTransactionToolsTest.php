<?php

use App\Enums\CategoryType;
use App\Mcp\Servers\SpendoServer;
use App\Mcp\Tools\BulkCreateTransactionsTool;
use App\Mcp\Tools\CreateTransactionTool;
use App\Mcp\Tools\GetTransactionsTool;
use App\Mcp\Tools\UpdateTransactionTool;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;

// ─── CreateTransactionTool - Transfer ───────────────────────────────────────

describe('CreateTransactionTool - Transfer', function () {
    it('creates a transfer between accounts', function () {
        $user = User::factory()->create();
        $origin = Account::factory()->for($user)->create(['name' => 'Origin']);
        $dest = Account::factory()->for($user)->create(['name' => 'Destination']);

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

    it('creates bidirectional linked_transaction_id for transfer', function () {
        $user = User::factory()->create();
        $origin = Account::factory()->for($user)->create();
        $dest = Account::factory()->for($user)->create();

        Category::factory()->create([
            'user_id' => null,
            'name' => 'Transferencia',
            'type' => CategoryType::System,
            'is_system' => true,
        ]);

        SpendoServer::actingAs($user)->tool(CreateTransactionTool::class, [
            'type' => 'transfer',
            'amount' => 50000,
            'description' => 'Link test',
            'origin_account_id' => $origin->id,
            'destination_account_id' => $dest->id,
        ])->assertOk();

        $out = Transaction::where('type', 'transfer_out')->where('account_id', $origin->id)->firstOrFail();
        $in = Transaction::where('type', 'transfer_in')->where('account_id', $dest->id)->firstOrFail();

        expect($out->linked_transaction_id)->toBe($in->id);
        expect($in->linked_transaction_id)->toBe($out->id);
    });

    it('rejects transfer to same account', function () {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

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
        $dest = Account::factory()->for($user)->create();

        $response = SpendoServer::actingAs($user)->tool(CreateTransactionTool::class, [
            'type' => 'transfer',
            'amount' => 100000,
            'description' => 'Missing origin',
            'destination_account_id' => $dest->id,
        ]);

        $response->assertHasErrors(['Origin account is required']);
    });
});

// ─── CreateTransactionTool - Idempotency ────────────────────────────────────

describe('CreateTransactionTool - Idempotency', function () {
    it('deduplicates transactions with same idempotency key', function () {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
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
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->expense()->for($user)->create();

        $response = SpendoServer::actingAs($user)->tool(CreateTransactionTool::class, [
            'type' => 'expense',
            'amount' => 58900, // 58,900 CLP
            'description' => 'Grocery shopping',
            'category_id' => $category->id,
            'account_id' => $account->id,
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

// ─── CreateTransactionTool - Tags ───────────────────────────────────────────

describe('CreateTransactionTool - Tags', function () {
    it('attaches tags to a new expense', function () {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->expense()->for($user)->create();
        $tag = Tag::factory()->for($user)->create(['name' => 'Recurring']);

        $response = SpendoServer::actingAs($user)->tool(CreateTransactionTool::class, [
            'type' => 'expense',
            'amount' => 10000,
            'description' => 'Tagged expense',
            'category_id' => $category->id,
            'account_id' => $account->id,
            'tag_ids' => [$tag->id],
        ]);

        $response->assertOk()->assertSee('Expense created successfully');

        $transaction = Transaction::where('user_id', $user->id)->first();
        expect($transaction->tags->pluck('id')->all())->toBe([$tag->id]);
    });
});

// ─── BulkCreateTransactionsTool ─────────────────────────────────────────────

describe('BulkCreateTransactionsTool', function () {
    it('creates multiple transactions in one request', function () {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $incomeCategory = Category::factory()->income()->for($user)->create();
        $expenseCategory = Category::factory()->expense()->for($user)->create();

        $response = SpendoServer::actingAs($user)->tool(BulkCreateTransactionsTool::class, [
            'transactions' => [
                [
                    'type' => 'income',
                    'amount' => 1000000,
                    'description' => 'Salary',
                    'category_id' => $incomeCategory->id,
                    'account_id' => $account->id,
                ],
                [
                    'type' => 'expense',
                    'amount' => 50000,
                    'description' => 'Groceries',
                    'category_id' => $expenseCategory->id,
                    'account_id' => $account->id,
                ],
            ],
        ]);

        $response->assertOk()
            ->assertSee('"succeeded": 2')
            ->assertSee('"failed": 0')
            ->assertSee('Income created successfully')
            ->assertSee('Expense created successfully');

        expect(Transaction::where('user_id', $user->id)->count())->toBe(2);
    });

    it('reports partial failures without rolling back successes', function () {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $incomeCategory = Category::factory()->income()->for($user)->create();

        $response = SpendoServer::actingAs($user)->tool(BulkCreateTransactionsTool::class, [
            'transactions' => [
                [
                    'type' => 'income',
                    'amount' => 500000,
                    'description' => 'Valid income',
                    'category_id' => $incomeCategory->id,
                    'account_id' => $account->id,
                ],
                [
                    'type' => 'expense',
                    'amount' => 10000,
                    'description' => 'Missing account and category',
                    // account_id and category_id deliberately omitted
                ],
            ],
        ]);

        $response->assertOk()
            ->assertSee('"succeeded": 1')
            ->assertSee('"failed": 1');

        // The successful income was committed
        expect(Transaction::where('user_id', $user->id)->count())->toBe(1);
    });

    it('deduplicates bulk items with idempotency keys', function () {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->income()->for($user)->create();

        $params = [
            'transactions' => [
                [
                    'type' => 'income',
                    'amount' => 500000,
                    'description' => 'Salary',
                    'category_id' => $category->id,
                    'account_id' => $account->id,
                    'idempotency_key' => 'salary-bulk-march',
                ],
            ],
        ];

        SpendoServer::actingAs($user)->tool(BulkCreateTransactionsTool::class, $params)->assertOk();
        $response = SpendoServer::actingAs($user)->tool(BulkCreateTransactionsTool::class, $params);

        $response->assertOk()->assertSee('already exists (idempotent)');
        expect(Transaction::where('user_id', $user->id)->count())->toBe(1);
    });

    it('rejects an empty transactions array', function () {
        $user = User::factory()->create();

        $response = SpendoServer::actingAs($user)->tool(BulkCreateTransactionsTool::class, [
            'transactions' => [],
        ]);

        $response->assertHasErrors();
    });

    it('includes index in each result', function () {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->income()->for($user)->create();

        $response = SpendoServer::actingAs($user)->tool(BulkCreateTransactionsTool::class, [
            'transactions' => [
                [
                    'type' => 'income',
                    'amount' => 100000,
                    'description' => 'Test',
                    'category_id' => $category->id,
                    'account_id' => $account->id,
                ],
            ],
        ]);

        $response->assertOk()->assertSee('"index": 0');
    });

    it('bulk transfer creates both legs and links them', function () {
        $user = User::factory()->create();
        $origin = Account::factory()->for($user)->create();
        $dest = Account::factory()->for($user)->create();

        Category::factory()->create([
            'user_id' => null,
            'name' => 'Transferencia',
            'type' => CategoryType::System,
            'is_system' => true,
        ]);

        $response = SpendoServer::actingAs($user)->tool(BulkCreateTransactionsTool::class, [
            'transactions' => [
                [
                    'type' => 'transfer',
                    'amount' => 100000,
                    'description' => 'Bulk transfer',
                    'origin_account_id' => $origin->id,
                    'destination_account_id' => $dest->id,
                ],
            ],
        ]);

        $response->assertOk()->assertSee('"succeeded": 1');

        $out = Transaction::where('type', 'transfer_out')->where('account_id', $origin->id)->firstOrFail();
        $in = Transaction::where('type', 'transfer_in')->where('account_id', $dest->id)->firstOrFail();

        expect($out->linked_transaction_id)->toBe($in->id);
        expect($in->linked_transaction_id)->toBe($out->id);
    });
});

// ─── GetTransactionsTool - Enhanced ─────────────────────────────────────────

describe('GetTransactionsTool - Enhanced', function () {
    it('returns totals block', function () {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
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
        $account = Account::factory()->for($user)->create();

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
        $account = Account::factory()->for($user)->create();
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

    it('filters by tag_ids', function () {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->expense()->for($user)->create();
        $tag = Tag::factory()->for($user)->create(['name' => 'Travel']);

        $taggedTransaction = Transaction::factory()->expense()->for($user)->create([
            'account_id' => $account->id,
            'category_id' => $category->id,
            'description' => 'Tagged transaction',
        ]);
        $taggedTransaction->tags()->attach($tag);

        Transaction::factory()->expense()->for($user)->create([
            'account_id' => $account->id,
            'category_id' => $category->id,
            'description' => 'Untagged transaction',
        ]);

        $response = SpendoServer::actingAs($user)->tool(GetTransactionsTool::class, [
            'tag_ids' => [$tag->id],
        ]);

        $response->assertOk()
            ->assertSee('Tagged transaction')
            ->assertDontSee('Untagged transaction');
    });
});

// ─── UpdateTransactionTool ───────────────────────────────────────────────────

describe('UpdateTransactionTool', function () {
    it('updates description, amount, and date of an expense', function () {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->expense()->for($user)->create();

        $transaction = Transaction::factory()->expense()->for($user)->create([
            'account_id' => $account->id,
            'category_id' => $category->id,
            'description' => 'Old description',
            'amount' => 10000,
        ]);

        $response = SpendoServer::actingAs($user)->tool(UpdateTransactionTool::class, [
            'transaction_id' => $transaction->id,
            'description' => 'New description',
            'amount' => 15000,
            'transaction_date' => '2026-03-01',
        ]);

        $response->assertOk()->assertSee('updated successfully');

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'description' => 'New description',
            'transaction_date' => '2026-03-01 00:00:00',
        ]);
    });

    it('updates the category', function () {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $oldCategory = Category::factory()->expense()->for($user)->create();
        $newCategory = Category::factory()->expense()->for($user)->create();

        $transaction = Transaction::factory()->expense()->for($user)->create([
            'account_id' => $account->id,
            'category_id' => $oldCategory->id,
        ]);

        SpendoServer::actingAs($user)->tool(UpdateTransactionTool::class, [
            'transaction_id' => $transaction->id,
            'category_id' => $newCategory->id,
        ])->assertOk();

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'category_id' => $newCategory->id,
        ]);
    });

    it('updates tags on a transaction', function () {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->expense()->for($user)->create();
        $tag1 = Tag::factory()->for($user)->create(['name' => 'Tag1']);
        $tag2 = Tag::factory()->for($user)->create(['name' => 'Tag2']);

        $transaction = Transaction::factory()->expense()->for($user)->create([
            'account_id' => $account->id,
            'category_id' => $category->id,
        ]);
        $transaction->tags()->attach($tag1);

        SpendoServer::actingAs($user)->tool(UpdateTransactionTool::class, [
            'transaction_id' => $transaction->id,
            'tag_ids' => [$tag2->id],
        ])->assertOk()->assertSee('updated successfully');

        $transaction->refresh();
        expect($transaction->tags->pluck('id')->all())->toBe([$tag2->id]);
    });

    it('returns error for transfer transactions', function () {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $transfer = Transaction::factory()->transferOut()->for($user)->create([
            'account_id' => $account->id,
        ]);

        SpendoServer::actingAs($user)->tool(UpdateTransactionTool::class, [
            'transaction_id' => $transfer->id,
            'description' => 'New desc',
        ])->assertHasErrors(['Transfer transactions cannot be updated.']);
    });

    it('returns error for transaction belonging to another user', function () {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $account = Account::factory()->for($other)->create();

        $transaction = Transaction::factory()->expense()->for($other)->create([
            'account_id' => $account->id,
        ]);

        SpendoServer::actingAs($user)->tool(UpdateTransactionTool::class, [
            'transaction_id' => $transaction->id,
            'description' => 'Hacked',
        ])->assertHasErrors(['Transaction not found.']);
    });

    it('returns error when no fields are provided', function () {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $transaction = Transaction::factory()->expense()->for($user)->create([
            'account_id' => $account->id,
        ]);

        SpendoServer::actingAs($user)->tool(UpdateTransactionTool::class, [
            'transaction_id' => $transaction->id,
        ])->assertHasErrors(['No fields provided to update.']);
    });
});
