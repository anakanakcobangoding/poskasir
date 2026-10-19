<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Topping;

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
