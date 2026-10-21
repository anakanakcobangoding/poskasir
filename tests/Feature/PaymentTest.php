<?php

use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Product;
use App\Services\OrderService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    actingAsUser();
});

function payableProduct(int $price = 20000): Product
{
    return Product::factory()->create(['name' => 'Bakso Urat', 'price' => $price]);
}

function orderPayload(Product $product, int $quantity = 1): array
{
    return [
        'type' => OrderType::TA->value,
        'items' => [
            ['product_id' => $product->id, 'quantity' => $quantity],
        ],
    ];
}

test('an unpaid order without payment data succeeds and stores no payment fields', function () {
    $product = payableProduct();

    $this->postJson(route('orders.store'), orderPayload($product, 2))->assertCreated();

    $order = Order::sole();

    expect($order->payment_status)->toBe(PaymentStatus::Unpaid)
        ->and($order->payment_method)->toBeNull()
        ->and($order->amount_paid)->toBeNull()
        ->and($order->change)->toBeNull()
        ->and($order->paid_at)->toBeNull();
});

test('cash payment with exact amount produces zero change', function () {
    $product = payableProduct(20000);

    $this->postJson(route('orders.store'), orderPayload($product) + [
        'payment_status' => PaymentStatus::Paid->value,
        'payment_method' => PaymentMethod::Cash->value,
        'payment_confirmed' => true,
        'amount_paid' => 20000,
    ])->assertCreated();

    $order = Order::sole();

    expect($order->payment_status)->toBe(PaymentStatus::Paid)
        ->and($order->payment_method)->toBe(PaymentMethod::Cash)
        ->and($order->amount_paid)->toBe('20000.00')
        ->and($order->change)->toBe('0.00')
        ->and($order->paid_at)->not->toBeNull();
});

test('cash payment with excess amount produces the correct change', function () {
    $product = payableProduct(15000);

    $this->postJson(route('orders.store'), orderPayload($product, 2) + [
        'payment_status' => PaymentStatus::Paid->value,
        'payment_method' => PaymentMethod::Cash->value,
        'payment_confirmed' => true,
        'amount_paid' => 50000,
    ])->assertCreated();

    $order = Order::sole();

    expect($order->total)->toBe('30000.00')
        ->and($order->amount_paid)->toBe('50000.00')
        ->and($order->change)->toBe('20000.00');
});

test('cash payment below the total is rejected', function () {
    $product = payableProduct(20000);

    $this->postJson(route('orders.store'), orderPayload($product) + [
        'payment_status' => PaymentStatus::Paid->value,
        'payment_method' => PaymentMethod::Cash->value,
        'payment_confirmed' => true,
        'amount_paid' => 10000,
    ])->assertStatus(422)->assertJsonValidationErrors('amount_paid');

    $this->assertDatabaseCount('orders', 0);
});

test('qris payment records the server total and zero change', function () {
    $product = payableProduct(18000);

    $this->postJson(route('orders.store'), orderPayload($product, 3) + [
        'payment_status' => PaymentStatus::Paid->value,
        'payment_method' => PaymentMethod::Qris->value,
        'payment_confirmed' => true,
    ])->assertCreated();

    $order = Order::sole();

    expect($order->total)->toBe('54000.00')
        ->and($order->payment_method)->toBe(PaymentMethod::Qris)
        ->and($order->amount_paid)->toBe('54000.00')
        ->and($order->change)->toBe('0.00')
        ->and($order->payment_status)->toBe(PaymentStatus::Paid)
        ->and($order->paid_at)->not->toBeNull();
});

test('qris payment ignores a client supplied amount and change', function () {
    $product = payableProduct(20000);

    $this->postJson(route('orders.store'), orderPayload($product) + [
        'payment_status' => PaymentStatus::Paid->value,
        'payment_method' => PaymentMethod::Qris->value,
        'payment_confirmed' => true,
        'change' => 999,
    ])->assertCreated();

    $order = Order::sole();

    expect($order->amount_paid)->toBe('20000.00')
        ->and($order->change)->toBe('0.00');
});

test('an invalid payment method is rejected', function () {
    $product = payableProduct();

    $this->postJson(route('orders.store'), orderPayload($product) + [
        'payment_status' => PaymentStatus::Paid->value,
        'payment_method' => 'transfer',
        'payment_confirmed' => true,
        'amount_paid' => 50000,
    ])->assertStatus(422)->assertJsonValidationErrors('payment_method');

    $this->assertDatabaseCount('orders', 0);
});

test('a paid order without a payment method is rejected', function () {
    $product = payableProduct();

    $this->postJson(route('orders.store'), orderPayload($product) + [
        'payment_status' => PaymentStatus::Paid->value,
    ])->assertStatus(422)->assertJsonValidationErrors('payment_method');

    $this->assertDatabaseCount('orders', 0);
});

