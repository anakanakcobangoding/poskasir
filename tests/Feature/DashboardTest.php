<?php

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Topping;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
    $this->actingAs($this->admin);
});

test('dashboard requires admin role', function () {
    $kasir = User::factory()->create(['role' => UserRole::Kasir, 'is_active' => true]);
    $this->actingAs($kasir);

    $this->get(route('dashboard.index'))->assertForbidden();
});

test('dashboard shows summary stats for paid orders', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id, 'price' => 15000, 'hpp' => 8000]);
    $topping = Topping::factory()->create(['price' => 3000, 'hpp' => 1500]);

    $order = Order::factory()->create([
        'type' => OrderType::TA,
        'status' => OrderStatus::Completed,
        'payment_status' => PaymentStatus::Paid,
        'payment_method' => PaymentMethod::Cash,
        'total' => 18000,
        'paid_at' => now(),
        'created_at' => now(),
    ]);

    $item = $order->items()->create([
        'product_id' => $product->id,
        'product_name' => $product->name,
        'unit_price' => 15000,
        'hpp' => 8000,
        'quantity' => 1,
        'subtotal' => 18000,
    ]);

    $item->toppings()->create([
        'topping_id' => $topping->id,
        'topping_name' => $topping->name,
        'price' => 3000,
        'hpp' => 1500,
        'quantity' => 1,
    ]);

    $response = $this->get(route('dashboard.index'));
    $response->assertOk();
    $response->assertSee('Rp 18.000');
    $response->assertSee('Rp 9.500');
    $response->assertSee('Rp 8.500');
});

test('dashboard excludes unpaid orders from revenue', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id, 'price' => 15000, 'hpp' => 8000]);

    // Paid order
    Order::factory()->create([
        'payment_status' => PaymentStatus::Paid,
        'total' => 15000,
        'paid_at' => now(),
        'created_at' => now(),
    ]);

    // Unpaid order
    Order::factory()->create([
        'payment_status' => PaymentStatus::Unpaid,
        'total' => 20000,
        'paid_at' => null,
        'created_at' => now(),
    ]);

    $response = $this->get(route('dashboard.index'));
    $response->assertOk();
    $response->assertSee('Rp 15.000');
    $response->assertDontSee('Rp 35.000');
});

test('dashboard excludes cancelled orders', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id, 'price' => 15000, 'hpp' => 8000]);

    Order::factory()->create([
        'status' => OrderStatus::Cancelled,
        'payment_status' => PaymentStatus::Paid,
        'total' => 15000,
        'paid_at' => now(),
        'created_at' => now(),
    ]);

    $response = $this->get(route('dashboard.index'));
    $response->assertOk();
    $response->assertSee('Rp 0');
});

test('dashboard filters by period 7 and 30 days', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id, 'price' => 15000, 'hpp' => 8000]);

    // Order 5 days ago
    Order::factory()->create([
        'payment_status' => PaymentStatus::Paid,
        'total' => 15000,
        'paid_at' => now()->subDays(5),
        'created_at' => now()->subDays(5),
    ]);

    // Order 15 days ago
    Order::factory()->create([
        'payment_status' => PaymentStatus::Paid,
        'total' => 20000,
        'paid_at' => now()->subDays(15),
        'created_at' => now()->subDays(15),
    ]);

    // 7-day period should only show 5-day-old order
    $response = $this->get(route('dashboard.index', ['period' => '7']));
    $response->assertOk();
    $response->assertSee('Rp 15.000');
    $response->assertDontSee('Rp 35.000');

    // 30-day period should show both
    $response = $this->get(route('dashboard.index', ['period' => '30']));
    $response->assertOk();
    $response->assertSee('Rp 35.000');
});

test('dashboard separates cash and qris payments', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id, 'price' => 15000, 'hpp' => 8000]);

    Order::factory()->create([
        'payment_status' => PaymentStatus::Paid,
        'payment_method' => PaymentMethod::Cash,
        'total' => 15000,
        'paid_at' => now(),
        'created_at' => now(),
    ]);

    Order::factory()->create([
        'payment_status' => PaymentStatus::Paid,
        'payment_method' => PaymentMethod::Qris,
        'total' => 20000,
        'paid_at' => now(),
        'created_at' => now(),
    ]);

    $response = $this->get(route('dashboard.index'));
    $response->assertOk();
    $response->assertSee('Tunai');
    $response->assertSee('QRIS');
    $response->assertSee('Rp 15.000');
    $response->assertSee('Rp 20.000');
});

