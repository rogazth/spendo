<?php

use App\Models\Account;
use App\Models\User;

test('account detail route is not available', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();

    $this->actingAs($user)
        ->get("/accounts/{$account->uuid}")
        ->assertStatus(405);
});
