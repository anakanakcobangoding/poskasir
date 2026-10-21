<?php

use App\Enums\OrderStatus;
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

test('report page requires admin role', function () {
    $kasir = User::factory()->create(['role' => UserRole::Kasir, 'is_active' => true]);
    $this->actingAs($kasir);

    $this->get(route('reports.index'))->assertForbidden();
});

test('report page loads with default today period', function () {
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

    $response = $this->get(route('reports.index'));
    $response->assertOk();
    $response->assertSee('Laporan Penjualan');
    $response->assertSee('Rp 15.000');
});

test('report filters by period today', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id, 'price' => 15000, 'hpp' => 8000]);

    // Order today
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

    // Order yesterday
    Order::factory()->create([
        'payment_status' => PaymentStatus::Paid,
        'total' => 20000,
        'paid_at' => now()->subDay(),
        'created_at' => now()->subDay(),
    ])->items()->create([
        'product_id' => $product->id,
        'product_name' => $product->name,
        'unit_price' => 20000,
        'hpp' => 8000,
        'quantity' => 1,
        'subtotal' => 20000,
    ]);

    $response = $this->get(route('reports.index', ['period' => 'today']));
    $response->assertOk();
    $response->assertSee('Rp 15.000');
    $response->assertDontSee('Rp 35.000');
});

test('report filters by period yesterday', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id, 'price' => 15000, 'hpp' => 8000]);

    // Order today
    Order::factory()->create([
        'payment_status' => PaymentStatus::Paid,
        'total' => 15000,
        'paid_at' => now(),
        'created_at' => now(),
    ]);

    // Order yesterday
    Order::factory()->create([
        'payment_status' => PaymentStatus::Paid,
        'total' => 20000,
        'paid_at' => now()->subDay(),
        'created_at' => now()->subDay(),
    ]);

    $response = $this->get(route('reports.index', ['period' => 'yesterday']));
    $response->assertOk();
    $response->assertSee('Rp 20.000');
    $response->assertDontSee('Rp 35.000');
});

test('report filters by period 7days', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id, 'price' => 15000, 'hpp' => 8000]);

    Order::factory()->create([
        'payment_status' => PaymentStatus::Paid,
        'total' => 15000,
        'paid_at' => now()->subDays(3),
        'created_at' => now()->subDays(3),
    ]);

    Order::factory()->create([
        'payment_status' => PaymentStatus::Paid,
        'total' => 20000,
        'paid_at' => now()->subDays(10),
        'created_at' => now()->subDays(10),
    ]);

    $response = $this->get(route('reports.index', ['period' => '7days']));
    $response->assertOk();
    $response->assertSee('Rp 15.000');
    $response->assertDontSee('Rp 35.000');
});

test('report filters by period 30days', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id, 'price' => 15000, 'hpp' => 8000]);

    Order::factory()->create([
        'payment_status' => PaymentStatus::Paid,
        'total' => 15000,
        'paid_at' => now()->subDays(10),
        'created_at' => now()->subDays(10),
    ]);

    Order::factory()->create([
        'payment_status' => PaymentStatus::Paid,
        'total' => 20000,
        'paid_at' => now()->subDays(40),
        'created_at' => now()->subDays(40),
    ]);

    $response = $this->get(route('reports.index', ['period' => '30days']));
    $response->assertOk();
    $response->assertSee('Rp 15.000');
    $response->assertDontSee('Rp 35.000');
});

test('report filters by period this_month', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id, 'price' => 15000, 'hpp' => 8000]);

    Order::factory()->create([
        'payment_status' => PaymentStatus::Paid,
        'total' => 15000,
        'paid_at' => now()->startOfMonth()->addDays(2),
        'created_at' => now()->startOfMonth()->addDays(2),
    ]);

    Order::factory()->create([
        'payment_status' => PaymentStatus::Paid,
        'total' => 20000,
        'paid_at' => now()->subMonth()->startOfMonth()->addDays(2),
        'created_at' => now()->subMonth()->startOfMonth()->addDays(2),
    ]);

    $response = $this->get(route('reports.index', ['period' => 'this_month']));
    $response->assertOk();
    $response->assertSee('Rp 15.000');
    $response->assertDontSee('Rp 35.000');
});

