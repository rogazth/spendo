<?php

use App\Models\Account;
use App\Models\PaymentMethod;
use App\Models\User;

test('account detail route is not available', function () {
    $user = User::factory()->create();
    $account = Account::factory()->checking()->for($user)->create();

    $this->actingAs($user)
        ->get("/accounts/{$account->uuid}")
        ->assertStatus(405);
});

test('payment method detail route is not available', function () {
    $user = User::factory()->create();
    $paymentMethod = PaymentMethod::factory()->creditCard()->for($user)->create();

    $this->actingAs($user)
        ->get("/payment-methods/{$paymentMethod->uuid}")
        ->assertStatus(405);
});
