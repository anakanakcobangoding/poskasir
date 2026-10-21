<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemTopping;
use App\Services\OrderService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $period = $request->query('period', 'today');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        [$resolvedStart, $resolvedEnd] = $this->resolveDateRange($period, $startDate, $endDate);

        $validOrdersQuery = Order::query()
            ->where('payment_status', PaymentStatus::Paid)
            ->where('status', '!=', OrderStatus::Cancelled)
            ->whereBetween('created_at', [$resolvedStart->copy()->setTimezone(config('app.timezone')), $resolvedEnd->copy()->setTimezone(config('app.timezone'))])
            ->when($request->filled('payment_method'), function ($query) use ($request) {
                $query->where('payment_method', $request->payment_method);
            });

        // Summary stats
        $totalRevenue = (float) $validOrdersQuery->sum('total');
        $totalOrders = (int) $validOrdersQuery->count();
        $avgOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

        // HPP calculations
        $hppData = $this->calculateHpp($resolvedStart, $resolvedEnd, $request->query('payment_method'));
        $totalHpp = $hppData['total_hpp'];
        $totalProductHpp = $hppData['product_hpp'];
        $totalToppingHpp = $hppData['topping_hpp'];
        $productsWithoutHpp = $hppData['products_without_hpp'];
        $toppingsWithoutHpp = $hppData['toppings_without_hpp'];
        $hasIncompleteHpp = $productsWithoutHpp > 0 || $toppingsWithoutHpp > 0;

        $grossProfit = $totalRevenue - $totalHpp;
        $grossMargin = $totalRevenue > 0 ? ($grossProfit / $totalRevenue) * 100 : 0;

        // Product sales detail
        $productSales = $this->getProductSales($resolvedStart, $resolvedEnd, $request->query('payment_method'));

        // Topping sales detail
        $toppingSales = $this->getToppingSales($resolvedStart, $resolvedEnd, $request->query('payment_method'));

        // Payment method analysis
        $paymentAnalysis = $this->getPaymentAnalysis($resolvedStart, $resolvedEnd, $request->query('payment_method'));

        // Transaction history with pagination
        $transactions = Order::query()
            ->where('payment_status', PaymentStatus::Paid)
            ->where('status', '!=', OrderStatus::Cancelled)
            ->whereBetween('created_at', [$resolvedStart->copy()->setTimezone(config('app.timezone')), $resolvedEnd->copy()->setTimezone(config('app.timezone'))])
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->where('order_number', 'like', '%'.$request->search.'%');
            })
            ->when($request->filled('payment_method'), function ($query) use ($request) {
                $query->where('payment_method', $request->payment_method);
            })
            ->with(['items.toppings'])
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('reports.index', compact(
            'period',
            'startDate',
            'endDate',
            'resolvedStart',
            'resolvedEnd',
            'totalRevenue',
            'totalOrders',
            'avgOrderValue',
            'totalHpp',
            'totalProductHpp',
            'totalToppingHpp',
            'grossProfit',
            'grossMargin',
            'hasIncompleteHpp',
            'productsWithoutHpp',
            'toppingsWithoutHpp',
            'productSales',
            'toppingSales',
            'paymentAnalysis',
            'transactions'
        ));
    }

    public function export(Request $request): StreamedResponse
    {
        $period = $request->query('period', 'today');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        [$resolvedStart, $resolvedEnd] = $this->resolveDateRange($period, $startDate, $endDate);

        $filename = 'laporan-penjualan-'.$period.'-'.$resolvedStart->format('Ymd').'-'.$resolvedEnd->format('Ymd').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $paymentMethod = $request->query('payment_method');

        $callback = function () use ($resolvedStart, $resolvedEnd, $paymentMethod): void {
            $handle = fopen('php://output', 'w');

            // BOM for UTF-8
            fwrite($handle, "\xEF\xBB\xBF");

            // Headers
            fputcsv($handle, [
                'No',
                'Nomor Transaksi',
                'Waktu',
                'Tipe Pesanan',
                'Total',
                'Metode Pembayaran',
                'Status Pembayaran',
            ]);

            $orders = Order::query()
                ->where('payment_status', PaymentStatus::Paid)
                ->where('status', '!=', OrderStatus::Cancelled)
                ->whereBetween('created_at', [$resolvedStart->copy()->setTimezone(config('app.timezone')), $resolvedEnd->copy()->setTimezone(config('app.timezone'))])
                ->when($paymentMethod, fn ($query) => $query->where('payment_method', $paymentMethod))
                ->orderByDesc('created_at')
                ->get();

            $rowNum = 1;
            foreach ($orders as $order) {
                fputcsv($handle, [
                    $rowNum++,
                    $order->order_number,
                    $order->created_at->timezone(OrderService::LOCAL_TIMEZONE)->format('d/m/Y H:i'),
                    $order->type->label() ?? $order->type->value,
                    $order->total,
                    $order->payment_method?->value ?? '-',
                    $order->payment_status->label() ?? $order->payment_status->value,
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportPdf(Request $request): StreamedResponse
    {
        $period = $request->query('period', 'today');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        [$resolvedStart, $resolvedEnd] = $this->resolveDateRange($period, $startDate, $endDate);
        $paymentMethod = $request->query('payment_method');

        // Build all data needed for PDF (same as index method)
        $validOrdersQuery = Order::query()
            ->where('payment_status', PaymentStatus::Paid)
            ->where('status', '!=', OrderStatus::Cancelled)
            ->whereBetween('created_at', [$resolvedStart->copy()->setTimezone(config('app.timezone')), $resolvedEnd->copy()->setTimezone(config('app.timezone'))])
            ->when($paymentMethod, function ($query) use ($paymentMethod) {
                $query->where('payment_method', $paymentMethod);
            });

        $totalRevenue = (float) $validOrdersQuery->sum('total');
        $totalOrders = (int) $validOrdersQuery->count();
        $avgOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

        $hppData = $this->calculateHpp($resolvedStart, $resolvedEnd, $paymentMethod);
        $totalHpp = $hppData['total_hpp'];
        $totalProductHpp = $hppData['product_hpp'];
        $totalToppingHpp = $hppData['topping_hpp'];
        $productsWithoutHpp = $hppData['products_without_hpp'];
        $toppingsWithoutHpp = $hppData['toppings_without_hpp'];
        $hasIncompleteHpp = $productsWithoutHpp > 0 || $toppingsWithoutHpp > 0;

        $grossProfit = $totalRevenue - $totalHpp;
        $grossMargin = $totalRevenue > 0 ? ($grossProfit / $totalRevenue) * 100 : 0;

        $productSales = $this->getProductSales($resolvedStart, $resolvedEnd, $paymentMethod);
        $toppingSales = $this->getToppingSales($resolvedStart, $resolvedEnd, $paymentMethod);
        $paymentAnalysis = $this->getPaymentAnalysis($resolvedStart, $resolvedEnd, $paymentMethod);

        // All transactions (no pagination for PDF)
        $transactions = Order::query()
            ->where('payment_status', PaymentStatus::Paid)
            ->where('status', '!=', OrderStatus::Cancelled)
            ->whereBetween('created_at', [$resolvedStart->copy()->setTimezone(config('app.timezone')), $resolvedEnd->copy()->setTimezone(config('app.timezone'))])
            ->when($paymentMethod, function ($query) use ($paymentMethod) {
                $query->where('payment_method', $paymentMethod);
            })
            ->with(['items.toppings'])
            ->orderByDesc('created_at')
            ->get();

        $dailyTransactions = $transactions->groupBy(fn (Order $order): string => $order->created_at->timezone(OrderService::LOCAL_TIMEZONE)->format('Y-m-d'));
        $dailySales = [];

        for ($day = $resolvedStart->copy(); $day <= $resolvedEnd; $day->addDay()) {
            $orders = $dailyTransactions->get($day->format('Y-m-d'), collect());
            $dailySales[] = ['date' => $day->format('d/m/Y'), 'count' => $orders->count(), 'revenue' => (float) $orders->sum('total')];
        }

        $filename = 'laporan-penjualan-'.$period.'-'.$resolvedStart->format('Ymd').'-'.$resolvedEnd->format('Ymd').'.pdf';

        $pdf = Pdf::loadView('reports.pdf', compact(
            'period',
            'startDate',
            'endDate',
            'resolvedStart',
            'resolvedEnd',
            'totalRevenue',
            'totalOrders',
            'avgOrderValue',
            'totalHpp',
            'totalProductHpp',
            'totalToppingHpp',
            'grossProfit',
            'grossMargin',
            'hasIncompleteHpp',
            'productsWithoutHpp',
            'toppingsWithoutHpp',
            'productSales',
            'toppingSales',
            'paymentAnalysis',
            'transactions',
            'paymentMethod',
            'dailySales'
        ))->setPaper('a4', 'landscape');

        return response()->streamDownload(function () use ($pdf) {
            $pdf->render();
            $document = $pdf->getDomPDF();
            $canvas = $document->getCanvas();
            $canvas->page_text($canvas->get_width() - 110, $canvas->get_height() - 22, 'Halaman {PAGE_NUM} / {PAGE_COUNT}', $document->getFontMetrics()->getFont('DejaVu Sans'), 7, [0.39, 0.45, 0.55]);

            echo $pdf->output();
        }, $filename, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Resolve date range based on period preset or custom dates
     *
     * @return array{Carbon, Carbon}
     */
    private function resolveDateRange(string $period, ?string $startDate, ?string $endDate): array
    {
        $tz = OrderService::LOCAL_TIMEZONE;

        if ($period === 'custom' && $startDate && $endDate) {
            $start = Carbon::createFromFormat('Y-m-d', $startDate, $tz)->startOfDay();
            $end = Carbon::createFromFormat('Y-m-d', $endDate, $tz)->endOfDay();

            if ($start > $end) {
                $start = $end->copy()->startOfDay();
            }

            return [$start, $end];
        }

        $now = Carbon::now($tz);

        return match ($period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            '7days' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            '30days' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            'this_month' => [$now->copy()->startOfMonth()->startOfDay(), $now->copy()->endOfMonth()->endOfDay()],
            default => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
        };
    }

    /**
     * Calculate HPP for a date range
     *
     * @return array{total_hpp: float, product_hpp: float, topping_hpp: float, products_without_hpp: int, toppings_without_hpp: int}
     */
    private function calculateHpp(Carbon $startDate, Carbon $endDate, ?string $paymentMethod = null): array
    {
        $orderItems = OrderItem::query()
            ->whereHas('order', function ($query) use ($startDate, $endDate, $paymentMethod) {
                $query->where('payment_status', PaymentStatus::Paid)
                    ->where('status', '!=', OrderStatus::Cancelled)
                    ->whereBetween('created_at', [$startDate->copy()->setTimezone(config('app.timezone')), $endDate->copy()->setTimezone(config('app.timezone'))])
                    ->when($paymentMethod, function ($q) use ($paymentMethod) {
                        $q->where('payment_method', $paymentMethod);
                    });
            })
            ->with(['toppings'])
            ->get();

        $productHpp = 0.0;
        $toppingHpp = 0.0;
        $productsWithoutHpp = 0;
        $toppingsWithoutHpp = 0;
        $seenProductsWithoutHpp = [];
        $seenToppingsWithoutHpp = [];

        foreach ($orderItems as $item) {
            $quantity = $item->quantity;

            if ($item->hpp !== null) {
                $productHpp += (float) $item->hpp * $quantity;
            } else {
                $productHpp += 0;
                if (! in_array($item->product_id, $seenProductsWithoutHpp)) {
                    $seenProductsWithoutHpp[] = $item->product_id;
                    $productsWithoutHpp++;
                }
            }

            foreach ($item->toppings as $topping) {
                $toppingQty = $topping->quantity * $quantity;
                if ($topping->hpp !== null) {
                    $toppingHpp += (float) $topping->hpp * $toppingQty;
                } else {
                    $toppingHpp += 0;
                    if (! in_array($topping->topping_id, $seenToppingsWithoutHpp)) {
                        $seenToppingsWithoutHpp[] = $topping->topping_id;
                        $toppingsWithoutHpp++;
                    }
                }
            }
        }

        return [
            'total_hpp' => $productHpp + $toppingHpp,
            'product_hpp' => $productHpp,
            'topping_hpp' => $toppingHpp,
            'products_without_hpp' => $productsWithoutHpp,
            'toppings_without_hpp' => $toppingsWithoutHpp,
        ];
    }

    /**
     * Get product sales detail
     *
     * @return array<int, array<string, mixed>>
     */
    private function getProductSales(Carbon $startDate, Carbon $endDate, ?string $paymentMethod = null): array
    {
        $products = OrderItem::query()
            ->whereHas('order', function ($query) use ($startDate, $endDate, $paymentMethod): void {
                $query->where('payment_status', PaymentStatus::Paid)
                    ->where('status', '!=', OrderStatus::Cancelled)
                    ->whereBetween('created_at', [$startDate->copy()->setTimezone(config('app.timezone')), $endDate->copy()->setTimezone(config('app.timezone'))])
                    ->when($paymentMethod, fn ($query) => $query->where('payment_method', $paymentMethod));
            })
            ->with('toppings')
            ->get()
            ->groupBy(fn (OrderItem $item): string => json_encode([$item->product_id, $item->product_name]))
            ->map(function (Collection $items): array {
                $hasIncompleteHpp = $items->contains(fn (OrderItem $item): bool => $item->hpp === null || $item->toppings->contains('hpp', null));
                $sales = (float) $items->sum('subtotal');
                $hpp = $hasIncompleteHpp ? null : (float) $items->sum(fn (OrderItem $item): float => ((float) $item->hpp + $item->toppings->sum(fn (OrderItemTopping $topping): float => (float) $topping->hpp * $topping->quantity)) * $item->quantity
                );

                return [
                    'name' => $items->first()->product_name,
                    'quantity' => (int) $items->sum('quantity'),
                    'sales' => $sales,
                    'hpp' => $hpp,
                    'profit' => $hpp !== null ? $sales - $hpp : null,
                    'has_incomplete_hpp' => $hasIncompleteHpp,
                ];
            })
            ->sortByDesc('quantity');

        return $products->values()->all();
    }

    /**
     * Get topping sales detail
     *
     * @return array<int, array<string, mixed>>
     */
    private function getToppingSales(Carbon $startDate, Carbon $endDate, ?string $paymentMethod = null): array
    {
        $results = OrderItemTopping::query()
            ->whereHas('orderItem.order', function ($query) use ($startDate, $endDate, $paymentMethod) {
                $query->where('payment_status', PaymentStatus::Paid)
                    ->where('status', '!=', OrderStatus::Cancelled)
                    ->whereBetween('created_at', [$startDate->copy()->setTimezone(config('app.timezone')), $endDate->copy()->setTimezone(config('app.timezone'))])
                    ->when($paymentMethod, function ($q) use ($paymentMethod) {
                        $q->where('payment_method', $paymentMethod);
                    });
            })
            ->join('order_items', 'order_items.id', '=', 'order_item_toppings.order_item_id')
            ->selectRaw('topping_id, topping_name, SUM(order_item_toppings.quantity * order_items.quantity) as total_qty, SUM(order_item_toppings.price * order_item_toppings.quantity * order_items.quantity) as total_sales, SUM(CASE WHEN order_item_toppings.hpp IS NOT NULL THEN order_item_toppings.hpp * order_item_toppings.quantity * order_items.quantity ELSE 0 END) as total_hpp, COUNT(CASE WHEN order_item_toppings.hpp IS NULL THEN 1 END) as null_hpp_count')
            ->groupBy('topping_id', 'topping_name')
            ->orderByDesc('total_qty')
            ->get()
            ->map(function ($item) {
                $hasHpp = $item->null_hpp_count == 0;

                return [
                    'name' => $item->topping_name,
                    'quantity' => (int) $item->total_qty,
                    'sales' => (float) $item->total_sales,
                    'hpp' => $hasHpp ? (float) $item->total_hpp : null,
                    'profit' => $hasHpp ? (float) $item->total_sales - (float) $item->total_hpp : null,
                    'has_incomplete_hpp' => ! $hasHpp,
                ];
            })
            ->toArray();

        return $results;
    }

    /**
     * Get payment method analysis
     */
    private function getPaymentAnalysis(Carbon $startDate, Carbon $endDate, ?string $paymentMethod = null): array
    {
        $baseQuery = Order::query()
            ->where('payment_status', PaymentStatus::Paid)
            ->where('status', '!=', OrderStatus::Cancelled)
            ->whereBetween('created_at', [$startDate->copy()->setTimezone(config('app.timezone')), $endDate->copy()->setTimezone(config('app.timezone'))]);

        if ($paymentMethod === 'cash') {
            $cashTotal = (float) (clone $baseQuery)->where('payment_method', PaymentMethod::Cash)->sum('total');
            $cashCount = (int) (clone $baseQuery)->where('payment_method', PaymentMethod::Cash)->count();
            $qrisTotal = 0.0;
            $qrisCount = 0;
        } elseif ($paymentMethod === 'qris') {
            $qrisTotal = (float) (clone $baseQuery)->where('payment_method', PaymentMethod::Qris)->sum('total');
            $qrisCount = (int) (clone $baseQuery)->where('payment_method', PaymentMethod::Qris)->count();
            $cashTotal = 0.0;
            $cashCount = 0;
        } else {
            $cashTotal = (float) (clone $baseQuery)->where('payment_method', PaymentMethod::Cash)->sum('total');
            $qrisTotal = (float) (clone $baseQuery)->where('payment_method', PaymentMethod::Qris)->sum('total');
            $cashCount = (int) (clone $baseQuery)->where('payment_method', PaymentMethod::Cash)->count();
            $qrisCount = (int) (clone $baseQuery)->where('payment_method', PaymentMethod::Qris)->count();
        }

        $totalPayment = $cashTotal + $qrisTotal;
        $totalCount = $cashCount + $qrisCount;

        return [
            'cash' => [
                'total' => $cashTotal,
                'count' => $cashCount,
                'percentage' => $totalPayment > 0 ? round(($cashTotal / $totalPayment) * 100, 1) : 0,
            ],
            'qris' => [
                'total' => $qrisTotal,
                'count' => $qrisCount,
                'percentage' => $totalPayment > 0 ? round(($qrisTotal / $totalPayment) * 100, 1) : 0,
            ],
            'total_transactions' => $totalCount,
        ];
    }
}
