<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Topping;

test('independent topping quantities persist with calculated totals and receipt branding', function () {
    actingAsUser();
    $product = Product::factory()->create(['price' => 10000]);
    $egg = Topping::factory()->create(['name' => 'Telur', 'price' => 3000]);
    $cheese = Topping::factory()->create(['name' => 'Keju', 'price' => 2000]);
    $product->toppings()->attach([$egg->id, $cheese->id]);
    Setting::create(['key' => 'store_name', 'value' => 'Warung Terbaru']);

    $this->postJson(route('orders.store'), [
        'type' => 'TA', 'items' => [[
            'product_id' => $product->id, 'quantity' => 2,
            'toppings' => [['id' => $egg->id, 'quantity' => 3], ['id' => $cheese->id, 'quantity' => 2]],
        ]],
    ])->assertCreated();

    $order = Order::sole();
    expect($order->total)->toBe('46000.00');
    $this->assertDatabaseHas('order_item_toppings', ['topping_id' => $egg->id, 'quantity' => 3, 'price' => 3000]);
    $this->assertDatabaseHas('order_item_toppings', ['topping_id' => $cheese->id, 'quantity' => 2, 'price' => 2000]);
    foreach (['orders.show', 'orders.receipt'] as $route) {
        $this->get(route($route, $order))->assertOk()->assertSee('Telur x3 per produk')->assertSee('Keju x2 per produk')->assertSee('Rp 46.000')->assertSee('Rp 18.000')->assertSee('Warung Terbaru');
    }
});

test('the same topping can have independent quantities on different cart lines', function () {
    actingAsUser();
    $product = Product::factory()->create(['price' => 10000]);
    $topping = Topping::factory()->create(['price' => 2000]);
    $product->toppings()->attach($topping);

    $this->postJson(route('orders.store'), [
        'type' => 'TA', 'items' => [
            ['product_id' => $product->id, 'quantity' => 1, 'toppings' => [['id' => $topping->id, 'quantity' => 2]]],
            ['product_id' => $product->id, 'quantity' => 1, 'toppings' => [['id' => $topping->id, 'quantity' => 3]]],
        ],
    ])->assertCreated();

    expect(Order::sole()->total)->toBe('30000.00');
    $this->assertDatabaseHas('order_item_toppings', ['topping_id' => $topping->id, 'quantity' => 2]);
    $this->assertDatabaseHas('order_item_toppings', ['topping_id' => $topping->id, 'quantity' => 3]);
});

test('a cash transaction with multiple products keeps independent topping quantities after reopening', function () {
    actingAsUser();
    $first = Product::factory()->create(['name' => 'Produk Satu', 'price' => 10000]);
    $second = Product::factory()->create(['name' => 'Produk Dua', 'price' => 15000]);
    $egg = Topping::factory()->create(['name' => 'Telur', 'price' => 3000]);
    $cheese = Topping::factory()->create(['name' => 'Keju', 'price' => 2000]);
    $first->toppings()->attach([$egg->id, $cheese->id]);
    $second->toppings()->attach([$egg->id, $cheese->id]);

    $response = $this->postJson(route('orders.store'), [
        'type' => 'TA', 'payment_status' => 'paid', 'payment_method' => 'cash', 'payment_confirmed' => true, 'amount_paid' => 100000,
        'items' => [
            ['product_id' => $first->id, 'quantity' => 2, 'toppings' => [['id' => $egg->id, 'quantity' => 3], ['id' => $cheese->id, 'quantity' => 2]]],
            ['product_id' => $second->id, 'quantity' => 1, 'toppings' => [['id' => $egg->id, 'quantity' => 1], ['id' => $cheese->id, 'quantity' => 4]]],
        ],
    ])->assertCreated();

    $order = Order::findOrFail($response->json('id'));
    expect($order->total)->toBe('72000.00');
    expect($order->change)->toBe('28000.00');
    foreach (['orders.show', 'orders.receipt'] as $route) {
        $this->get(route($route, $order))->assertOk()->assertSee('Telur x3 per produk')->assertSee('Keju x2 per produk')->assertSee('Telur x1 per produk')->assertSee('Keju x4 per produk')->assertSee('Rp 72.000');
    }
    expect($response->json('receipt_url'))->toBe(route('orders.receipt', $order, absolute: false));
    expect($order->items()->where('product_id', $first->id)->sole()->toppings()->where('topping_id', $egg->id)->sole()->quantity)->toBe(3);
});
