<?php

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;

/**
 * Post a minimal valid takeaway order payload.
 */
function validOrderPayload(Product $product): array
{
    return [
        'type' => OrderType::TA->value,
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1],
        ],
    ];
}

test('excessive order creation returns 429 for the same user', function () {
    $product = Product::factory()->create();
    $this->actingAs(User::factory()->kasir()->create());

    for ($attempt = 1; $attempt <= 60; $attempt++) {
        $this->post(route('orders.store'), validOrderPayload($product))
            ->assertStatus(201);
    }

    expect($this->post(route('orders.store'), validOrderPayload($product))->getStatusCode())
        ->toBe(429);
});

test('order creation buckets are isolated per user', function () {
    $product = Product::factory()->create();

    $first = User::factory()->kasir()->create();
    $second = User::factory()->kasir()->create();

    $this->actingAs($first);

    for ($attempt = 1; $attempt <= 60; $attempt++) {
        $this->post(route('orders.store'), validOrderPayload($product))
            ->assertStatus(201);
    }

    $this->post(route('orders.store'), validOrderPayload($product))->assertStatus(429);

    $this->actingAs($second);

    $this->post(route('orders.store'), validOrderPayload($product))
        ->assertStatus(201);
});

test('orders-store and orders-status limiters do not affect each other', function () {
    $product = Product::factory()->create();
    $this->actingAs(User::factory()->kasir()->create());

    for ($attempt = 1; $attempt <= 60; $attempt++) {
        $this->post(route('orders.store'), validOrderPayload($product))
            ->assertStatus(201);
    }

    $this->post(route('orders.store'), validOrderPayload($product))->assertStatus(429);

    $order = Order::factory()->create(['status' => OrderStatus::New]);

    $this->patch(route('orders.status', $order), ['status' => OrderStatus::Preparing->value])
        ->assertRedirect();
});

test('excessive status updates return 429 for the same user', function () {
    $this->actingAs(User::factory()->kasir()->create());

    for ($attempt = 1; $attempt <= 30; $attempt++) {
        $order = Order::factory()->create(['status' => OrderStatus::New]);

        $this->patch(route('orders.status', $order), ['status' => OrderStatus::Preparing->value])
            ->assertRedirect();
    }

    $order = Order::factory()->create(['status' => OrderStatus::New]);

    expect($this->patch(route('orders.status', $order), ['status' => OrderStatus::Preparing->value])->getStatusCode())
        ->toBe(429);
});

test('status update buckets are isolated per user', function () {
    $first = User::factory()->kasir()->create();
    $second = User::factory()->kasir()->create();

    $this->actingAs($first);

    for ($attempt = 1; $attempt <= 30; $attempt++) {
        $order = Order::factory()->create(['status' => OrderStatus::New]);

        $this->patch(route('orders.status', $order), ['status' => OrderStatus::Preparing->value])
            ->assertRedirect();
    }

    $order = Order::factory()->create(['status' => OrderStatus::New]);
    $this->patch(route('orders.status', $order), ['status' => OrderStatus::Preparing->value])->assertStatus(429);

    $this->actingAs($second);

    $order = Order::factory()->create(['status' => OrderStatus::New]);
    $this->patch(route('orders.status', $order), ['status' => OrderStatus::Preparing->value])
        ->assertRedirect();
});

test('plain GET order listing is not rate limited', function () {
    $this->actingAs(User::factory()->kasir()->create());

    for ($visit = 1; $visit <= 70; $visit++) {
        $this->get(route('orders.index'))->assertOk();
    }
});

test('order rate limits do not bypass authentication or role middleware', function () {
    $product = Product::factory()->create();

    $this->post(route('orders.store'), validOrderPayload($product))
        ->assertRedirect(route('login'));

    $kasir = User::factory()->kasir()->create();
    $this->actingAs($kasir);

    $this->get(route('products.index'))->assertForbidden();
    $this->post(route('products.store'), ['name' => 'X', 'price' => 1000])->assertForbidden();
});
