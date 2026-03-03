<?php

use App\Models\Instrument;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

// ---------------------------------------------------------------------------
// Guest access
// ---------------------------------------------------------------------------

test('instruments guests are redirected to login', function () {
    $this->get('/instruments')->assertRedirect('/login');
});

// ---------------------------------------------------------------------------
// Index
// ---------------------------------------------------------------------------

test('instruments index renders page for authenticated user', function () {
    $user = User::factory()->create();
    Instrument::factory()->creditCard()->for($user)->create();

    $this->actingAs($user)->get('/instruments')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('instruments/index')
            ->has('instruments')
        );
});

test('instruments index only shows user instruments', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Instrument::factory()->for($user)->count(2)->create();
    Instrument::factory()->for($other)->count(3)->create();

    $this->actingAs($user)->get('/instruments')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->count('instruments', 2)
        );
});
