<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemTopping;
use App\Services\OrderService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $period = in_array(request()->query('period'), ['7', '30'], true) ? request()->query('period') : '7';
        $days = (int) $period;

        $startDate = Carbon::now(OrderService::LOCAL_TIMEZONE)->subDays($days - 1)->startOfDay();
        $endDate = Carbon::now(OrderService::LOCAL_TIMEZONE)->endOfDay();

        // Base query for valid orders (paid)
        $validOrdersQuery = Order::query()
            ->where('payment_status', PaymentStatus::Paid)
            ->where('status', '!=', OrderStatus::Cancelled)
            ->whereBetween('created_at', [$startDate->copy()->setTimezone(config('app.timezone')), $endDate->copy()->setTimezone(config('app.timezone'))]);

        // Summary stats
        $totalRevenue = (float) $validOrdersQuery->sum('total');
        $totalOrders = (int) $validOrdersQuery->count();
        $avgOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

        // HPP calculations
        $hppData = $this->calculateHpp($startDate, $endDate);
        $totalHpp = $hppData['total_hpp'];
        $totalProductHpp = $hppData['product_hpp'];
        $totalToppingHpp = $hppData['topping_hpp'];
        $productsWithoutHpp = $hppData['products_without_hpp'];
        $toppingsWithoutHpp = $hppData['toppings_without_hpp'];
        $hasIncompleteHpp = $productsWithoutHpp > 0 || $toppingsWithoutHpp > 0;

        $grossProfit = $totalRevenue - $totalHpp;
        $grossMargin = $totalRevenue > 0 ? ($grossProfit / $totalRevenue) * 100 : 0;

        // Daily revenue chart data
        $dailyRevenue = $this->getDailyRevenue($startDate, $endDate);
        $dailyOrders = $this->getDailyOrders($startDate, $endDate);

        // Payment method analysis
        $paymentAnalysis = $this->getPaymentAnalysis($startDate, $endDate);

        // Top products
        $topProducts = $this->getTopProducts($startDate, $endDate);

        // Recent transactions
        $recentTransactions = Order::query()
            ->where('payment_status', PaymentStatus::Paid)
            ->where('status', '!=', OrderStatus::Cancelled)
            ->whereBetween('created_at', [$startDate->copy()->setTimezone(config('app.timezone')), $endDate->copy()->setTimezone(config('app.timezone'))])
            ->with(['items.toppings'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('dashboard.index', compact(
            'period',
            'days',
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
            'dailyRevenue',
            'dailyOrders',
            'paymentAnalysis',
            'topProducts',
            'recentTransactions'
        ));
    }

    /**
     * Calculate HPP for a date range
     *
     * @return array{total_hpp: float, product_hpp: float, topping_hpp: float, products_without_hpp: int, toppings_without_hpp: int}
     */
    private function calculateHpp(Carbon $startDate, Carbon $endDate): array
    {
        $orderItems = OrderItem::query()
            ->whereHas('order', function ($query) use ($startDate, $endDate) {
                $query->where('payment_status', PaymentStatus::Paid)
                    ->where('status', '!=', OrderStatus::Cancelled)
                    ->whereBetween('created_at', [$startDate->copy()->setTimezone(config('app.timezone')), $endDate->copy()->setTimezone(config('app.timezone'))]);
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
     * Get daily revenue for chart
     *
     * @return array<string, float>
     */
    private function getDailyRevenue(Carbon $startDate, Carbon $endDate): array
    {
        $results = Order::query()
            ->where('payment_status', PaymentStatus::Paid)
            ->where('status', '!=', OrderStatus::Cancelled)
            ->whereBetween('created_at', [$startDate->copy()->setTimezone(config('app.timezone')), $endDate->copy()->setTimezone(config('app.timezone'))])
            ->get(['created_at', 'total'])
            ->groupBy(fn (Order $order): string => $order->created_at->timezone(OrderService::LOCAL_TIMEZONE)->format('Y-m-d'))
            ->map(fn (Collection $orders): float => (float) $orders->sum('total'))
            ->toArray();

        $data = [];
        $current = $startDate->copy();
        while ($current <= $endDate) {
            $dateKey = $current->format('Y-m-d');
            $data[$dateKey] = isset($results[$dateKey]) ? (float) $results[$dateKey] : 0.0;
            $current->addDay();
        }

        return $data;
    }

    /**
     * Get daily orders count for chart
     *
     * @return array<string, int>
     */
    private function getDailyOrders(Carbon $startDate, Carbon $endDate): array
    {
        $results = Order::query()
            ->where('payment_status', PaymentStatus::Paid)
            ->where('status', '!=', OrderStatus::Cancelled)
            ->whereBetween('created_at', [$startDate->copy()->setTimezone(config('app.timezone')), $endDate->copy()->setTimezone(config('app.timezone'))])
            ->get(['created_at'])
            ->groupBy(fn (Order $order): string => $order->created_at->timezone(OrderService::LOCAL_TIMEZONE)->format('Y-m-d'))
            ->map(fn (Collection $orders): int => $orders->count())
            ->toArray();

        $data = [];
        $current = $startDate->copy();
        while ($current <= $endDate) {
            $dateKey = $current->format('Y-m-d');
            $data[$dateKey] = isset($results[$dateKey]) ? (int) $results[$dateKey] : 0;
            $current->addDay();
        }

        return $data;
    }

    /**
     * Get payment method analysis
     */
    private function getPaymentAnalysis(Carbon $startDate, Carbon $endDate): array
    {
        $cashTotal = (float) Order::query()
            ->where('payment_status', PaymentStatus::Paid)
            ->where('status', '!=', OrderStatus::Cancelled)
            ->where('payment_method', PaymentMethod::Cash)
            ->whereBetween('created_at', [$startDate->copy()->setTimezone(config('app.timezone')), $endDate->copy()->setTimezone(config('app.timezone'))])
            ->sum('total');

        $qrisTotal = (float) Order::query()
            ->where('payment_status', PaymentStatus::Paid)
            ->where('status', '!=', OrderStatus::Cancelled)
            ->where('payment_method', PaymentMethod::Qris)
            ->whereBetween('created_at', [$startDate->copy()->setTimezone(config('app.timezone')), $endDate->copy()->setTimezone(config('app.timezone'))])
            ->sum('total');

        $cashCount = (int) Order::query()
            ->where('payment_status', PaymentStatus::Paid)
            ->where('status', '!=', OrderStatus::Cancelled)
            ->where('payment_method', PaymentMethod::Cash)
            ->whereBetween('created_at', [$startDate->copy()->setTimezone(config('app.timezone')), $endDate->copy()->setTimezone(config('app.timezone'))])
            ->count();

        $qrisCount = (int) Order::query()
            ->where('payment_status', PaymentStatus::Paid)
            ->where('status', '!=', OrderStatus::Cancelled)
            ->where('payment_method', PaymentMethod::Qris)
            ->whereBetween('created_at', [$startDate->copy()->setTimezone(config('app.timezone')), $endDate->copy()->setTimezone(config('app.timezone'))])
            ->count();

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

    /**
     * Get top selling products
     *
     * @return array<int, array<string, mixed>>
     */
    private function getTopProducts(Carbon $startDate, Carbon $endDate): array
    {
        $products = OrderItem::query()
            ->whereHas('order', function ($query) use ($startDate, $endDate): void {
                $query->where('payment_status', PaymentStatus::Paid)
                    ->where('status', '!=', OrderStatus::Cancelled)
                    ->whereBetween('created_at', [$startDate->copy()->setTimezone(config('app.timezone')), $endDate->copy()->setTimezone(config('app.timezone'))]);
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

        return $products->take(10)->values()->all();
    }
}
