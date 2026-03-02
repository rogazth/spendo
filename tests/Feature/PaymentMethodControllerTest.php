<?php

use App\Models\Account;
use App\Models\Currency;
use App\Models\PaymentMethod;
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
    $this->get('/payment-methods')->assertRedirect('/login');
});

// ---------------------------------------------------------------------------
// Index
// ---------------------------------------------------------------------------

test('index renders payment methods page', function () {
    $user = User::factory()->create();
    PaymentMethod::factory()->creditCard()->for($user)->create();

    $this->actingAs($user)->get('/payment-methods')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('payment-methods/index')
            ->has('paymentMethods')
            ->has('accounts')
        );
});

// ---------------------------------------------------------------------------
// Store
// ---------------------------------------------------------------------------

test('store creates a credit card', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/payment-methods', [
        'name' => 'Visa Santander',
        'type' => 'credit_card',
        'credit_limit' => 500000,
        'billing_cycle_day' => 10,
        'payment_due_day' => 5,
    ])->assertRedirect('/payment-methods');

    $this->assertDatabaseHas('payment_methods', [
        'user_id' => $user->id,
        'name' => 'Visa Santander',
        'type' => 'credit_card',
    ]);
});

test('store creates a debit card linked to an account', function () {
    $user = User::factory()->create();
    $account = Account::factory()->checking()->for($user)->create();

    $this->actingAs($user)->post('/payment-methods', [
        'name' => 'Débito BCI',
        'type' => 'debit_card',
        'linked_account_id' => $account->id,
    ])->assertRedirect('/payment-methods');

    $this->assertDatabaseHas('payment_methods', [
        'user_id' => $user->id,
        'name' => 'Débito BCI',
        'linked_account_id' => $account->id,
    ]);
});

test('store rejects linked_account_id from another user', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $account = Account::factory()->checking()->for($owner)->create();

    $this->actingAs($other)->post('/payment-methods', [
        'name' => 'Débito Ajeno',
        'type' => 'debit_card',
        'linked_account_id' => $account->id,
    ])->assertSessionHasErrors('linked_account_id');
});

test('store validates required fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/payment-methods', [])
        ->assertSessionHasErrors(['name', 'type']);
});

test('store rejects duplicate payment method names for the same user', function () {
    $user = User::factory()->create();
    PaymentMethod::factory()->for($user)->create(['name' => 'Mi Tarjeta']);

    $this->actingAs($user)->post('/payment-methods', [
        'name' => 'Mi Tarjeta',
        'type' => 'credit_card',
    ])->assertSessionHasErrors('name');
});

test('store sets default and clears other defaults', function () {
    $user = User::factory()->create();
    $existing = PaymentMethod::factory()->for($user)->create(['is_default' => true]);

    $this->actingAs($user)->post('/payment-methods', [
        'name' => 'Nueva Tarjeta',
        'type' => 'credit_card',
        'is_default' => true,
    ]);

    expect($existing->fresh()->is_default)->toBeFalse();
    $new = PaymentMethod::query()->where('name', 'Nueva Tarjeta')->firstOrFail();
    expect($new->is_default)->toBeTrue();
});

// ---------------------------------------------------------------------------
// Update
// ---------------------------------------------------------------------------

test('update modifies a payment method', function () {
    $user = User::factory()->create();
    $pm = PaymentMethod::factory()->creditCard()->for($user)->create(['name' => 'Original']);

    $this->actingAs($user)->put("/payment-methods/{$pm->uuid}", [
        'name' => 'Actualizada',
        'type' => 'credit_card',
    ])->assertRedirect('/payment-methods');

    expect($pm->fresh()->name)->toBe('Actualizada');
});

test('update returns 403 for another user payment method', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $pm = PaymentMethod::factory()->creditCard()->for($owner)->create();

    $this->actingAs($other)->put("/payment-methods/{$pm->uuid}", [
        'name' => 'Hack',
        'type' => 'credit_card',
    ])->assertForbidden();
});

// ---------------------------------------------------------------------------
// makeDefault
// ---------------------------------------------------------------------------

test('makeDefault sets payment method as default', function () {
    $user = User::factory()->create();
    $pm = PaymentMethod::factory()->for($user)->create(['is_default' => false]);

    $this->actingAs($user)->patch("/payment-methods/{$pm->uuid}/make-default")
        ->assertRedirect();

    expect($pm->fresh()->is_default)->toBeTrue();
});

test('makeDefault returns 403 for another user payment method', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $pm = PaymentMethod::factory()->for($owner)->create();

    $this->actingAs($other)->patch("/payment-methods/{$pm->uuid}/make-default")
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// toggleActive
// ---------------------------------------------------------------------------

test('toggleActive flips the active state', function () {
    $user = User::factory()->create();
    $pm = PaymentMethod::factory()->for($user)->create(['is_active' => true]);

    $this->actingAs($user)->patch("/payment-methods/{$pm->uuid}/toggle-active")
        ->assertRedirect();

    expect($pm->fresh()->is_active)->toBeFalse();

    $this->actingAs($user)->patch("/payment-methods/{$pm->uuid}/toggle-active");

    expect($pm->fresh()->is_active)->toBeTrue();
});

test('toggleActive returns 403 for another user payment method', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $pm = PaymentMethod::factory()->for($owner)->create();

    $this->actingAs($other)->patch("/payment-methods/{$pm->uuid}/toggle-active")
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// Destroy
// ---------------------------------------------------------------------------

test('destroy soft-deletes a payment method', function () {
    $user = User::factory()->create();
    $pm = PaymentMethod::factory()->for($user)->create();

    $this->actingAs($user)->delete("/payment-methods/{$pm->uuid}")
        ->assertRedirect('/payment-methods');

    $this->assertSoftDeleted('payment_methods', ['id' => $pm->id]);
});

test('destroy returns 403 for another user payment method', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $pm = PaymentMethod::factory()->for($owner)->create();

    $this->actingAs($other)->delete("/payment-methods/{$pm->uuid}")
        ->assertForbidden();
});