test('report filters by custom date range', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id, 'price' => 15000, 'hpp' => 8000]);

    $today = now()->format('Y-m-d');
    $yesterday = now()->subDay()->format('Y-m-d');

    Order::factory()->create([
        'payment_status' => PaymentStatus::Paid,
        'total' => 15000,
        'paid_at' => now(),
        'created_at' => now(),
    ]);

    Order::factory()->create([
        'payment_status' => PaymentStatus::Paid,
        'total' => 20000,
        'paid_at' => now()->subDay(),
        'created_at' => now()->subDay(),
    ]);

    $response = $this->get(route('reports.index', [
        'period' => 'custom',
        'start_date' => $yesterday,
        'end_date' => $yesterday,
    ]));
    $response->assertOk();
    $response->assertSee('Rp 20.000');
    $response->assertDontSee('Rp 35.000');
});

test('report excludes unpaid orders from revenue', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id, 'price' => 15000, 'hpp' => 8000]);

    Order::factory()->create([
        'payment_status' => PaymentStatus::Paid,
        'total' => 15000,
        'paid_at' => now(),
        'created_at' => now(),
    ]);

    Order::factory()->create([
        'payment_status' => PaymentStatus::Unpaid,
        'total' => 20000,
        'paid_at' => null,
        'created_at' => now(),
    ]);

    $response = $this->get(route('reports.index'));
    $response->assertOk();
    $response->assertSee('Rp 15.000');
    $response->assertDontSee('Rp 35.000');
});

test('report excludes cancelled orders from revenue', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id, 'price' => 15000, 'hpp' => 8000]);

    Order::factory()->create([
        'status' => OrderStatus::Cancelled,
        'payment_status' => PaymentStatus::Paid,
        'total' => 15000,
        'paid_at' => now(),
        'created_at' => now(),
    ]);

    $response = $this->get(route('reports.index'));
    $response->assertOk();
    $response->assertSee('Rp 0');
});

test('report calculates total transactions and average', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id, 'price' => 15000, 'hpp' => 8000]);

    Order::factory()->create([
        'payment_status' => PaymentStatus::Paid,
        'total' => 15000,
        'paid_at' => now(),
        'created_at' => now(),
    ]);

    Order::factory()->create([
        'payment_status' => PaymentStatus::Paid,
        'total' => 25000,
        'paid_at' => now(),
        'created_at' => now(),
    ]);

    $response = $this->get(route('reports.index'));
    $response->assertOk();
    $response->assertSee('2'); // 2 transactions
    $response->assertSee('Rp 20.000'); // average (40000/2)
});

test('report calculates hpp for products and toppings from snapshot', function () {
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

    $response = $this->get(route('reports.index'));
    $response->assertOk();

    // Product HPP: 8000 * 2 = 16000
    // Topping HPP: 1500 * 2 toppings * 2 products = 6000
    // Total HPP: 22000
    $response->assertSee('Rp 22.000');
});

test('report shows incomplete hpp warning when items lack hpp snapshot', function () {
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

    $response = $this->get(route('reports.index'));
    $response->assertOk();
    $response->assertSee('Perhitungan Laba Belum Lengkap');
    $response->assertSee('Belum lengkap');
});

test('report calculates gross profit and margin correctly', function () {
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

    $response = $this->get(route('reports.index'));
    $response->assertOk();

    // Revenue: 15000, HPP: 8000, Profit: 7000, Margin: 46.7%
    $response->assertSee('Rp 7.000');
    $response->assertSee('46,7%');
});

test('report shows product sales detail with hpp and profit', function () {
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

    $response = $this->get(route('reports.index'));
    $response->assertOk();
    $response->assertSee('Produk A');
    $response->assertSee('Produk B');
    $response->assertSee('Rp 8.000');
    $response->assertSee('Rp 10.000');
});

