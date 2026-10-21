<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

test('closed store rejects new transactions for both roles and payment methods', function (string $role, string $method) {
    $this->actingAs(User::factory()->{$role}()->create());
    Setting::create(['key' => 'store_status', 'value' => 'closed']);
    $product = Product::factory()->create(['price' => 10000]);
    $this->postJson(route('orders.store'), [
        'type' => 'TA', 'items' => [['product_id' => $product->id, 'quantity' => 1]],
        'payment_status' => 'paid', 'payment_method' => $method,
        'payment_confirmed' => true,
        ...($method === 'cash' ? ['amount_paid' => 10000] : []),
    ])->assertUnprocessable()->assertJsonValidationErrors('store_status');
    expect(Order::count())->toBe(0);
})->with(['admin', 'kasir'])->with(['cash', 'qris']);

test('service rejects stale carts when the database status changes', function () {
    $product = Product::factory()->create();
    $service = app(OrderService::class);
    Setting::create(['key' => 'store_status', 'value' => 'closed']);
    expect(fn () => $service->create(['type' => 'TA', 'items' => [['product_id' => $product->id, 'quantity' => 1]]]))
        ->toThrow(ValidationException::class);
    expect(Order::count())->toBe(0);
});

test('cashier cannot change store status but administrator can reopen it', function () {
    Setting::create(['key' => 'store_status', 'value' => 'closed']);
    $this->actingAs(User::factory()->kasir()->create());
    $this->putJson(route('settings.store-profile.open'))->assertForbidden();
    expect(Setting::where('key', 'store_status')->value('value'))->toBe('closed');
    $this->actingAs(User::factory()->admin()->create());
    $this->putJson(route('settings.store-profile.open'))->assertOk()->assertJsonPath('status', 'open');
    $this->getJson(route('store.status'))->assertOk()->assertJsonPath('status', 'open');
    $product = Product::factory()->create();
    $this->postJson(route('orders.store'), ['type' => 'TA', 'items' => [['product_id' => $product->id, 'quantity' => 1]]])->assertCreated();
});

test('closing blocks completion while keeping history receipts and cancellation available', function () {
    $this->actingAs(User::factory()->kasir()->create());
    $order = Order::factory()->create(['status' => OrderStatus::Ready]);
    Setting::create(['key' => 'store_status', 'value' => 'closed']);
    $this->patchJson(route('orders.status', $order), ['status' => 'completed'])
        ->assertUnprocessable()->assertJsonValidationErrors('store_status');
    expect($order->fresh()->status)->toBe(OrderStatus::Ready);
    $this->get(route('orders.show', $order))->assertOk();
    $this->get(route('orders.receipt', $order))->assertOk();
    $this->patch(route('orders.status', $order), ['status' => 'cancelled'])->assertRedirect();
    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);
});

test('welcome and cashier read the persisted status across requests', function () {
    $this->actingAs(User::factory()->admin()->create());
    $this->putJson(route('settings.store-profile.close'))->assertOk()->assertJsonPath('status', 'closed');
    $this->get('/')->assertOk()->assertSee('TOKO TUTUP')->assertSee('Kasir Sedang Tutup');
    $this->get(route('pos.index'))->assertOk()->assertSee('TOKO TUTUP')->assertSee('TOKO SEDANG TUTUP');
    $this->post(route('logout'));
    $cashier = User::factory()->kasir()->create();
    $this->post(route('login.store'), ['email' => $cashier->email, 'password' => 'password'])->assertRedirect('/');
    $this->getJson(route('store.status'))->assertOk()->assertJsonPath('status', 'closed')->assertHeader('Cache-Control', 'no-store, private');
    $this->get('/')->assertOk()->assertSee('Kasir Sedang Tutup')->assertDontSee('data-store-action', false);
});

test('a completed checkout replay is rejected when the store has since closed', function () {
    $this->actingAs(User::factory()->kasir()->create());
    $product = Product::factory()->create();
    $payload = ['type' => 'TA', 'items' => [['product_id' => $product->id, 'quantity' => 1]]];
    $headers = ['Idempotency-Key' => (string) Str::uuid()];
    $this->postJson(route('orders.store'), $payload, $headers)->assertCreated();
    Setting::where('key', 'store_status')->update(['value' => 'closed']);
    $this->postJson(route('orders.store'), $payload, $headers)->assertUnprocessable()->assertJsonValidationErrors('store_status');
    expect(Order::count())->toBe(1);
});

test('store controls render for administrators while cashiers only see the status', function () {
    $this->actingAs(User::factory()->admin()->create());
    $product = Product::factory()->create(['price' => 10000]);
    $admin = $this->get(route('pos.index'))->assertOk()->assertSee('data-store-action', false);
    $this->actingAs(User::factory()->kasir()->create());
    $cashier = $this->get(route('pos.index'))->assertOk()->assertDontSee('data-store-action', false);
    if ($directory = getenv('STORE_BROWSER_FIXTURES')) {
        file_put_contents($directory.'/admin.html', $admin->getContent());
        file_put_contents($directory.'/cashier.html', $cashier->getContent());
    }
});
