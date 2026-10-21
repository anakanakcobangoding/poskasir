<?php

use App\Models\Order;
use App\Models\Setting;
use App\Models\User;

test('store profile saves all fields without resetting other settings', function () {
    $this->actingAs(User::factory()->admin()->create());
    Setting::create(['key' => 'store_status', 'value' => 'closed']);
    Setting::create(['key' => 'qris_image', 'value' => 'qris/existing.png']);

    $this->put(route('settings.store-profile.update'), [
        'store_name' => 'Warung Baru', 'pos_name' => 'Kasir Baru', 'subtitle' => 'Menu Baru',
    ])->assertRedirect(route('settings.store-profile.edit'))->assertSessionHas('success');

    foreach (['store_name' => 'Warung Baru', 'pos_name' => 'Kasir Baru', 'subtitle' => 'Menu Baru', 'store_status' => 'closed', 'qris_image' => 'qris/existing.png'] as $key => $value) {
        $this->assertDatabaseHas('settings', compact('key', 'value'));
    }
    $this->get(route('settings.store-profile.edit'))->assertOk()->assertSee('Warung Baru')->assertSee('Kasir Baru')->assertSee('Menu Baru');
    $this->get(route('dashboard.index'))->assertOk()->assertSee('Warung Baru');
});

test('omitted subtitle is preserved and an explicitly empty subtitle is cleared', function () {
    $this->actingAs(User::factory()->admin()->create());
    Setting::create(['key' => 'subtitle', 'value' => 'Existing']);
    $profile = ['store_name' => 'Warung', 'pos_name' => 'Kasir'];

    $this->put(route('settings.store-profile.update'), $profile)->assertSessionHasNoErrors();
    $this->assertDatabaseHas('settings', ['key' => 'subtitle', 'value' => 'Existing']);
    $this->put(route('settings.store-profile.update'), $profile + ['subtitle' => ''])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('settings', ['key' => 'subtitle', 'value' => null]);
});

test('invalid profile changes preserve existing information', function () {
    $this->actingAs(User::factory()->admin()->create());
    Setting::create(['key' => 'store_name', 'value' => 'Existing']);

    $this->put(route('settings.store-profile.update'), ['store_name' => '', 'pos_name' => 'Kasir'])
        ->assertSessionHasErrors('store_name')->assertSessionMissing('success');

    $this->assertDatabaseHas('settings', ['key' => 'store_name', 'value' => 'Existing']);
});

test('store branding survives logout and login and appears on an existing receipt', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $order = Order::factory()->paid()->create();

    $this->put(route('settings.store-profile.update'), ['store_name' => 'Nama Toko Terbaru', 'pos_name' => 'Kasir Utama', 'subtitle' => 'Menu Harian'])
        ->assertSessionHasNoErrors()->assertSessionHas('success');
    $this->post(route('logout'))->assertRedirect(route('login'));
    $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'password'])->assertRedirect();

    foreach (['settings.store-profile.edit', 'dashboard.index', 'pos.index'] as $route) {
        $this->get(route($route))->assertOk()->assertSee('Nama Toko Terbaru');
    }
    $this->get(route('orders.receipt', $order))->assertOk()->assertSee('Nama Toko Terbaru');
    $this->assertDatabaseHas('settings', ['key' => 'store_name', 'value' => 'Nama Toko Terbaru']);
});