test('dashboard calculates hpp for products and toppings', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id, 'price' => 15000, 'hpp' => 8000]);
    $topping = Topping::factory()->create(['price' => 3000, 'hpp' => 1500]);

    $order = Order::factory()->create([
        'payment_status' => PaymentStatus::Paid,
        'total' => 18000,
        'paid_at' => now(),
        'created_at' => now(),
    ]);

    $item = $order->items()->create([
        'product_id' => $product->id,
        'product_name' => $product->name,
        'unit_price' => 15000,
        'hpp' => 8000,
        'quantity' => 2,
        'subtotal' => 36000,
    ]);

    $item->toppings()->create([
        'topping_id' => $topping->id,
        'topping_name' => $topping->name,
        'price' => 3000,
        'hpp' => 1500,
        'quantity' => 2,
    ]);

    $response = $this->get(route('dashboard.index'));
    $response->assertOk();

    // Product HPP: 8000 * 2 = 16000
    // Topping HPP: 1500 * 2 toppings * 2 products = 6000
    // Total HPP: 22000
    $response->assertSee('Rp 22.000');
});

test('dashboard shows incomplete hpp warning when products lack hpp', function () {
    $category = Category::factory()->create();
    $productWithHpp = Product::factory()->create(['category_id' => $category->id, 'price' => 15000, 'hpp' => 8000]);
    $productWithoutHpp = Product::factory()->create(['category_id' => $category->id, 'price' => 20000, 'hpp' => null]);

    Order::factory()->create([
        'payment_status' => PaymentStatus::Paid,
        'total' => 15000,
        'paid_at' => now(),
        'created_at' => now(),
    ])->items()->create([
        'product_id' => $productWithHpp->id,
        'product_name' => $productWithHpp->name,
        'unit_price' => 15000,
        'hpp' => 8000,
        'quantity' => 1,
        'subtotal' => 15000,
    ]);

    Order::factory()->create([
        'payment_status' => PaymentStatus::Paid,
        'total' => 20000,
        'paid_at' => now(),
        'created_at' => now(),
    ])->items()->create([
        'product_id' => $productWithoutHpp->id,
        'product_name' => $productWithoutHpp->name,
        'unit_price' => 20000,
        'hpp' => null,
        'quantity' => 1,
        'subtotal' => 20000,
    ]);

    $response = $this->get(route('dashboard.index'));
    $response->assertOk();
    $response->assertSee('Perhitungan Laba Belum Lengkap');
    $response->assertSee('Belum lengkap');
});

test('dashboard calculates gross profit and margin correctly', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id, 'price' => 15000, 'hpp' => 8000]);

    Order::factory()->create([
        'payment_status' => PaymentStatus::Paid,
        'total' => 15000,
        'paid_at' => now(),
        'created_at' => now(),
    ])->items()->create([
        'product_id' => $product->id,
        'product_name' => $product->name,
        'unit_price' => 15000,
        'hpp' => 8000,
        'quantity' => 1,
        'subtotal' => 15000,
    ]);

    $response = $this->get(route('dashboard.index'));
    $response->assertOk();

    // Revenue: 15000, HPP: 8000, Profit: 7000, Margin: 46.7%
    $response->assertSee('Rp 7.000');
    $response->assertSee('46,7%');
});

test('dashboard shows top products with hpp and profit', function () {
    $category = Category::factory()->create();
    $product1 = Product::factory()->create(['category_id' => $category->id, 'name' => 'Produk A', 'price' => 15000, 'hpp' => 8000]);
    $product2 = Product::factory()->create(['category_id' => $category->id, 'name' => 'Produk B', 'price' => 20000, 'hpp' => 10000]);

    $order = Order::factory()->create([
        'payment_status' => PaymentStatus::Paid,
        'total' => 35000,
        'paid_at' => now(),
        'created_at' => now(),
    ]);

    $order->items()->create([
        'product_id' => $product1->id,
        'product_name' => $product1->name,
        'unit_price' => 15000,
        'hpp' => 8000,
        'quantity' => 1,
        'subtotal' => 15000,
    ]);

    $order->items()->create([
        'product_id' => $product2->id,
        'product_name' => $product2->name,
        'unit_price' => 20000,
        'hpp' => 10000,
        'quantity' => 1,
        'subtotal' => 20000,
    ]);

    $response = $this->get(route('dashboard.index'));
    $response->assertOk();
    $response->assertSee('Produk A');
    $response->assertSee('Produk B');
    $response->assertSee('Rp 8.000');
    $response->assertSee('Rp 10.000');
});

