<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\Topping;

function adjustedOrderPayload(): array
{
    $product = Product::factory()->create(['price' => 10000, 'hpp' => 4000]);
    $topping = Topping::factory()->create(['price' => 3000, 'hpp' => 500]);
    $product->toppings()->attach($topping);

    return ['type' => 'TA', 'payment_method' => 'cash', 'payment_status' => 'paid', 'payment_confirmed' => true, 'amount_paid' => 50000, 'items' => [['product_id' => $product->id, 'quantity' => 2, 'toppings' => [['id' => $topping->id, 'quantity' => 3]]]]];
}

test('cash checkout calculates adjustments on the server and preserves catalog prices', function (string $type, int|float $value, string $discount, string $total, string $change) {
    actingAsUser();
    $payload = adjustedOrderPayload() + ['discount_type' => $type, 'discount_value' => $value, 'additional_fee_label' => 'Tip', 'additional_fee_amount' => 2000, 'total' => 1, 'discount_amount' => 1];

    $this->postJson(route('orders.store'), $payload)->assertCreated();

    $order = Order::sole();
    expect($order->subtotal)->toBe('38000.00');
    expect($order->discount_amount)->toBe($discount);
    expect($order->additional_fee_amount)->toBe('2000.00');
    expect($order->total)->toBe($total);
    expect($order->change)->toBe($change);
    expect(Product::sole()->price)->toBe('10000.00');
    expect(Topping::sole()->price)->toBe('3000.00');
    $this->get(route('orders.receipt', $order))->assertOk()->assertSee('Diskon')->assertSee('Tip')->assertSee('Rp 2.000');
})->with([
    'fixed' => ['fixed', 5000, '5000.00', '35000.00', '15000.00'],
    'percentage before fee' => ['percentage', 12.5, '4750.00', '35250.00', '14750.00'],
]);

test('invalid transaction adjustments leave no partial order', function (array $adjustment, string $field) {
    actingAsUser();

    $this->postJson(route('orders.store'), adjustedOrderPayload() + $adjustment)->assertUnprocessable()->assertJsonValidationErrors($field);

    $this->assertDatabaseCount('orders', 0);
})->with([
    'negative discount' => [['discount_type' => 'fixed', 'discount_value' => -1], 'discount_value'],
    'excessive discount' => [['discount_type' => 'fixed', 'discount_value' => 38001], 'discount_value'],
    'excessive percentage' => [['discount_type' => 'percentage', 'discount_value' => 101], 'discount_value'],
    'negative fee' => [['additional_fee_label' => 'Tip', 'additional_fee_amount' => -1], 'additional_fee_amount'],
    'missing fee label' => [['additional_fee_amount' => 1000], 'additional_fee_label'],
]);
