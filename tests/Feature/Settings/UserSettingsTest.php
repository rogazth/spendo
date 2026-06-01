<?php

use App\Models\User;
use App\Models\UserSettings;
use Database\Seeders\CurrencySeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('preferences page renders with the user settings', function () {
    $user = User::factory()->create();
    UserSettings::factory()->for($user)->create([
        'default_currency' => 'USD',
        'timezone' => 'Europe/Madrid',
        'budget_cycle_start_day' => 15,
    ]);

    $response = $this->actingAs($user)->get(route('user-settings.edit'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('settings/preferences')
        ->where('settings.default_currency', 'USD')
        ->where('settings.timezone', 'Europe/Madrid')
        ->where('settings.budget_cycle_start_day', 15)
        ->has('currencies.0', fn (Assert $currency) => $currency
            ->has('value')
            ->has('label')
        )
        ->has('timezones')
    );
});

test('preferences resource only exposes existing columns', function () {
    $user = User::factory()->create();
    UserSettings::factory()->for($user)->create();

    $response = $this->actingAs($user)->get(route('user-settings.edit'));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('settings', fn (Assert $settings) => $settings
            ->has('id')
            ->has('uuid')
            ->has('default_currency')
            ->has('timezone')
            ->has('budget_cycle_start_day')
            ->has('created_at')
            ->has('updated_at')
        )
    );
});

test('preferences page renders defaults for a user without settings', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('user-settings.edit'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('settings.default_currency', 'CLP')
        ->where('settings.timezone', 'America/Santiago')
        ->where('settings.budget_cycle_start_day', 1)
    );
});

test('preferences can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('user-settings.edit'))
        ->patch(route('user-settings.update'), [
            'default_currency' => 'USD',
            'timezone' => 'Europe/London',
            'budget_cycle_start_day' => 10,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('user-settings.edit'));

    $this->assertDatabaseHas('user_settings', [
        'user_id' => $user->id,
        'default_currency' => 'USD',
        'timezone' => 'Europe/London',
        'budget_cycle_start_day' => 10,
    ]);
});

test('preferences update validates its fields', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('user-settings.edit'))
        ->patch(route('user-settings.update'), [
            'default_currency' => 'ZZZ',
            'timezone' => 'Not/AZone',
            'budget_cycle_start_day' => 31,
        ]);

    $response->assertSessionHasErrors([
        'default_currency',
        'timezone',
        'budget_cycle_start_day',
    ]);
});

test('preferences require authentication', function () {
    $this->get(route('user-settings.edit'))->assertRedirect(route('login'));
});
