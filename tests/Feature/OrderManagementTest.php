<?php

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;

beforeEach(function () {
    actingAsUser();
});

/**
 * Create an order with the given attributes and item count.
 */
function orderWithItems(array $attributes = [], int $itemCount = 1): Order
{
    $order = Order::factory()->create($attributes);

    OrderItem::factory()->count($itemCount)->for($order)->create();

    return $order;
}

test('the order list shows active orders by default', function () {
    $new = Order::factory()->create(['status' => OrderStatus::New]);
    $preparing = Order::factory()->create(['status' => OrderStatus::Preparing]);
    $ready = Order::factory()->create(['status' => OrderStatus::Ready]);
    $completed = Order::factory()->create(['status' => OrderStatus::Completed]);
    $cancelled = Order::factory()->create(['status' => OrderStatus::Cancelled]);

    $response = $this->get(route('orders.index'))->assertOk();

    $response->assertSee($new->order_number)
        ->assertSee($preparing->order_number)
        ->assertSee($ready->order_number)
        ->assertDontSee($completed->order_number)
        ->assertDontSee($cancelled->order_number);
});

test('the order list shows number, type, item count, total and status', function () {
    $order = orderWithItems([
        'status' => OrderStatus::New,
        'type' => OrderType::DI,
        'table_number' => 'A1',
        'total' => 36000,
        'subtotal' => 36000,
    ], 2);

    $this->get(route('orders.index'))
        ->assertOk()
        ->assertSee($order->order_number)
        ->assertSee('Rp 36.000')
        ->assertSee('2 item')
        ->assertSee('Dine-in');
});

test('the order list filters by status', function () {
    $new = Order::factory()->create(['status' => OrderStatus::New]);
    $completed = Order::factory()->create(['status' => OrderStatus::Completed]);

    $this->get(route('orders.index', ['status' => 'completed']))
        ->assertOk()
        ->assertSee($completed->order_number)
        ->assertDontSee($new->order_number);
});

test('the order list filters by type', function () {
    $delivery = Order::factory()->create(['type' => OrderType::DLV, 'status' => OrderStatus::New]);
    $takeaway = Order::factory()->create(['type' => OrderType::TA, 'status' => OrderStatus::New]);

    $this->get(route('orders.index', ['type' => 'DLV']))
        ->assertOk()
        ->assertSee($delivery->order_number)
        ->assertDontSee($takeaway->order_number);
});

test('the all status filter shows every order', function () {
    $completed = Order::factory()->create(['status' => OrderStatus::Completed]);
    $cancelled = Order::factory()->create(['status' => OrderStatus::Cancelled]);

    $this->get(route('orders.index', ['status' => 'all']))
        ->assertOk()
        ->assertSee($completed->order_number)
        ->assertSee($cancelled->order_number);
});

test('an invalid filter value is ignored without redirecting', function () {
    $completed = Order::factory()->create(['status' => OrderStatus::Completed]);
    $new = Order::factory()->create(['status' => OrderStatus::New]);

    $this->get(route('orders.index', ['status' => 'foo', 'type' => 'XYZ']))
        ->assertOk()
        ->assertSee($new->order_number)
        ->assertDontSee($completed->order_number);
});

test('the order detail shows items and toppings', function () {
    $order = Order::factory()->create([
        'status' => OrderStatus::New,
        'type' => OrderType::TA,
        'total' => 33000,
        'subtotal' => 33000,
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

    $this->get(route('orders.show', $order))
        ->assertOk()
        ->assertSee($order->order_number)
        ->assertSee('Mi Ayam Spesial')
        ->assertSee('Telur Rebus')
        ->assertSee('Rp 15.000')
        ->assertSee('Rp 33.000')
        ->assertSee('Baru');
});

test('the order detail returns not found for an unknown order', function () {
    $this->get('/orders/999999')->assertNotFound();
});

test('a valid status transition is stored', function () {
    $order = Order::factory()->create(['status' => OrderStatus::New]);

    $this->patch(route('orders.status', $order), ['status' => 'preparing'])
        ->assertRedirect(route('orders.show', $order))
        ->assertSessionHas('success');

    expect($order->refresh()->status)->toBe(OrderStatus::Preparing);
});

test('skipping a status is rejected', function () {
    $order = Order::factory()->create(['status' => OrderStatus::New]);

    $this->patch(route('orders.status', $order), ['status' => 'ready'])
        ->assertSessionHasErrors('status');

    expect($order->refresh()->status)->toBe(OrderStatus::New);
});

test('a transition to the same status is rejected', function () {
    $order = Order::factory()->create(['status' => OrderStatus::New]);

    $this->patch(route('orders.status', $order), ['status' => 'new'])
        ->assertSessionHasErrors('status');

    expect($order->refresh()->status)->toBe(OrderStatus::New);
});

test('terminal orders cannot change status', function () {
    $completed = Order::factory()->create(['status' => OrderStatus::Completed]);
    $cancelled = Order::factory()->create(['status' => OrderStatus::Cancelled]);

    $this->patch(route('orders.status', $completed), ['status' => 'preparing'])
        ->assertSessionHasErrors('status');

    $this->patch(route('orders.status', $cancelled), ['status' => 'preparing'])
        ->assertSessionHasErrors('status');

    expect($completed->refresh()->status)->toBe(OrderStatus::Completed)
        ->and($cancelled->refresh()->status)->toBe(OrderStatus::Cancelled);
});

test('orders can be cancelled before completion', function () {
    foreach ([OrderStatus::New, OrderStatus::Preparing, OrderStatus::Ready] as $status) {
        $order = Order::factory()->create(['status' => $status]);

        $this->patch(route('orders.status', $order), ['status' => 'cancelled'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('orders.show', $order));

        expect($order->refresh()->status)->toBe(OrderStatus::Cancelled);
    }
});

test('a completed order cannot be cancelled', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Completed]);

    $this->patch(route('orders.status', $order), ['status' => 'cancelled'])
        ->assertSessionHasErrors('status');

    expect($order->refresh()->status)->toBe(OrderStatus::Completed);
});

test('an unknown status value is rejected', function () {
    $order = Order::factory()->create(['status' => OrderStatus::New]);

    $this->patch(route('orders.status', $order), ['status' => 'foo'])
        ->assertSessionHasErrors('status');

    expect($order->refresh()->status)->toBe(OrderStatus::New);
});

test('the detail exposes only allowed transitions', function () {
    $new = Order::factory()->create(['status' => OrderStatus::New]);
    $completed = Order::factory()->create(['status' => OrderStatus::Completed]);

    $this->get(route('orders.show', $new))
        ->assertOk()
        ->assertSee('Tandai Diproses')
        ->assertSee('Batalkan Pesanan');

    $this->get(route('orders.show', $completed))
        ->assertOk()
        ->assertDontSee('Tandai Diproses')
        ->assertDontSee('Batalkan Pesanan');
});

test('stage three order creation and the cashier page still work', function () {
    $this->get(route('pos.index'))->assertOk();

    $product = Product::factory()->create(['price' => 10000]);

    $this->postJson(route('orders.store'), [
        'type' => 'TA',
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
    ])->assertCreated();

    expect(Order::query()->count())->toBe(1);
});
