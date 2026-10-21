<?php

use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

test('repeated checkout requests with the same key create one order', function () {
    actingAsUser();
    $product = Product::factory()->create(['price' => 10000]);
    $payload = ['type' => 'TA', 'payment_method' => 'cash', 'payment_status' => 'paid', 'payment_confirmed' => true, 'amount_paid' => 10000, 'items' => [['product_id' => $product->id, 'quantity' => 1]]];
    $headers = ['Idempotency-Key' => (string) Str::uuid()];

    $first = $this->postJson(route('orders.store'), $payload, $headers)->assertCreated();
    $second = $this->postJson(route('orders.store'), $payload, $headers)->assertCreated();

    expect($second->json('id'))->toBe($first->json('id'));
    $this->assertDatabaseCount('orders', 1);
    $this->assertDatabaseCount('order_items', 1);
});

test('a checkout key cannot be reused for a changed cart', function () {
    actingAsUser();
    $product = Product::factory()->create();
    $payload = ['type' => 'TA', 'items' => [['product_id' => $product->id, 'quantity' => 1]]];
    $headers = ['Idempotency-Key' => (string) Str::uuid()];
    $this->postJson(route('orders.store'), $payload, $headers)->assertCreated();

    $payload['items'][0]['quantity'] = 2;
    $this->postJson(route('orders.store'), $payload, $headers)->assertConflict();

    $this->assertDatabaseCount('orders', 1);
    expect(Order::sole()->items()->sole()->quantity)->toBe(1);
});

test('checkout replay keys are scoped to the cashier', function () {
    actingAsUser();
    $product = Product::factory()->create();
    $payload = ['type' => 'TA', 'items' => [['product_id' => $product->id, 'quantity' => 1]]];
    $headers = ['Idempotency-Key' => (string) Str::uuid()];
    $this->postJson(route('orders.store'), $payload, $headers)->assertCreated();
    actingAsUser();

    $this->postJson(route('orders.store'), $payload, $headers)->assertCreated();

    $this->assertDatabaseCount('orders', 2);
});

test('an invalid checkout token is rejected without storing an order', function () {
    actingAsUser();
    $product = Product::factory()->create();

    $this->postJson(route('orders.store'), ['type' => 'TA', 'items' => [['product_id' => $product->id, 'quantity' => 1]]], ['Idempotency-Key' => 'invalid'])
        ->assertUnprocessable()->assertJsonValidationErrors('checkout_token');

    $this->assertDatabaseCount('orders', 0);
});

test('an in-progress checkout cannot create a second order', function () {
    $user = actingAsUser();
    $product = Product::factory()->create();
    $token = (string) Str::uuid();
    $lock = Cache::store('database')->lock('checkout:'.$user->id.':'.$token.':lock', 120);
    expect($lock->get())->toBeTrue();

    try {
        $this->postJson(route('orders.store'), ['type' => 'TA', 'items' => [['product_id' => $product->id, 'quantity' => 1]]], ['Idempotency-Key' => $token])
            ->assertConflict()->assertJsonPath('message', 'Checkout masih diproses. Tunggu sebentar lalu coba lagi.');

        $this->assertDatabaseCount('orders', 0);
    } finally {
        $lock->release();
    }
});
