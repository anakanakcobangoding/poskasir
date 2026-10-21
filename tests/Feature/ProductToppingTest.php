<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemTopping;
use App\Models\Product;
use App\Models\Topping;

beforeEach(function () {
    actingAsUser();
});

test('the cashier payload links each product to its allowed toppings only', function () {
    $allowed = Topping::factory()->create(['name' => 'Telur Rebus', 'price' => 4000]);
    $toppingForA = Topping::factory()->create(['name' => 'Ceker Extra', 'price' => 5000]);
    $unavailable = Topping::factory()->unavailable()->create(['name' => 'Sawi Habis', 'price' => 2000]);

    $productA = Product::factory()->create();
    $productB = Product::factory()->create();

    $productA->toppings()->attach([$allowed->id, $unavailable->id]);
    $productB->toppings()->attach($toppingForA->id);

    $response = $this->get(route('pos.index'))->assertOk();

    $response->assertSee('posProductToppings');

    expect(preg_match('/window\.posProductToppings\s*=\s*(\{.*?\});/s', $response->getContent(), $matches))->toBe(1);

    $mapping = json_decode($matches[1], true);

    expect($mapping[(string) $productA->id])->toBe([$allowed->id])
        ->and($mapping[(string) $productB->id])->toBe([$toppingForA->id])
        ->and($mapping[(string) $productB->id])->not->toContain($allowed->id);
});

test('the cashier page marks only products with available toppings', function () {
    $withToppings = Product::factory()->create(['name' => 'Mi Ayam Spesial']);
    $unavailableOnly = Product::factory()->create(['name' => 'Es Teh Polos']);
    $topping = Topping::factory()->create(['name' => 'Telur Rebus', 'price' => 4000]);
    $unavailable = Topping::factory()->unavailable()->create(['name' => 'Topping Habis']);

    $withToppings->toppings()->attach($topping->id);
    $unavailableOnly->toppings()->attach($unavailable->id);

    $response = $this->get(route('pos.index'))->assertOk();

    expect(substr_count($response->getContent(), 'bisa + topping'))->toBe(1);
});

test('a product only accepts toppings linked to it', function () {
    $allowed = Topping::factory()->create(['price' => 4000]);
    $product = Product::factory()->create(['price' => 10000]);
    $product->toppings()->attach($allowed->id);

    $this->postJson(route('orders.store'), [
        'type' => 'TA',
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 1,
            'toppings' => [['id' => $allowed->id, 'quantity' => 1]],
        ]],
    ])->assertCreated();

    $order = Order::sole();

    expect($order->total)->toBe('14000.00')
        ->and($order->items()->sole()->toppings()->sole()->topping_id)->toBe($allowed->id);
});

test('a product cannot use a topping that is only allowed for another product', function () {
    $toppingForA = Topping::factory()->create(['price' => 4000]);
    $productA = Product::factory()->create(['price' => 10000]);
    $productB = Product::factory()->create(['price' => 12000]);
    $productA->toppings()->attach($toppingForA->id);

    $this->postJson(route('orders.store'), [
        'type' => 'TA',
        'items' => [[
            'product_id' => $productB->id,
            'quantity' => 1,
            'toppings' => [['id' => $toppingForA->id, 'quantity' => 1]],
        ]],
    ])->assertStatus(422)->assertJsonValidationErrors('items');

    $this->assertDatabaseCount('orders', 0);
    $this->assertDatabaseCount('order_items', 0);
});

test('an unlinked topping id is rejected even when it exists', function () {
    $topping = Topping::factory()->create(['price' => 4000]);
    $product = Product::factory()->create(['price' => 10000]);

    $this->postJson(route('orders.store'), [
        'type' => 'TA',
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 1,
            'toppings' => [['id' => $topping->id, 'quantity' => 1]],
        ]],
    ])->assertStatus(422)->assertJsonValidationErrors('items');

    $this->assertDatabaseCount('orders', 0);
});

