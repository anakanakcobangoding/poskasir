<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Topping;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    actingAsUser();
});

test('the cashier page shows only active categories and available products', function () {
    $activeCategory = Category::factory()->create(['name' => 'Mi Ayam']);
    Product::factory()->for($activeCategory)->create(['name' => 'Mi Ayam Spesial', 'price' => 15000]);

    $inactiveCategory = Category::factory()->inactive()->create(['name' => 'Menu Lama']);
    Product::factory()->for($inactiveCategory)->create(['name' => 'Menu Lama Nonaktif']);

    Product::factory()->for($activeCategory)->unavailable()->create(['name' => 'Produk Habis']);

    $this->get(route('pos.index'))
        ->assertOk()
        ->assertSee('Mi Ayam Spesial')
        ->assertSee('Rp 15.000')
        ->assertDontSee('Menu Lama Nonaktif')
        ->assertDontSee('Produk Habis');
});

test('the cashier page lists available toppings for the cart script', function () {
    Category::factory()->create(['name' => 'Mi Ayam']);
    Topping::factory()->create(['name' => 'Telur Rebus']);
    Topping::factory()->unavailable()->create(['name' => 'Topping Habis']);

    $this->get(route('pos.index'))
        ->assertOk()
        ->assertSee('posToppings')
        ->assertSee('Telur Rebus')
        ->assertDontSee('Topping Habis');
});

test('the cashier page renders with an empty menu', function () {
    $this->get(route('pos.index'))
        ->assertOk()
        ->assertSee('Keranjang kosong');
});

test('the cashier page renders the checkout modal with the right buttons', function () {
    $this->get(route('pos.index'))
        ->assertOk()
        ->assertSee('id="pos-checkout"', false)
        ->assertSee('Proses Transaksi');
});

test('the checkout modal keeps the checkout field selectors the script relies on', function () {
    $response = $this->get(route('pos.index'))->assertOk();

    foreach ([
        'id="pos-checkout"',
        'id="pos-checkout-step1"',
        'id="pos-checkout-step2"',
        'id="pos-checkout-step3"',
        'id="pos-checkout-qris"',
        'id="pos-order-form"',
    ] as $selector) {
        $response->assertSee($selector, false);
    }
});

test('the checkout modal keeps all three order types', function () {
    $response = $this->get(route('pos.index'))->assertOk();

    foreach (['DI', 'TA', 'DLV'] as $type) {
        $response->assertSee($type, false);
    }

    $response->assertSee('Dine In')
        ->assertSee('Takeaway')
        ->assertSee('Delivery');
});

test('the cashier page shows a configuration hint when QRIS image is not set', function () {
    $this->get(route('pos.index'))
        ->assertOk()
        ->assertSee('Gambar QRIS belum diatur')
        ->assertDontSee('id="pos-qris-image"', false);
});

test('the cashier page renders the uploaded QRIS image on the current host', function () {
    config(['filesystems.disks.public.url' => 'http://localhost:8000/storage']);
    Storage::fake('public');
    Storage::disk('public')->put('qris/qris.png', 'fake-image');
    Setting::create(['key' => 'qris_image', 'value' => 'qris/qris.png']);

    $this->get(route('pos.index'))
        ->assertOk()
        ->assertSee('id="pos-qris-image"', false)
        ->assertSee('src="/pos/qris-image"', false)
        ->assertDontSee('http://localhost:8000/storage', false)
        ->assertDontSee('Gambar QRIS belum diatur');
});

test('the checkout modal exposes the qris total and the payment confirmation control', function () {
    $response = $this->get(route('pos.index'))->assertOk();

    foreach ([
        'id="pos-qris-final-total"',
        'id="pos-qris-confirmed"',
    ] as $selector) {
        $response->assertSee($selector, false);
    }

    $response->assertSee('TOTAL PEMBAYARAN');
});

test('the cashier page ignores a QRIS setting that points at a missing file', function () {
    Storage::fake('public');
    Setting::create(['key' => 'qris_image', 'value' => 'qris/missing.png']);

    $this->get(route('pos.index'))
        ->assertOk()
        ->assertSee('Gambar QRIS belum diatur')
        ->assertDontSee('id="pos-qris-image"', false);
});