test('dashboard shows recent transactions', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id, 'price' => 15000, 'hpp' => 8000]);

    $order = Order::factory()->create([
        'payment_status' => PaymentStatus::Paid,
        'total' => 15000,
        'paid_at' => now(),
        'created_at' => now(),
        'order_number' => 'WC-20261020-001',
    ]);

    $response = $this->get(route('dashboard.index'));
    $response->assertOk();
    $response->assertSee('WC-20261020-001');
    $response->assertSee('Tunai');
});

test('historical hpp snapshot is preserved when product hpp changes', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id, 'price' => 15000, 'hpp' => 8000]);

    // Create order with HPP 8000
    $order = Order::factory()->create([
        'payment_status' => PaymentStatus::Paid,
        'total' => 15000,
        'paid_at' => now()->subDays(2),
        'created_at' => now()->subDays(2),
    ]);

    $item = $order->items()->create([
        'product_id' => $product->id,
        'product_name' => $product->name,
        'unit_price' => 15000,
        'hpp' => 8000, // Historical HPP
        'quantity' => 1,
        'subtotal' => 15000,
    ]);

    // Update product HPP to 9000
    $product->update(['hpp' => 9000]);

    // Dashboard should use historical HPP (8000) not current (9000)
    $response = $this->get(route('dashboard.index', ['period' => '7']));
    $response->assertOk();
    $response->assertSee('Rp 8.000'); // Historical HPP
    $response->assertDontSee('Rp 9.000'); // Not current HPP
});

test('dashboard handles empty data gracefully', function () {
    $response = $this->get(route('dashboard.index'));
    $response->assertOk();
    $response->assertSee('Rp 0');
    $response->assertSee('Belum ada data penjualan');
});

test('product form includes hpp field', function () {
    $category = Category::factory()->create();

    $response = $this->get(route('products.create'));
    $response->assertOk();
    $response->assertSee('HPP (Harga Pokok Penjualan)');
    $response->assertSee('Opsional');
});

test('topping form includes hpp field', function () {
    $response = $this->get(route('toppings.create'));
    $response->assertOk();
    $response->assertSee('HPP (Harga Pokok Penjualan)');
    $response->assertSee('Opsional');
});

test('product can be created with hpp', function () {
    $category = Category::factory()->create();

    $this->post(route('products.store'), [
        'category_id' => $category->id,
        'name' => 'Mi Ayam',
        'price' => '15000',
        'hpp' => '8000',
        'is_available' => '1',
    ])->assertRedirect(route('products.index'));

    $this->assertDatabaseHas('products', [
        'name' => 'Mi Ayam',
        'price' => 15000,
        'hpp' => 8000,
    ]);
});

test('topping can be created with hpp', function () {
    $this->post(route('toppings.store'), [
        'name' => 'Telur Rebus',
        'price' => '3000',
        'hpp' => '1500',
        'is_available' => '1',
    ])->assertRedirect(route('toppings.index'));

    $this->assertDatabaseHas('toppings', [
        'name' => 'Telur Rebus',
        'price' => 3000,
        'hpp' => 1500,
    ]);
});

test('product hpp validation rejects negative values', function () {
    $category = Category::factory()->create();

    $this->post(route('products.store'), [
        'category_id' => $category->id,
        'name' => 'Mi Ayam',
        'price' => '15000',
        'hpp' => '-1000',
        'is_available' => '1',
    ])->assertSessionHasErrors('hpp');

    $this->assertDatabaseCount('products', 0);
});

test('topping hpp validation rejects negative values', function () {
    $this->post(route('toppings.store'), [
        'name' => 'Telur Rebus',
        'price' => '3000',
        'hpp' => '-500',
        'is_available' => '1',
    ])->assertSessionHasErrors('hpp');

    $this->assertDatabaseCount('toppings', 0);
});
