<?php

use App\Enums\UserRole;
use App\Models\User;

test('user role enum exposes the admin and kasir values', function () {
    expect(UserRole::Admin->value)->toBe('admin')
        ->and(UserRole::Kasir->value)->toBe('kasir')
        ->and(UserRole::cases())->toHaveCount(2);
});

test('the factory creates kasir users by default', function () {
    $user = User::factory()->create();

    expect($user->role)->toBe(UserRole::Kasir)
        ->and($user->isKasir())->toBeTrue()
        ->and($user->isAdmin())->toBeFalse();
});

test('the admin state creates admin users', function () {
    $user = User::factory()->admin()->create();

    expect($user->role)->toBe(UserRole::Admin)
        ->and($user->isAdmin())->toBeTrue()
        ->and($user->isKasir())->toBeFalse();
});

test('the kasir state creates kasir users', function () {
    $user = User::factory()->kasir()->create();

    expect($user->role)->toBe(UserRole::Kasir)
        ->and($user->isKasir())->toBeTrue()
        ->and($user->isAdmin())->toBeFalse();
});

test('the role attribute is cast to the user role enum', function () {
    $user = User::factory()->create(['role' => UserRole::Admin->value]);

    expect($user->role)->toBeInstanceOf(UserRole::class)
        ->and($user->role)->toBe(UserRole::Admin);
});

test('the role attribute is not mass assignable', function () {
    $user = User::create([
        'name' => 'Kasir Baru',
        'email' => 'kasir@example.test',
        'password' => 'password',
        'role' => UserRole::Admin->value,
    ]);

    expect($user->fresh()->role)->toBe(UserRole::Kasir);
});
