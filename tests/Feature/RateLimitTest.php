<?php

use App\Models\User;

test('a user can attempt login five times before being throttled', function () {
    User::factory()->create([
        'email' => 'kasir@example.test',
        'password' => 'rahasia123',
    ]);

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->post(route('login.store'), [
            'email' => 'kasir@example.test',
            'password' => 'salah-sekali',
        ])->assertSessionHasErrors('email')
            ->assertStatus(302);
    }

    expect($this->post(route('login.store'), [
        'email' => 'kasir@example.test',
        'password' => 'salah-sekali',
    ])->getStatusCode())->toBe(429);
});

test('throttling keys on the normalized email and ip combination', function () {
    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->post(route('login.store'), [
            'email' => 'KASIR@example.test',
            'password' => 'salah-sekali',
        ])->assertStatus(302);
    }

    $this->post(route('login.store'), [
        'email' => 'kasir@example.test',
        'password' => 'salah-sekali',
    ])->assertStatus(429);
});

test('a successful login clears the failed attempt counter', function () {
    User::factory()->create([
        'email' => 'kasir@example.test',
        'password' => 'rahasia123',
    ]);

    for ($attempt = 1; $attempt <= 4; $attempt++) {
        $this->post(route('login.store'), [
            'email' => 'kasir@example.test',
            'password' => 'salah-sekali',
        ])->assertSessionHasErrors('email');
    }

    $this->post(route('login.store'), [
        'email' => 'kasir@example.test',
        'password' => 'rahasia123',
    ])->assertRedirect(route('welcome'));

    $this->flushSession();

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->post(route('login.store'), [
            'email' => 'kasir@example.test',
            'password' => 'salah-sekali',
        ])->assertStatus(302);
    }

    $this->post(route('login.store'), [
        'email' => 'kasir@example.test',
        'password' => 'salah-sekali',
    ])->assertStatus(429);
});

test('the login page itself is not throttled', function () {
    for ($visit = 1; $visit <= 10; $visit++) {
        $this->get(route('login'))->assertOk();
    }
});
