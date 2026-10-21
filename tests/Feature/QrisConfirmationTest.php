<?php

use App\Models\Order;
use App\Models\Product;

test('QRIS checkout requires explicit manual confirmation before recording a paid transaction', function () {
    actingAsUser();
    $product = Product::factory()->create();
    $this->postJson(route('orders.store'), [
        'type' => 'TA', 'items' => [['product_id' => $product->id, 'quantity' => 1]],
        'payment_status' => 'paid', 'payment_method' => 'qris', 'payment_confirmed' => false,
    ])->assertUnprocessable()->assertJsonValidationErrors('payment_confirmed');
    expect(Order::count())->toBe(0);
});
