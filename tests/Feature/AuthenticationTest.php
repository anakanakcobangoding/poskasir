<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

test('guests can open the login page', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Masuk')
        ->assertSee('Email')
        ->assertSee('Kata sandi');
});

test('a user can log in with valid credentials', function () {
    $user = User::factory()->create([
        'email' => 'kasir@example.test',
        'password' => 'rahasia123',
    ]);

    $response = $this->post(route('login.store'), [
        'email' => 'kasir@example.test',
        'password' => 'rahasia123',
    ]);

    $response->assertRedirect(route('welcome'));

    $this->assertAuthenticatedAs($user);
});

test('login always redirects to the welcome page instead of an intended asset URL', function () {
    User::factory()->create([
        'email' => 'kasir@example.test',
        'password' => 'rahasia123',
    ]);

    $this->from('/store-logo')->get('/store-logo')->assertRedirect(route('login'));

    $this->post(route('login.store'), [
        'email' => 'kasir@example.test',
        'password' => 'rahasia123',
    ])->assertRedirect(route('welcome'));

    $this->get('/')->assertRedirect(route('welcome'));
    $this->get(route('welcome'))->assertOk();
});

test('login fails with invalid credentials and does not reveal whether the email exists', function () {
    User::factory()->create(['email' => 'kasir@example.test']);

    $response = $this->from(route('login'))->post(route('login.store'), [
        'email' => 'kasir@example.test',
        'password' => 'salah-sekali',
    ]);

    $response->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    expect(session('errors')->first('email'))->toBe('Email atau kata sandi tidak sesuai.');

    $this->assertGuest();
});

test('login with an unknown email shows the same generic error', function () {
    $response = $this->from(route('login'))->post(route('login.store'), [
        'email' => 'tidak-ada@example.test',
        'password' => 'apa-saja',
    ]);

    $response->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    expect(session('errors')->first('email'))->toBe('Email atau kata sandi tidak sesuai.');

    $this->assertGuest();
});

test('login regenerates the session id', function () {
    User::factory()->create([
        'email' => 'kasir@example.test',
        'password' => 'rahasia123',
    ]);

    $this->get(route('login'));

    $sessionIdBefore = session()->getId();

    $this->post(route('login.store'), [
        'email' => 'kasir@example.test',
        'password' => 'rahasia123',
    ]);

    expect(session()->getId())->not->toBe($sessionIdBefore);
});

test('a logged in user can log out and the session is invalidated', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $this->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
    expect(Auth::check())->toBeFalse();
});

test('guests are redirected to login from the pos page', function () {
    $this->get(route('pos.index'))
        ->assertRedirect(route('login'));
});

test('guests are redirected to login from the order list', function () {
    $this->get(route('orders.index'))
        ->assertRedirect(route('login'));
});

test('guests cannot view an order detail or receipt', function () {
    $order = Order::factory()->create();

    $this->get(route('orders.show', $order))->assertRedirect(route('login'));
    $this->get(route('orders.receipt', $order))->assertRedirect(route('login'));
});

test('guests cannot create an order', function () {
    $this->postJson(route('orders.store'), ['type' => 'TA', 'items' => []])
        ->assertUnauthorized();
});

test('guests cannot manage the menu', function () {
    $this->get(route('categories.index'))->assertRedirect(route('login'));
    $this->get(route('products.index'))->assertRedirect(route('login'));
    $this->get(route('toppings.index'))->assertRedirect(route('login'));
});

test('there is no public registration route', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register')->assertNotFound();
});

test('an authenticated user can still reach the cashier page', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('pos.index'))->assertOk();
});