test('report shows topping sales detail with hpp and profit', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id, 'price' => 15000, 'hpp' => 8000]);
    $topping1 = Topping::factory()->create(['name' => 'Topping A', 'price' => 3000, 'hpp' => 1500]);
    $topping2 = Topping::factory()->create(['name' => 'Topping B', 'price' => 4000, 'hpp' => 2000]);

    $order = Order::factory()->create([
        'payment_status' => PaymentStatus::Paid,
        'total' => 22000,
        'paid_at' => now(),
        'created_at' => now(),
    ]);

    $item = $order->items()->create([
        'product_id' => $product->id,
        'product_name' => $product->name,
        'unit_price' => 15000,
        'hpp' => 8000,
        'quantity' => 1,
        'subtotal' => 15000,
    ]);

    $item->toppings()->create([
        'topping_id' => $topping1->id,
        'topping_name' => $topping1->name,
        'price' => 3000,
        'hpp' => 1500,
        'quantity' => 1,
    ]);

    $item->toppings()->create([
        'topping_id' => $topping2->id,
        'topping_name' => $topping2->name,
        'price' => 4000,
        'hpp' => 2000,
        'quantity' => 1,
    ]);

    $response = $this->get(route('reports.index'));
    $response->assertOk();
    $response->assertSee('Topping A');
    $response->assertSee('Topping B');
    $response->assertSee('Rp 1.500');
    $response->assertSee('Rp 2.000');
});

test('report does not count toppings that were not selected', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id, 'price' => 15000, 'hpp' => 8000]);
    $toppingSelected = Topping::factory()->create(['name' => 'Dipilih', 'price' => 3000, 'hpp' => 1500]);
    $toppingNotSelected = Topping::factory()->create(['name' => 'Tidak Dipilih', 'price' => 4000, 'hpp' => 2000]);

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
        'quantity' => 1,
        'subtotal' => 15000,
    ]);

    $item->toppings()->create([
        'topping_id' => $toppingSelected->id,
        'topping_name' => $toppingSelected->name,
        'price' => 3000,
        'hpp' => 1500,
        'quantity' => 1,
    ]);

    $response = $this->get(route('reports.index'));
    $response->assertOk();
    $response->assertSee('Dipilih');
    $response->assertDontSee('Tidak Dipilih');
});

test('report separates cash and qris payments', function () {
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

    $response = $this->get(route('reports.index'));
    $response->assertOk();
    $response->assertSee('Tunai');
    $response->assertSee('QRIS');
    $response->assertSee('Rp 15.000');
    $response->assertSee('Rp 20.000');
});

test('report shows transaction history with pagination', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id, 'price' => 15000, 'hpp' => 8000]);

    for ($i = 1; $i <= 20; $i++) {
        Order::factory()->create([
            'payment_status' => PaymentStatus::Paid,
            'total' => 15000,
            'paid_at' => now()->subMinutes($i),
            'created_at' => now()->subMinutes($i),
            'order_number' => 'WC-TEST-'.str_pad($i, 3, '0', STR_PAD_LEFT),
        ])->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => 15000,
            'hpp' => 8000,
            'quantity' => 1,
            'subtotal' => 15000,
        ]);
    }

    $response = $this->get(route('reports.index'));
    $response->assertOk();
    $response->assertSee('WC-TEST-001');
    $response->assertSee('WC-TEST-015'); // 15 per page
    $response->assertDontSee('WC-TEST-016');
});

test('report supports search by order number', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id, 'price' => 15000, 'hpp' => 8000]);

    Order::factory()->create([
        'payment_status' => PaymentStatus::Paid,
        'total' => 15000,
        'paid_at' => now(),
        'created_at' => now(),
        'order_number' => 'WC-SEARCH-001',
    ]);

    Order::factory()->create([
        'payment_status' => PaymentStatus::Paid,
        'total' => 20000,
        'paid_at' => now(),
        'created_at' => now(),
        'order_number' => 'WC-OTHER-002',
    ]);

    $response = $this->get(route('reports.index', ['search' => 'SEARCH']));
    $response->assertOk();
    $response->assertSee('WC-SEARCH-001');
    $response->assertDontSee('WC-OTHER-002');
});

