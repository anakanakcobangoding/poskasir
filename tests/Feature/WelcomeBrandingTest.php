<?php

use App\Models\User;

test('authenticated users start at welcome and can open the existing cashier', function () {
    actingAsUser();

    $this->get('/')->assertOk()->assertSee('Selamat Datang di WarungCerdas POS')->assertSee('Mulai Kasir');
    $this->get(route('pos.index'))->assertOk();
});

test('login defaults to welcome while internal administration stays protected', function () {
    $user = User::factory()->kasir()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect('/');

    $this->get(route('dashboard.index'))->assertForbidden();
});

test('welcome exposes store status controls to administrators and keeps them hidden from cashiers', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->get('/')->assertOk()->assertSee('data-store-action', false)->assertSee('Tutup Toko');
    $this->putJson(route('settings.store-profile.close'))->assertOk()->assertJsonPath('status', 'closed');
    $this->get('/')->assertOk()->assertSee('TOKO TUTUP')->assertSee('Buka Toko');

    $this->actingAs(User::factory()->kasir()->create())->get('/')->assertOk()->assertDontSee('data-store-action', false)->assertSee('TOKO TUTUP');
});
