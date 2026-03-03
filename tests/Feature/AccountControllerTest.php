<?php

use App\Models\Account;
use App\Models\Currency;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    Currency::updateOrCreate(['code' => 'CLP'], ['name' => 'Peso chileno', 'locale' => 'es-CL']);
    Currency::updateOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'locale' => 'en-US']);
});

// ---------------------------------------------------------------------------
// Guest access
// ---------------------------------------------------------------------------

test('guests are redirected to login', function () {
    $this->get('/accounts')->assertRedirect('/login');
});

// ---------------------------------------------------------------------------
// Index
// ---------------------------------------------------------------------------

test('index renders accounts page for authenticated user', function () {
    $user = User::factory()->create();
    Account::factory()->for($user)->create();

    $this->actingAs($user)->get('/accounts')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('accounts/index')
            ->has('accounts')
        );
});

// ---------------------------------------------------------------------------
// Store
// ---------------------------------------------------------------------------

test('store creates an account', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/accounts', [
        'name' => 'Mi Cuenta',
        'currency' => 'CLP',
    ])->assertRedirect('/accounts');

    $this->assertDatabaseHas('accounts', [
        'user_id' => $user->id,
        'name' => 'Mi Cuenta',
        'currency' => 'CLP',
    ]);
});

test('store creates an income transaction for initial balance', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/accounts', [
        'name' => 'Cuenta con saldo',
        'currency' => 'CLP',
        'initial_balance' => 100000,
    ])->assertRedirect('/accounts');

    $account = Account::query()->where('name', 'Cuenta con saldo')->firstOrFail();

    $this->assertDatabaseHas('transactions', [
        'user_id' => $user->id,
        'account_id' => $account->id,
        'type' => 'income',
        'description' => 'Balance inicial',
    ]);
});

test('store validates required fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/accounts', [])
        ->assertSessionHasErrors(['name', 'currency']);
});

test('store rejects duplicate account names for the same user', function () {
    $user = User::factory()->create();
    Account::factory()->for($user)->create(['name' => 'Mi Banco']);

    $this->actingAs($user)->post('/accounts', [
        'name' => 'Mi Banco',
        'currency' => 'CLP',
    ])->assertSessionHasErrors('name');
});

test('store rejects invalid currency code', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/accounts', [
        'name' => 'Cuenta Inválida',
        'currency' => 'XYZ',
    ])->assertSessionHasErrors('currency');
});

test('store rejects negative initial_balance', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/accounts', [
        'name' => 'Cuenta Negativa',
        'currency' => 'CLP',
        'initial_balance' => -100,
    ])->assertSessionHasErrors('initial_balance');
});

test('store sets default and clears other defaults', function () {
    $user = User::factory()->create();
    $existing = Account::factory()->for($user)->create(['is_default' => true]);

    $this->actingAs($user)->post('/accounts', [
        'name' => 'Nueva Cuenta',
        'currency' => 'CLP',
        'is_default' => true,
    ]);

    expect($existing->fresh()->is_default)->toBeFalse();
    $new = Account::query()->where('name', 'Nueva Cuenta')->firstOrFail();
    expect($new->is_default)->toBeTrue();
});

// ---------------------------------------------------------------------------
// Update
// ---------------------------------------------------------------------------

test('update modifies an account', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['name' => 'Original']);

    $this->actingAs($user)->put("/accounts/{$account->uuid}", [
        'name' => 'Actualizada',
        'currency' => 'CLP',
    ])->assertRedirect('/accounts');

    expect($account->fresh()->name)->toBe('Actualizada');
});

test('update returns 403 for another user account', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $account = Account::factory()->for($owner)->create();

    $this->actingAs($other)->put("/accounts/{$account->uuid}", [
        'name' => 'Hack',
        'currency' => 'CLP',
    ])->assertForbidden();
});

// ---------------------------------------------------------------------------
// makeDefault
// ---------------------------------------------------------------------------

test('makeDefault sets account as default', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['is_default' => false]);

    $this->actingAs($user)->patch("/accounts/{$account->uuid}/make-default")
        ->assertRedirect();

    expect($account->fresh()->is_default)->toBeTrue();
});

test('makeDefault returns 403 for another user account', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $account = Account::factory()->for($owner)->create();

    $this->actingAs($other)->patch("/accounts/{$account->uuid}/make-default")
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// Destroy
// ---------------------------------------------------------------------------

test('destroy soft-deletes an account', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();

    $this->actingAs($user)->delete("/accounts/{$account->uuid}")
        ->assertRedirect('/accounts');

    $this->assertSoftDeleted('accounts', ['id' => $account->id]);
});

test('destroy returns 403 for another user account', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $account = Account::factory()->for($owner)->create();

    $this->actingAs($other)->delete("/accounts/{$account->uuid}")
        ->assertForbidden();
});
