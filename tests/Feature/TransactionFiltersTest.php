<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Instrument;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('filters transactions by multiple instruments', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $category = Category::factory()->expense()->for($user)->create();
    $instrumentA = Instrument::factory()->checking()->for($user)->create();
    $instrumentB = Instrument::factory()->creditCard()->for($user)->create();

    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'category_id' => $category->id,
        'instrument_id' => $instrumentA->id,
        'description' => 'Tx Instrument A',
        'transaction_date' => now()->subDay(),
    ]);
    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'category_id' => $category->id,
        'instrument_id' => $instrumentB->id,
        'description' => 'Tx Instrument B',
        'transaction_date' => now(),
    ]);

    $response = $this->actingAs($user)->get('/transactions?instrument_ids[]='.$instrumentA->id);

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('transactions/index')
        ->where('filters.instrument_ids', [$instrumentA->id])
        ->where('transactions.data.0.description', 'Tx Instrument A')
    );
});

test('stores and updates exclude_from_budget in transactions', function () {
    $this->seed(CurrencySeeder::class);

    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $category = Category::factory()->expense()->for($user)->create();
    $instrument = Instrument::factory()->checking()->for($user)->create();

    $this->actingAs($user)->post('/transactions', [
        'type' => 'expense',
        'account_id' => $account->id,
        'instrument_id' => $instrument->id,
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
        'instrument_id' => $instrument->id,
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
