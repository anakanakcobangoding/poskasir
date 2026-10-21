<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Topping;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create(['email' => str_repeat('long-email-', 7).'@example.test']));
});

test('sales summaries multiply topping costs by product quantity and exclude cancelled orders', function (string $route) {
    $this->travelTo(now()->setTime(10, 0));
    $order = Order::factory()->paid()->create(['total' => 32000]);
    $item = OrderItem::factory()->for($order)->create(['unit_price' => 10000, 'hpp' => 4000, 'quantity' => 2, 'subtotal' => 32000]);
    $topping = Topping::factory()->create();
    $item->toppings()->create(['topping_id' => $topping->id, 'topping_name' => 'Extra', 'price' => 2000, 'hpp' => 500, 'quantity' => 3]);
    Order::factory()->paid()->create(['status' => OrderStatus::Cancelled, 'total' => 90000]);

    $response = $this->get(route($route))->assertOk()
        ->assertViewHas('totalRevenue', 32000.0)->assertViewHas('totalOrders', 1)
        ->assertViewHas('totalProductHpp', 8000.0)->assertViewHas('totalToppingHpp', 3000.0)
        ->assertViewHas('totalHpp', 11000.0)->assertViewHas('grossProfit', 21000.0)
        ->assertViewHas('grossMargin', 65.625);

    $products = $response->viewData($route === 'dashboard.index' ? 'topProducts' : 'productSales');
    expect($products[0]['hpp'])->toBe(11000.0);
    expect($products[0]['profit'])->toBe(21000.0);
    if ($route === 'reports.index') {
        expect($response->viewData('toppingSales')[0])->toMatchArray(['quantity' => 6, 'sales' => 12000.0, 'hpp' => 3000.0]);
    }
})->with(['dashboard.index', 'reports.index']);

test('dashboard recent transactions respect the selected period', function () {
    $this->travelTo(now()->setTime(10, 0));
    Order::factory()->paid()->create(['order_number' => 'OUTSIDE-PERIOD', 'created_at' => now()->subDays(10)]);

    $this->get(route('dashboard.index', ['period' => '7']))->assertOk()
        ->assertViewHas('totalOrders', 0)->assertDontSee('OUTSIDE-PERIOD');
});

test('sales periods and daily charts use Jakarta calendar days with UTC storage', function (string $route) {
    $this->travelTo(Carbon::parse('2026-10-21 00:30:00', 'Asia/Jakarta'));
    Order::factory()->paid()->create(['total' => 10000, 'created_at' => '2026-10-20 17:30:00']);
    Order::factory()->paid()->create(['total' => 90000, 'created_at' => '2026-10-21 17:01:00']);
    Order::factory()->paid()->create(['total' => 80000, 'created_at' => $route === 'dashboard.index' ? '2026-10-14 16:59:59' : '2026-10-20 16:59:59']);

    $response = $this->get(route($route))->assertOk()->assertViewHas('totalRevenue', 10000.0)->assertViewHas('totalOrders', 1);

    if ($route === 'dashboard.index') {
        expect($response->viewData('dailyRevenue')['2026-10-21'])->toBe(10000.0);
        expect($response->viewData('dailyOrders')['2026-10-21'])->toBe(1);
    }
})->with(['dashboard.index', 'reports.index']);

test('payment percentages describe revenue share rather than transaction count', function (string $route) {
    $this->travelTo(now()->setTime(10, 0));
    Order::factory()->paid()->create(['total' => 10000, 'payment_method' => PaymentMethod::Cash]);
    Order::factory()->paid()->count(2)->create(['total' => 45000, 'payment_method' => PaymentMethod::Qris]);

    $response = $this->get(route($route))->assertOk();

    expect($response->viewData('paymentAnalysis'))->toMatchArray([
        'cash' => ['total' => 10000.0, 'count' => 1, 'percentage' => 10.0],
        'qris' => ['total' => 90000.0, 'count' => 2, 'percentage' => 90.0],
        'total_transactions' => 3,
    ]);
})->with(['dashboard.index', 'reports.index']);

test('csv download respects payment filter and excludes cancelled and unpaid orders', function () {
    $this->travelTo(now()->setTime(10, 0));
    Order::factory()->paid()->create(['order_number' => 'INCLUDED-CASH']);
    Order::factory()->paid()->create(['order_number' => 'EXCLUDED-QRIS', 'payment_method' => PaymentMethod::Qris]);
    Order::factory()->paid()->create(['order_number' => 'EXCLUDED-CANCELLED', 'status' => OrderStatus::Cancelled]);
    Order::factory()->create(['order_number' => 'EXCLUDED-UNPAID']);

    $response = $this->get(route('reports.export', ['payment_method' => 'cash']))->assertOk()->assertDownload();

    expect($response->streamedContent())->toContain('INCLUDED-CASH')->not->toContain('EXCLUDED-');
});

