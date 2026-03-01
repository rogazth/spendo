<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('filters transactions by multiple payment methods', function () {
    $user = User::factory()->create();
    $account = Account::factory()->checking()->for($user)->create();
    $category = Category::factory()->expense()->for($user)->create();
    $paymentMethodA = PaymentMethod::factory()->debitCard()->for($user)->create([
        'linked_account_id' => $account->id,
    ]);
    $paymentMethodB = PaymentMethod::factory()->creditCard()->for($user)->create();

    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'category_id' => $category->id,
        'payment_method_id' => $paymentMethodA->id,
        'description' => 'Tx PM A',
        'transaction_date' => now()->subDay(),
    ]);
    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'category_id' => $category->id,
        'payment_method_id' => $paymentMethodB->id,
        'description' => 'Tx PM B',
        'transaction_date' => now(),
    ]);

    $response = $this->actingAs($user)->get('/transactions?payment_method_ids[]='.$paymentMethodA->id);

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('transactions/index')
        ->where('filters.payment_method_ids', [$paymentMethodA->id])
        ->where('transactions.data.0.description', 'Tx PM A')
    );
});

test('supports legacy payment_method_id filter in transactions index', function () {
    $user = User::factory()->create();
    $account = Account::factory()->checking()->for($user)->create();
    $category = Category::factory()->expense()->for($user)->create();
    $paymentMethod = PaymentMethod::factory()->debitCard()->for($user)->create([
        'linked_account_id' => $account->id,
    ]);

    Transaction::factory()->expense()->for($user)->create([
        'account_id' => $account->id,
        'category_id' => $category->id,
        'payment_method_id' => $paymentMethod->id,
        'description' => 'Tx legacy filter',
    ]);

    $response = $this->actingAs($user)->get('/transactions?payment_method_id='.$paymentMethod->id);

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('transactions/index')
        ->where('filters.payment_method_ids', [$paymentMethod->id])
        ->where('transactions.data.0.description', 'Tx legacy filter')
    );
});

test('stores and updates exclude_from_budget in transactions', function () {
    $this->seed(CurrencySeeder::class);

    $user = User::factory()->create();
    $account = Account::factory()->checking()->for($user)->create();
    $category = Category::factory()->expense()->for($user)->create();
    $paymentMethod = PaymentMethod::factory()->debitCard()->for($user)->create([
        'linked_account_id' => $account->id,
    ]);

    $this->actingAs($user)->post('/transactions', [
        'type' => 'expense',
        'account_id' => $account->id,
        'payment_method_id' => $paymentMethod->id,
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
        'payment_method_id' => $paymentMethod->id,
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
