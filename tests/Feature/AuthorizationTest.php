<?php

use App\Enums\UserRole;
use App\Models\User;

test('admin can access menu management', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $this->get(route('categories.index'))->assertOk();
    $this->get(route('products.index'))->assertOk();
    $this->get(route('toppings.index'))->assertOk();
});

test('kasir is denied access to menu management with 403', function () {
    $kasir = User::factory()->create();
    $this->actingAs($kasir);

    $this->get(route('categories.index'))->assertForbidden();
    $this->get(route('products.index'))->assertForbidden();
    $this->get(route('toppings.index'))->assertForbidden();

    $this->post(route('categories.store'), ['name' => 'X', 'sort_order' => 1])->assertForbidden();
    $this->post(route('products.store'), ['name' => 'X', 'price' => 1000])->assertForbidden();
    $this->post(route('toppings.store'), ['name' => 'X', 'price' => 1000])->assertForbidden();
});

test('kasir can still access operational features', function () {
    $kasir = User::factory()->create();
    $this->actingAs($kasir);

    $this->get(route('pos.index'))->assertOk();
    $this->get(route('orders.index'))->assertOk();
});

test('role cannot be mass assigned via create', function () {
    $user = User::create([
        'name' => 'Test',
        'email' => 'role-test@example.test',
        'password' => 'password',
        'role' => 'admin',
    ]);

    expect($user->fresh()->role)->toBe(UserRole::Kasir);
});

test('create-admin command is guarded when admin exists', function () {
    User::factory()->admin()->create();

    $this->artisan('app:create-admin')
        ->expectsConfirmation('Tetap lanjut membuat akun admin baru?', 'no')
        ->assertExitCode(0);
});
