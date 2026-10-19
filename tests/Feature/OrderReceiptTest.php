<?php

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\CarbonImmutable;

test('the receipt shows stored order snapshots', function () {
    $order = Order::factory()->create([
        'status' => OrderStatus::New,
        'type' => OrderType::DI,
        'table_number' => 'A1',
        'customer_name' => 'Budi',
        'subtotal' => 33000,
        'total' => 33000,
    ]);

    $item = OrderItem::factory()->for($order)->create([
        'product_name' => 'Mi Ayam Spesial',
        'unit_price' => 15000,
        'quantity' => 2,
        'subtotal' => 33000,
    ]);

    $item->toppings()->create([
        'topping_name' => 'Telur Rebus',
        'price' => 3000,
        'quantity' => 1,
    ]);

    $this->get(route('orders.receipt', $order))
        ->assertOk()
        ->assertSee(config('app.name'))
        ->assertSee($order->order_number)
        ->assertSee('Mi Ayam Spesial')
        ->assertSee('Telur Rebus')
        ->assertSee('Rp 15.000')
        ->assertSee('Rp 33.000')
        ->assertSee('Dine-in')
        ->assertSee('Belum dibayar')
        ->assertSee('window.print()', false);
});

test('the receipt uses a standalone print layout without the app navigation', function () {
    $order = Order::factory()->create(['status' => OrderStatus::New]);

    $this->get(route('orders.receipt', $order))
        ->assertOk()
        ->assertDontSee('app-sidebar', false)
        ->assertSee('Cetak');
});

test('receipt times are displayed in Asia/Jakarta', function () {
    $this->travelTo(CarbonImmutable::parse('2026-03-01 16:30:00', 'UTC'));

    $order = Order::factory()->create(['status' => OrderStatus::New]);

    $this->get(route('orders.receipt', $order))
        ->assertOk()
        ->assertSee('01/03/2026 23:30');
});
