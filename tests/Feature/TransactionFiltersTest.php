<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserSettings;
use Carbon\CarbonImmutable;
use Database\Seeders\CurrencySeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('filters transactions by tag', function () {
    $this->seed(CurrencySeeder::class);

    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $category = Category::factory()->expense()->for($user)->create();

    $tagA = Tag::factory()->for($user)->create(['name' => 'Trabajo']);
    $tagB = Tag::factory()->for($user)->create(['name' => 'Personal']);

    $txA = Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'category_id' => $category->id,
        'description' => 'Tx Tag A',
        'transaction_date' => now()->subDay(),
    ]);
    $txA->tags()->attach($tagA);

    $txB = Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'category_id' => $category->id,
        'description' => 'Tx Tag B',
        'transaction_date' => now(),
    ]);
    $txB->tags()->attach($tagB);

    $response = $this->actingAs($user)->get('/transactions?tag_ids[]='.$tagA->id);

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('transactions/index')
        ->where('transactions.meta.total', 1)
        ->where('transactions.data.0.description', 'Tx Tag A')
    );
});

test('stores and updates exclude_from_budget in transactions', function () {
    $this->seed(CurrencySeeder::class);

    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $category = Category::factory()->expense()->for($user)->create();

    $this->actingAs($user)->post('/transactions', [
        'account_id' => $account->id,
        'category_id' => $category->id,
        'amount' => -12345,
        'currency' => 'CLP',
        'description' => 'Tx exclusion test',
        'exclude_from_budget' => true,
        'transaction_date' => now()->toDateString(),
    ])->assertRedirect('/transactions');

    $transaction = Transaction::query()
        ->where('user_id', $user->id)
        ->where('description', 'Tx exclusion test')
        ->firstOrFail();

    expect($transaction->exclude_from_budget)->toBeTrue();
    expect($transaction->amount)->toEqual(-12345);

    $this->actingAs($user)->put("/transactions/{$transaction->uuid}", [
        'account_id' => $account->id,
        'category_id' => $category->id,
        'amount' => -12345,
        'currency' => 'CLP',
        'description' => 'Tx exclusion test',
        'exclude_from_budget' => false,
        'transaction_date' => now()->toDateString(),
    ])->assertRedirect('/transactions');

    $transaction->refresh();
    expect($transaction->exclude_from_budget)->toBeFalse();
});

test('defaults transactions list to user current cycle', function () {
    $this->seed(CurrencySeeder::class);

    CarbonImmutable::setTestNow('2026-05-28 12:00:00');

    $user = User::factory()->create();
    UserSettings::factory()->for($user)->create(['budget_cycle_start_day' => 1]);

    $account = Account::factory()->for($user)->create();
    $category = Category::factory()->expense()->for($user)->create();

    $inCycle = Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'category_id' => $category->id,
        'description' => 'In cycle',
        'transaction_date' => CarbonImmutable::parse('2026-05-10'),
    ]);

    $beforeCycle = Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'category_id' => $category->id,
        'description' => 'Before cycle',
        'transaction_date' => CarbonImmutable::parse('2026-04-15'),
    ]);

    $this->actingAs($user)->get('/transactions')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('transactions/index')
            ->where('filters.date_from', '2026-05-01')
            ->where('filters.date_to', '2026-05-31')
            ->where('transactions.meta.total', 1)
            ->where('transactions.data.0.description', 'In cycle')
        );

    CarbonImmutable::setTestNow();
});

test('dates=all sentinel disables default cycle scoping', function () {
    $this->seed(CurrencySeeder::class);

    CarbonImmutable::setTestNow('2026-05-28 12:00:00');

    $user = User::factory()->create();
    UserSettings::factory()->for($user)->create(['budget_cycle_start_day' => 1]);

    $account = Account::factory()->for($user)->create();
    $category = Category::factory()->expense()->for($user)->create();

    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'category_id' => $category->id,
        'transaction_date' => CarbonImmutable::parse('2026-05-10'),
    ]);

    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'category_id' => $category->id,
        'transaction_date' => CarbonImmutable::parse('2026-04-15'),
    ]);

    $this->actingAs($user)->get('/transactions?dates=all')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('transactions/index')
            ->where('filters.dates', 'all')
            ->where('filters.date_from', null)
            ->where('filters.date_to', null)
            ->where('transactions.meta.total', 2)
        );

    CarbonImmutable::setTestNow();
});

test('explicit date_from/date_to filters override cycle default', function () {
    $this->seed(CurrencySeeder::class);

    CarbonImmutable::setTestNow('2026-05-28 12:00:00');

    $user = User::factory()->create();
    UserSettings::factory()->for($user)->create(['budget_cycle_start_day' => 1]);

    $account = Account::factory()->for($user)->create();
    $category = Category::factory()->expense()->for($user)->create();

    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'category_id' => $category->id,
        'description' => 'May',
        'transaction_date' => CarbonImmutable::parse('2026-05-10'),
    ]);

    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'category_id' => $category->id,
        'description' => 'April',
        'transaction_date' => CarbonImmutable::parse('2026-04-15'),
    ]);

    $this->actingAs($user)->get('/transactions?date_from=2026-04-01&date_to=2026-04-30')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('transactions/index')
            ->where('filters.date_from', '2026-04-01')
            ->where('filters.date_to', '2026-04-30')
            ->where('transactions.meta.total', 1)
            ->where('transactions.data.0.description', 'April')
        );

    CarbonImmutable::setTestNow();
});
