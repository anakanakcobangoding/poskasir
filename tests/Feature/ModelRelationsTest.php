<?php

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemTopping;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Topping;
use Illuminate\Database\QueryException;

test('category lists its products', function () {
    $category = Category::factory()->create();
    Product::factory()->count(2)->for($category)->create();

    expect($category->products)->toHaveCount(2);
});

test('order lists its items with topping details', function () {
    $order = Order::factory()->dineIn()->create();
    $item = OrderItem::factory()->for($order)->create();
    $topping = Topping::factory()->create();
    $item->toppings()->create([
        'topping_id' => $topping->id,
        'topping_name' => $topping->name,
        'price' => $topping->price,
        'quantity' => 1,
    ]);

    $storedTopping = $order->items->first()->toppings->first();

    expect($order->items)->toHaveCount(1)
        ->and($order->items->first()->toppings)->toHaveCount(1)
        ->and($storedTopping->topping->is($topping))->toBeTrue()
        ->and($storedTopping->topping_name)->toBe($topping->name);
});

test('deleting an order removes its items and item toppings', function () {
    $order = Order::factory()->create();
    $item = OrderItem::factory()->for($order)->create();
    $item->toppings()->create([
        'topping_id' => Topping::factory()->create()->id,
        'topping_name' => 'Kerupuk',
        'price' => 2000,
        'quantity' => 1,
    ]);

    $order->delete();

    expect(OrderItem::where('order_id', $order->id)->exists())->toBeFalse()
        ->and(OrderItemTopping::count())->toBe(0);
});

test('prevents deleting a category that still has products', function () {
    $category = Category::factory()->create();
    Product::factory()->for($category)->create();

    expect(fn () => $category->delete())->toThrow(QueryException::class)
        ->and($category->products()->exists())->toBeTrue();
});

test('keeps the product snapshot when the product is deleted', function () {
    $order = Order::factory()->create();
    $product = Product::factory()->create(['name' => 'Mi Ayam Original', 'price' => 15000]);
    $item = OrderItem::factory()->for($order)->for($product)->create([
        'product_name' => 'Mi Ayam Original',
        'unit_price' => 15000,
        'quantity' => 2,
        'subtotal' => 30000,
    ]);

    $product->delete();
    $item->refresh();

    expect($item->product_id)->toBeNull()
        ->and($item->product_name)->toBe('Mi Ayam Original')
        ->and($item->unit_price)->toBe('15000.00');
});

test('keeps the topping snapshot when the topping is deleted', function () {
    $order = Order::factory()->create();
    $item = OrderItem::factory()->for($order)->create();
    $topping = Topping::factory()->create(['name' => 'Telur Rebus', 'price' => 4000]);
    $itemTopping = $item->toppings()->create([
        'topping_id' => $topping->id,
        'topping_name' => 'Telur Rebus',
        'price' => 4000,
        'quantity' => 1,
    ]);

    $topping->delete();
    $itemTopping->refresh();

    expect($itemTopping->topping_id)->toBeNull()
        ->and($itemTopping->topping_name)->toBe('Telur Rebus')
        ->and($itemTopping->price)->toBe('4000.00');
});

test('casts money columns to two decimal places', function () {
    $product = Product::factory()->create(['price' => 15000]);
    $order = Order::factory()->paid()->create();

    expect($product->price)->toBe('15000.00')
        ->and($order->total)->toMatch('/^\d+\.\d{2}$/')
        ->and($order->amount_paid)->toMatch('/^\d+\.\d{2}$/')
        ->and($order->change)->toMatch('/^\d+\.\d{2}$/');
});

test('creates orders as new and unpaid', function () {
    $order = Order::factory()->create();

    expect($order->type)->toBe(OrderType::TA)
        ->and($order->status)->toBe(OrderStatus::New)
        ->and($order->payment_status)->toBe(PaymentStatus::Unpaid)
        ->and($order->paid_at)->toBeNull();
});

test('requires a table number for dine-in orders and an address for delivery orders', function () {
    $dineIn = Order::factory()->dineIn()->create();
    $delivery = Order::factory()->delivery()->create();

    expect($dineIn->type)->toBe(OrderType::DI)
        ->and($dineIn->table_number)->not->toBeNull()
        ->and($delivery->type)->toBe(OrderType::DLV)
        ->and($delivery->delivery_address)->not->toBeNull();
});

test('exposes the expected order fields', function () {
    $order = Order::factory()->paid()->create();

    expect(array_keys($order->toArray()))->toEqualCanonicalizing([
        'id',
        'order_number',
        'type',
        'status',
        'customer_name',
        'table_number',
        'delivery_address',
        'subtotal',
        'total',
        'payment_method',
        'amount_paid',
        'change',
        'payment_status',
        'paid_at',
        'notes',
        'created_at',
        'updated_at',
    ]);
});

test('stores settings as unique key-value rows', function () {
    Setting::create(['key' => 'qris_image', 'value' => 'qris.png']);

    expect(Setting::where('key', 'qris_image')->value('value'))->toBe('qris.png')
        ->and(fn () => Setting::create(['key' => 'qris_image', 'value' => 'other.png']))
        ->toThrow(QueryException::class);
});