test('report supports filter by payment method', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id, 'price' => 15000, 'hpp' => 8000]);

    Order::factory()->create([
        'payment_status' => PaymentStatus::Paid,
        'payment_method' => PaymentMethod::Cash,
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

    Order::factory()->create([
        'payment_status' => PaymentStatus::Paid,
        'payment_method' => PaymentMethod::Qris,
        'total' => 20000,
        'paid_at' => now(),
        'created_at' => now(),
    ])->items()->create([
        'product_id' => $product->id,
        'product_name' => $product->name,
        'unit_price' => 20000,
        'hpp' => 8000,
        'quantity' => 1,
        'subtotal' => 20000,
    ]);

    $response = $this->get(route('reports.index', ['payment_method' => 'cash']));
    $response->assertOk();
    $response->assertSee('Rp 15.000');
    $response->assertDontSee('Rp 20.000');
});

test('report export csv returns csv response', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id, 'price' => 15000, 'hpp' => 8000]);

    Order::factory()->create([
        'payment_status' => PaymentStatus::Paid,
        'total' => 15000,
        'paid_at' => now(),
        'created_at' => now(),
        'order_number' => 'WC-EXPORT-001',
    ])->items()->create([
        'product_id' => $product->id,
        'product_name' => $product->name,
        'unit_price' => 15000,
        'hpp' => 8000,
        'quantity' => 1,
        'subtotal' => 15000,
    ]);

    Order::factory()->create([
        'payment_status' => PaymentStatus::Unpaid,
        'total' => 20000,
        'paid_at' => null,
        'created_at' => now(),
        'order_number' => 'WC-EXPORT-002',
    ]);

    $response = $this->get(route('reports.export'));
    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    $response->assertHeader('Content-Disposition', 'attachment; filename="laporan-penjualan-today-'.now()->format('Ymd').'-'.now()->format('Ymd').'.csv"');
});

test('report export csv respects period filter in headers', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id, 'price' => 15000, 'hpp' => 8000]);

    Order::factory()->create([
        'payment_status' => PaymentStatus::Paid,
        'total' => 15000,
        'paid_at' => now(),
        'created_at' => now(),
        'order_number' => 'WC-TODAY-001',
    ])->items()->create([
        'product_id' => $product->id,
        'product_name' => $product->name,
        'unit_price' => 15000,
        'hpp' => 8000,
        'quantity' => 1,
        'subtotal' => 15000,
    ]);

    Order::factory()->create([
        'payment_status' => PaymentStatus::Paid,
        'total' => 20000,
        'paid_at' => now()->subDay(),
        'created_at' => now()->subDay(),
        'order_number' => 'WC-YESTERDAY-002',
    ])->items()->create([
        'product_id' => $product->id,
        'product_name' => $product->name,
        'unit_price' => 20000,
        'hpp' => 8000,
        'quantity' => 1,
        'subtotal' => 20000,
    ]);

    $response = $this->get(route('reports.export', ['period' => 'today']));
    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    $response->assertHeader('Content-Disposition', 'attachment; filename="laporan-penjualan-today-'.now()->format('Ymd').'-'.now()->format('Ymd').'.csv"');
});

test('report handles empty data gracefully', function () {
    $response = $this->get(route('reports.index'));
    $response->assertOk();
    $response->assertSee('Rp 0');
    $response->assertSee('Tidak ada transaksi pada filter ini');
});

test('report margin handles zero revenue', function () {
    $response = $this->get(route('reports.index'));
    $response->assertOk();
    $response->assertSee('0%');
});

test('historical hpp snapshot is preserved when product hpp changes', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id, 'price' => 15000, 'hpp' => 8000]);

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
        'hpp' => 8000,
        'quantity' => 1,
        'subtotal' => 15000,
    ]);

    $product->update(['hpp' => 9000]);

    $response = $this->get(route('reports.index', ['period' => '7days']));
    $response->assertOk();
    $response->assertSee('Rp 8.000'); // Historical HPP
    $response->assertDontSee('Rp 9.000'); // Not current HPP
});