test('client supplied total, change, paid_at, and prices are ignored', function () {
    $product = payableProduct(20000);

    $this->postJson(route('orders.store'), orderPayload($product, 2) + [
        'subtotal' => 1,
        'total' => 1,
        'change' => 1,
        'paid_at' => '2000-01-01 00:00:00',
        'payment_status' => PaymentStatus::Paid->value,
        'payment_method' => PaymentMethod::Cash->value,
        'payment_confirmed' => true,
        'amount_paid' => 50000,
    ])->assertCreated();

    $order = Order::sole();

    expect($order->total)->toBe('40000.00')
        ->and($order->change)->toBe('10000.00')
        ->and($order->paid_at->year)->toBe((int) CarbonImmutable::now()->year);
});

test('a failed payment validation leaves no partial order or items', function () {
    $product = payableProduct(20000);

    $this->postJson(route('orders.store'), orderPayload($product) + [
        'payment_status' => PaymentStatus::Paid->value,
        'payment_method' => PaymentMethod::Cash->value,
        'payment_confirmed' => true,
        'amount_paid' => 5000,
    ])->assertStatus(422);

    $this->assertDatabaseCount('orders', 0);
    $this->assertDatabaseCount('order_items', 0);
});

test('the receipt shows payment details for a paid cash order', function () {
    $product = payableProduct(15000);

    $this->postJson(route('orders.store'), orderPayload($product, 2) + [
        'payment_status' => PaymentStatus::Paid->value,
        'payment_method' => PaymentMethod::Cash->value,
        'payment_confirmed' => true,
        'amount_paid' => 40000,
    ])->assertCreated();

    $order = Order::sole();

    $this->get(route('orders.receipt', $order))
        ->assertOk()
        ->assertSee('Lunas')
        ->assertSee('Tunai')
        ->assertSee('Rp 40.000')
        ->assertSee('Rp 10.000');
});

test('the receipt shows an unpaid order without payment details', function () {
    $product = payableProduct(15000);

    $this->postJson(route('orders.store'), orderPayload($product))->assertCreated();

    $order = Order::sole();

    $this->get(route('orders.receipt', $order))
        ->assertOk()
        ->assertSee('Belum dibayar')
        ->assertDontSee('Kembalian');
});

test('more than fifty item lines are rejected', function () {
    $product = payableProduct();

    $items = array_fill(0, 51, ['product_id' => $product->id, 'quantity' => 1]);

    $this->postJson(route('orders.store'), [
        'type' => OrderType::TA->value,
        'items' => $items,
    ])->assertStatus(422)->assertJsonValidationErrors('items');

    $this->assertDatabaseCount('orders', 0);
});

test('a paid order without the cashier confirmation is rejected', function () {
    $product = payableProduct(20000);

    $this->postJson(route('orders.store'), orderPayload($product) + [
        'payment_status' => PaymentStatus::Paid->value,
        'payment_method' => PaymentMethod::Cash->value,
        'amount_paid' => 50000,
    ])->assertStatus(422)->assertJsonValidationErrors('payment_confirmed');

    $this->assertDatabaseCount('orders', 0);
});

test('a qris order without the cashier confirmation is rejected and not stored as paid', function () {
    $product = payableProduct(20000);

    $this->postJson(route('orders.store'), orderPayload($product) + [
        'payment_status' => PaymentStatus::Paid->value,
        'payment_method' => PaymentMethod::Qris->value,
    ])->assertStatus(422)->assertJsonValidationErrors('payment_confirmed');

    $this->assertDatabaseCount('orders', 0);
});

test('selecting qris as an unpaid order never marks it paid', function () {
    $product = payableProduct(20000);

    $this->postJson(route('orders.store'), orderPayload($product) + [
        'payment_method' => PaymentMethod::Qris->value,
    ])->assertCreated();

    $order = Order::sole();

    expect($order->payment_status)->toBe(PaymentStatus::Unpaid)
        ->and($order->payment_method)->toBe(PaymentMethod::Qris)
        ->and($order->amount_paid)->toBeNull()
        ->and($order->change)->toBeNull()
        ->and($order->paid_at)->toBeNull();
});

test('an explicitly confirmed qris order is marked paid with the server total', function () {
    $product = payableProduct(20000);

    $this->postJson(route('orders.store'), orderPayload($product, 2) + [
        'payment_status' => PaymentStatus::Paid->value,
        'payment_method' => PaymentMethod::Qris->value,
        'payment_confirmed' => 1,
    ])->assertCreated();

    $order = Order::sole();

    expect($order->payment_status)->toBe(PaymentStatus::Paid)
        ->and($order->payment_method)->toBe(PaymentMethod::Qris)
        ->and($order->amount_paid)->toBe('40000.00')
        ->and($order->change)->toBe('0.00')
        ->and($order->paid_at)->not->toBeNull();
});

test('the confirmation flag is not required for an unpaid order', function () {
    $product = payableProduct();

    $this->postJson(route('orders.store'), orderPayload($product))->assertCreated();

    expect(Order::sole()->payment_status)->toBe(PaymentStatus::Unpaid);
});

test('the service refuses to mark an order paid without an explicit confirmation', function () {
    $product = payableProduct(20000);

    expect(fn () => app(OrderService::class)->create([
        'type' => OrderType::TA->value,
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
        'payment_status' => PaymentStatus::Paid->value,
        'payment_method' => PaymentMethod::Qris->value,
    ]))->toThrow(ValidationException::class);

    $this->assertDatabaseCount('orders', 0);
});