test('topping quantity multiplies the item and order totals', function () {
    $topping = Topping::factory()->create(['price' => 3000]);
    $product = Product::factory()->create(['price' => 10000]);
    $product->toppings()->attach($topping->id);

    $this->postJson(route('orders.store'), [
        'type' => 'TA',
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 2,
            'toppings' => [['id' => $topping->id, 'quantity' => 3]],
        ]],
    ])->assertCreated();

    $order = Order::sole();
    $item = $order->items()->sole();
    $itemTopping = $item->toppings()->sole();

    // (10000 + 3000 * 3) * 2 = 38000
    expect($item->unit_price)->toBe('10000.00')
        ->and($itemTopping->price)->toBe('3000.00')
        ->and($itemTopping->quantity)->toBe(3)
        ->and($item->subtotal)->toBe('38000.00')
        ->and($order->subtotal)->toBe('38000.00')
        ->and($order->total)->toBe('38000.00');
});

test('topping quantity of zero, negative, fractional, or above the limit is rejected', function () {
    $topping = Topping::factory()->create(['price' => 3000]);
    $product = Product::factory()->create(['price' => 10000]);
    $product->toppings()->attach($topping->id);

    foreach ([0, -1, 1.5, 21] as $quantity) {
        $this->postJson(route('orders.store'), [
            'type' => 'TA',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'toppings' => [['id' => $topping->id, 'quantity' => $quantity]],
            ]],
        ])->assertStatus(422)->assertJsonValidationErrors('items.0.toppings.0.quantity');

        $this->assertDatabaseCount('orders', 0);
    }
});

test('an order without any topping still works', function () {
    $product = Product::factory()->create(['price' => 10000]);

    $this->postJson(route('orders.store'), [
        'type' => 'TA',
        'items' => [['product_id' => $product->id, 'quantity' => 2]],
    ])->assertCreated();

    $order = Order::sole();

    expect($order->total)->toBe('20000.00')
        ->and($order->items()->sole()->toppings()->count())->toBe(0);
});

test('the legacy topping_ids payload still works with an implicit quantity of one', function () {
    $topping = Topping::factory()->create(['price' => 3000]);
    $product = Product::factory()->create(['price' => 10000]);
    $product->toppings()->attach($topping->id);

    $this->postJson(route('orders.store'), [
        'type' => 'TA',
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 1,
            'topping_ids' => [$topping->id],
        ]],
    ])->assertCreated();

    $itemTopping = Order::sole()->items()->sole()->toppings()->sole();

    expect($itemTopping->quantity)->toBe(1)
        ->and(Order::sole()->total)->toBe('13000.00');
});

test('existing orders stored without product-topping links remain readable', function () {
    $order = Order::factory()->create(['subtotal' => 15000, 'total' => 15000]);
    $item = OrderItem::factory()->for($order)->create([
        'product_name' => 'Mi Ayam Original',
        'unit_price' => 15000,
        'quantity' => 1,
        'subtotal' => 15000,
    ]);

    $item->toppings()->create([
        'topping_id' => null,
        'topping_name' => 'Telur Rebus',
        'price' => 4000,
        'quantity' => 2,
    ]);

    $stored = OrderItemTopping::sole();

    expect($stored->topping_id)->toBeNull()
        ->and($stored->quantity)->toBe(2);

    $this->get(route('orders.receipt', $order))
        ->assertOk()
        ->assertSee('Telur Rebus');

    $this->get(route('orders.show', $order))
        ->assertOk()
        ->assertSee('Telur Rebus');
});

test('a topping that is unavailable cannot be ordered even when linked', function () {
    $topping = Topping::factory()->unavailable()->create(['price' => 3000]);
    $product = Product::factory()->create(['price' => 10000]);
    $product->toppings()->attach($topping->id);

    $this->postJson(route('orders.store'), [
        'type' => 'TA',
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 1,
            'toppings' => [['id' => $topping->id, 'quantity' => 1]],
        ]],
    ])->assertStatus(422)->assertJsonValidationErrors('items');

    $this->assertDatabaseCount('orders', 0);
});
