<?php

use App\Models\Account;
use App\Models\Instrument;
use App\Models\User;

test('account detail route is not available', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();

    $this->actingAs($user)
        ->get("/accounts/{$account->uuid}")
        ->assertStatus(405);
});

test('instrument detail route is not available', function () {
    $user = User::factory()->create();
    $instrument = Instrument::factory()->creditCard()->for($user)->create();

    $this->actingAs($user)
        ->get("/instruments/{$instrument->uuid}")
        ->assertStatus(404);
});