test('pdf download contains a real PDF and preserves the selected period and payment filter', function () {
    $this->travelTo(now()->setTime(10, 0));
    Order::factory()->paid()->create(['order_number' => 'PDF-CASH']);

    $response = $this->get(route('reports.export-pdf', ['period' => '7days', 'payment_method' => 'cash']))
        ->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertDownload();

    expect($response->streamedContent())->toStartWith('%PDF-')->toContain('%%EOF');
});

test('missing HPP is distinguished from an explicitly recorded zero cost', function (?int $hpp, bool $incomplete) {
    $this->travelTo(now()->setTime(10, 0));
    $order = Order::factory()->paid()->create(['total' => 10000]);
    OrderItem::factory()->for($order)->create(['hpp' => $hpp, 'unit_price' => 10000, 'quantity' => 1, 'subtotal' => 10000]);

    $response = $this->get(route('dashboard.index'))->assertOk()
        ->assertViewHas('hasIncompleteHpp', $incomplete)->assertViewHas('totalHpp', 0.0)->assertViewHas('grossMargin', 100.0);

    if ($incomplete) {
        $response->assertSee('Estimasi — HPP belum lengkap');
    } else {
        $response->assertDontSee('Estimasi — HPP belum lengkap');
    }
})->with(['missing snapshot' => [null, true], 'recorded zero cost' => [0, false]]);

test('sales pages and compact PDF preserve all transaction details', function () {
    $this->travelTo(now()->setTime(10, 0));
    $product = Product::factory()->create(['name' => 'Mi Ayam Spesial dengan Nama Produk Panjang', 'price' => 10000, 'hpp' => 4000]);
    $topping = Topping::factory()->create(['name' => 'Extra Topping', 'price' => 2000, 'hpp' => 500]);
    $product->toppings()->attach($topping);
    for ($index = 1; $index <= 31; $index++) {
        $order = Order::factory()->paid()->create([
            'order_number' => sprintf('WC-REVIEW-%03d', $index), 'total' => 32000,
            'payment_method' => $index <= 6 ? PaymentMethod::Cash : PaymentMethod::Qris,
        ]);
        $item = OrderItem::factory()->for($order)->for($product)->create([
            'product_name' => $product->name, 'quantity' => 2, 'unit_price' => 10000, 'subtotal' => 32000, 'hpp' => $index === 1 ? null : 4000,
        ]);
        $item->toppings()->create(['topping_id' => $topping->id, 'topping_name' => $topping->name, 'price' => 2000, 'hpp' => 500, 'quantity' => 3]);
    }

    $dashboard = $this->get(route('dashboard.index'))->assertOk()->assertSee('7 hari terakhir')->assertSee('Omzet Periode')->assertDontSee('Omzet Hari Ini');
    $report = $this->get(route('reports.index', ['period' => '7days']))->assertOk()->assertSee('Ekspor PDF')->assertSee('Ekspor CSV');
    $pdf = app('dompdf.wrapper');
    $html = '';
    Pdf::shouldReceive('loadView')->once()->andReturnUsing(function (string $view, array $data) use ($pdf, &$html) {
        expect($data['totalOrders'])->toBe(31);
        expect($data['dailySales'])->toHaveCount(7);
        $html = view($view, $data)->render();

        return $pdf->loadHTML($html);
    });

    $response = $this->get(route('reports.export-pdf', ['period' => '7days']))->assertOk()->assertDownload();
    $bytes = $response->streamedContent();

    expect($bytes)->toStartWith('%PDF-');
    expect($pdf->getDomPDF()->getCanvas()->get_page_count())->toBeLessThanOrEqual(3);
    expect(substr_count($html, 'HPP belum lengkap:'))->toBe(1);
    foreach (['TOTAL OMZET', 'TOTAL HPP TERCATAT', 'LABA KOTOR', 'Metode Pembayaran', 'Ringkasan Harian', 'Produk Terjual', 'Topping Terjual', 'WC-REVIEW-001', 'WC-REVIEW-031'] as $text) {
        expect($html)->toContain($text);
    }

    if ($directory = getenv('SALES_BROWSER_FIXTURES')) {
        file_put_contents($directory.'/dashboard.html', $dashboard->getContent());
        file_put_contents($directory.'/reports.html', $report->getContent());
        file_put_contents($directory.'/report.pdf', $bytes);
        foreach (['welcome' => route('welcome'), 'profile' => route('settings.store-profile.edit'), 'catalog' => route('products.index'), 'pos' => route('pos.index'), 'receipt' => route('orders.receipt', $order)] as $page => $url) {
            file_put_contents($directory.'/'.$page.'.html', $this->get($url)->assertOk()->getContent());
        }
    }
});
