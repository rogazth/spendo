<?php

use App\Models\User;

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('registration seeds the user\'s default categories', function () {
    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'seeded@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::where('email', 'seeded@example.com')->firstOrFail();

    expect($user->categories()->pluck('name')->all())
        ->toEqualCanonicalizing(['Sueldo', 'Otros Ingresos', 'Otros Gastos']);
});
