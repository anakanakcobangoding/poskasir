<?php

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemTopping;
use App\Models\Product;
use App\Models\Topping;
use App\Services\OrderService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

/**
 * Build the duplicate-key exception a concurrent order insert would raise.
 */
function orderNumberConflict(): QueryException
{
    return new QueryException(
        'sqlite',
        'insert into "orders" ("order_number") values (?)',
        [],
        new PDOException('SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed: orders.order_number', 23000),
    );
}

test('it stores a takeaway order with database snapshots and server-calculated totals', function () {
    $product = Product::factory()->create(['name' => 'Mi Ayam Original', 'price' => 15000]);
    $topping = Topping::factory()->create(['name' => 'Telur Rebus', 'price' => 3000]);

    $response = $this->postJson(route('orders.store'), [
        'type' => 'TA',
        'customer_name' => 'Budi',
        'items' => [
            [
                'product_id' => $product->id,
                'quantity' => 2,
                'topping_ids' => [$topping->id],
            ],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['message', 'order_number']);

    $order = Order::sole();

    $response->assertJsonPath('order_number', $order->order_number);

    expect($order->order_number)->toMatch('/^WC-\d{8}-\d{3}$/')
        ->and($order->type)->toBe(OrderType::TA)
        ->and($order->status)->toBe(OrderStatus::New)
        ->and($order->payment_status)->toBe(PaymentStatus::Unpaid)
        ->and($order->payment_method)->toBeNull()
        ->and($order->amount_paid)->toBeNull()
        ->and($order->change)->toBeNull()
        ->and($order->paid_at)->toBeNull()
        ->and($order->customer_name)->toBe('Budi')
        ->and($order->table_number)->toBeNull()
        ->and($order->delivery_address)->toBeNull()
        ->and($order->subtotal)->toBe('36000.00')
        ->and($order->total)->toBe('36000.00');

    $item = $order->items()->sole();

    expect($item->product_id)->toBe($product->id)
        ->and($item->product_name)->toBe('Mi Ayam Original')
        ->and($item->unit_price)->toBe('15000.00')
        ->and($item->quantity)->toBe(2)
        ->and($item->subtotal)->toBe('36000.00');

    $itemTopping = $item->toppings()->sole();

    expect($itemTopping->topping_id)->toBe($topping->id)
        ->and($itemTopping->topping_name)->toBe('Telur Rebus')
        ->and($itemTopping->price)->toBe('3000.00')
        ->and($itemTopping->quantity)->toBe(1);
});

test('stores a dine-in order with its table number', function () {
    $product = Product::factory()->create(['price' => 12000]);

    $this->postJson(route('orders.store'), [
        'type' => 'DI',
        'table_number' => 'A1',
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
    ])->assertCreated();

    $order = Order::sole();

    expect($order->type)->toBe(OrderType::DI)
        ->and($order->table_number)->toBe('A1')
        ->and($order->delivery_address)->toBeNull()
        ->and($order->total)->toBe('12000.00');
});

test('stores a delivery order with its address', function () {
    $product = Product::factory()->create(['price' => 18000]);

    $this->postJson(route('orders.store'), [
        'type' => 'DLV',
        'delivery_address' => 'Jl. Merdeka No. 10',
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
    ])->assertCreated();

    $order = Order::sole();

    expect($order->type)->toBe(OrderType::DLV)
        ->and($order->delivery_address)->toBe('Jl. Merdeka No. 10')
        ->and($order->table_number)->toBeNull();
});

test('requires a table number for dine-in orders', function () {
    $product = Product::factory()->create();

    $this->postJson(route('orders.store'), [
        'type' => 'DI',
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['table_number' => 'Nomor meja wajib diisi untuk pesanan dine-in.']);

    $this->assertDatabaseCount('orders', 0);
});

test('requires a delivery address for delivery orders', function () {
    $product = Product::factory()->create();

    $this->postJson(route('orders.store'), [
        'type' => 'DLV',
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['delivery_address' => 'Alamat pengiriman wajib diisi untuk pesanan delivery.']);

    $this->assertDatabaseCount('orders', 0);
});

test('ignores table and delivery fields for takeaway orders', function () {
    $product = Product::factory()->create();

    $this->postJson(route('orders.store'), [
        'type' => 'TA',
        'table_number' => 'B9',
        'delivery_address' => 'Jl. Palsu No. 1',
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
    ])->assertCreated();

    $order = Order::sole();

    expect($order->table_number)->toBeNull()
        ->and($order->delivery_address)->toBeNull();
});

test('rejects an empty cart', function () {
    $this->postJson(route('orders.store'), ['type' => 'TA', 'items' => []])
        ->assertStatus(422)
        ->assertJsonValidationErrors('items');

    $this->assertDatabaseCount('orders', 0);
});

test('rejects an invalid payload', function () {
    $response = $this->postJson(route('orders.store'), [
        'type' => 'PICKUP',
        'items' => [
            ['product_id' => 'abc', 'quantity' => 0],
            ['product_id' => 999999, 'quantity' => 1],
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors([
            'type',
            'items.0.product_id',
            'items.0.quantity',
            'items.1.product_id',
        ]);

    $this->assertDatabaseCount('orders', 0);
});

test('rejects unknown products', function () {
    $this->postJson(route('orders.store'), [
        'type' => 'TA',
        'items' => [['product_id' => 999999, 'quantity' => 1]],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['items.0.product_id']);

    $this->assertDatabaseCount('orders', 0);
});

test('rejects unavailable products', function () {
    $unavailable = Product::factory()->unavailable()->create();

    $this->postJson(route('orders.store'), [
        'type' => 'TA',
        'items' => [['product_id' => $unavailable->id, 'quantity' => 1]],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('items');

    $this->assertDatabaseCount('orders', 0);

    expect(fn () => app(OrderService::class)->create([
        'type' => 'TA',
        'items' => [['product_id' => $unavailable->id, 'quantity' => 1]],
    ]))->toThrow(ValidationException::class);
});

test('rejects unknown toppings', function () {
    $product = Product::factory()->create();

    $this->postJson(route('orders.store'), [
        'type' => 'TA',
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1, 'topping_ids' => [999999]],
        ],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['items.0.topping_ids.0']);

    $this->assertDatabaseCount('orders', 0);
});

test('rejects unavailable toppings', function () {
    $product = Product::factory()->create();
    $unavailable = Topping::factory()->unavailable()->create();

    $this->postJson(route('orders.store'), [
        'type' => 'TA',
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1, 'topping_ids' => [$unavailable->id]],
        ],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['items']);

    $this->assertDatabaseCount('orders', 0);
});

test('ignores prices and statuses sent by the client', function () {
    $product = Product::factory()->create(['name' => 'Bakso Urat', 'price' => 20000]);
    $topping = Topping::factory()->create(['name' => 'Pangsit', 'price' => 4000]);

    $this->postJson(route('orders.store'), [
        'type' => 'TA',
        'subtotal' => 1,
        'total' => 1,
        'status' => 'completed',
        'payment_status' => 'paid',
        'amount_paid' => 1,
        'items' => [
            [
                'product_id' => $product->id,
                'product_name' => 'Nama Palsu',
                'unit_price' => 1,
                'subtotal' => 1,
                'quantity' => 3,
                'topping_ids' => [$topping->id],
            ],
        ],
    ])->assertCreated();

    $order = Order::sole();
    $item = $order->items()->sole();

    expect($order->status)->toBe(OrderStatus::New)
        ->and($order->payment_status)->toBe(PaymentStatus::Unpaid)
        ->and($order->amount_paid)->toBeNull()
        ->and($order->subtotal)->toBe('72000.00')
        ->and($order->total)->toBe('72000.00')
        ->and($item->product_name)->toBe('Bakso Urat')
        ->and($item->unit_price)->toBe('20000.00')
        ->and($item->subtotal)->toBe('72000.00')
        ->and($item->toppings()->sole()->price)->toBe('4000.00');
});

test('rolls back the whole order when an item insert fails', function () {
    $product = Product::factory()->create(['price' => 10000]);
    $other = Product::factory()->create(['price' => 20000]);

    $attempts = 0;

    Event::listen('eloquent.creating: '.OrderItem::class, function () use (&$attempts) {
        $attempts++;

        if ($attempts === 2) {
            throw new QueryException('sqlite', 'insert into "order_items"', [], new PDOException('insert failed'));
        }
    });

    expect(fn () => app(OrderService::class)->create([
        'type' => 'TA',
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1],
            ['product_id' => $other->id, 'quantity' => 1],
        ],
    ]))->toThrow(QueryException::class);

    $this->assertDatabaseCount('orders', 0);
    $this->assertDatabaseCount('order_items', 0);
    $this->assertDatabaseCount('order_item_toppings', 0);
});

test('rolls back the whole order when a topping insert fails', function () {
    $product = Product::factory()->create(['price' => 10000]);
    $topping = Topping::factory()->create(['price' => 2500]);

    Event::listen('eloquent.creating: '.OrderItemTopping::class, function () {
        throw new QueryException('sqlite', 'insert into "order_item_toppings"', [], new PDOException('boom'));
    });

    expect(fn () => app(OrderService::class)->create([
        'type' => 'TA',
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1, 'topping_ids' => [$topping->id]],
        ],
    ]))->toThrow(QueryException::class);

    $this->assertDatabaseCount('orders', 0);
    $this->assertDatabaseCount('order_items', 0);
    $this->assertDatabaseCount('order_item_toppings', 0);
});

test('numbers orders sequentially for the same Jakarta day', function () {
    $product = Product::factory()->create();
    $this->travelTo(CarbonImmutable::create(2026, 3, 1, 10, 0, 0, 'Asia/Jakarta'));

    $service = app(OrderService::class);
    $payload = ['type' => 'TA', 'items' => [['product_id' => $product->id, 'quantity' => 1]]];

    $first = $service->create($payload);
    $second = $service->create($payload);

    expect($first->order_number)->toBe('WC-20260301-001')
        ->and($second->order_number)->toBe('WC-20260301-002')
        ->and(Order::query()->distinct()->count('order_number'))->toBe(2);
});

test('switches the order number date on the Jakarta day boundary, not the app timezone', function () {
    $product = Product::factory()->create();

    expect(config('app.timezone'))->toBe('UTC');

    $service = app(OrderService::class);
    $payload = ['type' => 'TA', 'items' => [['product_id' => $product->id, 'quantity' => 1]]];

    $this->travelTo(CarbonImmutable::create(2026, 3, 1, 23, 30, 0, 'Asia/Jakarta'));
    expect($service->create($payload)->order_number)->toBe('WC-20260301-001');

    $this->travelTo(CarbonImmutable::create(2026, 3, 2, 0, 30, 0, 'Asia/Jakarta'));
    $second = $service->create($payload);

    expect($second->order_number)->toBe('WC-20260302-001')
        ->and($second->created_at->timezone('UTC')->format('Y-m-d'))->toBe('2026-03-01');
});

test('enforces order number uniqueness at the database level', function () {
    Order::factory()->create(['order_number' => 'WC-20260101-001']);

    expect(fn () => Order::factory()->create(['order_number' => 'WC-20260101-001']))
        ->toThrow(QueryException::class);
});

test('recovers from a transient order number conflict on retry', function () {
    $product = Product::factory()->create();
    $this->travelTo(CarbonImmutable::create(2026, 3, 1, 9, 0, 0, 'Asia/Jakarta'));

    $attempts = 0;

    Event::listen('eloquent.creating: '.Order::class, function () use (&$attempts) {
        $attempts++;

        if ($attempts === 1) {
            throw orderNumberConflict();
        }
    });

    $order = app(OrderService::class)->create([
        'type' => 'TA',
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
    ]);

    expect($attempts)->toBe(2)
        ->and($order->order_number)->toBe('WC-20260301-001')
        ->and(Order::query()->count())->toBe(1)
        ->and($order->items()->count())->toBe(1);
});

test('stops retrying after the bounded attempts are exhausted', function () {
    $product = Product::factory()->create();
    $this->travelTo(CarbonImmutable::create(2026, 3, 1, 9, 0, 0, 'Asia/Jakarta'));

    $attempts = 0;

    Event::listen('eloquent.creating: '.Order::class, function () use (&$attempts) {
        $attempts++;

        throw orderNumberConflict();
    });

    expect(fn () => app(OrderService::class)->create([
        'type' => 'TA',
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
    ]))->toThrow(QueryException::class);

    expect($attempts)->toBe(3)
        ->and(Order::query()->count())->toBe(0);
});

test('does not retry database errors unrelated to the order number', function () {
    $product = Product::factory()->create();
    $this->travelTo(CarbonImmutable::create(2026, 3, 1, 9, 0, 0, 'Asia/Jakarta'));

    $attempts = 0;

    Event::listen('eloquent.creating: '.Order::class, function () use (&$attempts) {
        $attempts++;

        throw new QueryException('sqlite', 'select * from "missing_table"', [], new PDOException('no such table: missing_table', 1));
    });

    expect(fn () => app(OrderService::class)->create([
        'type' => 'TA',
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
    ]))->toThrow(QueryException::class);

    expect($attempts)->toBe(1);
});
