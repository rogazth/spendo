<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
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
        'type' => 'expense',
        'account_id' => $account->id,
        'category_id' => $category->id,
        'amount' => 12345,
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

    $this->actingAs($user)->put("/transactions/{$transaction->uuid}", [
        'type' => 'expense',
        'account_id' => $account->id,
        'category_id' => $category->id,
        'amount' => 12345,
        'currency' => 'CLP',
        'description' => 'Tx exclusion test',
        'exclude_from_budget' => false,
        'transaction_date' => now()->toDateString(),
    ])->assertRedirect('/transactions');

    $transaction->refresh();
    expect($transaction->exclude_from_budget)->toBeFalse();
});
