<?php

use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('administrator can upload a store logo and customize welcome identity', function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->admin()->create());
    $this->put(route('settings.store-profile.update'), [
        'store_name' => 'Warung Logo', 'pos_name' => 'Kasir', 'subtitle' => 'Menu segar', 'main_title' => 'Selamat datang pelanggan',
        'logo' => UploadedFile::fake()->image('logo.png', 100, 100),
    ])->assertSessionHasNoErrors()->assertSessionHas('success');
    $path = Setting::where('key', 'store_logo')->value('value');
    expect($path)->toStartWith('store-logos/');
    Storage::disk('public')->assertExists($path);
    $this->get('/')->assertOk()->assertSee('Selamat datang pelanggan')->assertSee('Menu segar')->assertSee(route('settings.store-logo', absolute: false));
    $this->get(route('settings.store-logo'))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    $order = Order::factory()->create();
    $this->get(route('orders.receipt', $order))->assertOk()->assertSee('Warung Logo')->assertSee(route('settings.store-logo', absolute: false));
});

test('logo can be removed from store profile without deleting the stored file', function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->admin()->create());
    $path = UploadedFile::fake()->image('logo.jpg')->store('store-logos', 'public');
    Setting::create(['key' => 'store_logo', 'value' => $path]);
    $this->put(route('settings.store-profile.update'), [
        'store_name' => 'Warung', 'pos_name' => 'Kasir', 'remove_logo' => '1',
    ])->assertSessionHasNoErrors();
    expect(Setting::where('key', 'store_logo')->value('value'))->toBeNull();
    Storage::disk('public')->assertExists($path);
});

test('invalid logo uploads are rejected and cashier cannot change branding', function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->admin()->create());
    $this->put(route('settings.store-profile.update'), [
        'store_name' => 'Warung', 'pos_name' => 'Kasir', 'logo' => UploadedFile::fake()->create('bad.svg', 10, 'image/svg+xml'),
    ])->assertSessionHasErrors('logo');
    $this->actingAs(User::factory()->kasir()->create());
    $this->put(route('settings.store-profile.update'), ['store_name' => 'Peretas', 'pos_name' => 'Kasir'])->assertForbidden();
});
