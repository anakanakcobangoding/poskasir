<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Http\Requests\IndexOrderRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\OrderStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly OrderStatusService $statuses,
    ) {}

    public function index(IndexOrderRequest $request): View
    {
        $filters = $request->validated();
        $status = $filters['status'] ?? null;
        $type = $filters['type'] ?? null;

        $orders = Order::query()
            ->withCount('items')
            ->when($status === null, fn ($query) => $query->whereIn('status', [
                OrderStatus::New,
                OrderStatus::Preparing,
                OrderStatus::Ready,
            ]))
            ->when($status !== null && $status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($type !== null, fn ($query) => $query->where('type', $type))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('orders.index', [
            'orders' => $orders,
            'status' => $status,
            'type' => $type,
        ]);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = $this->orders->create($request->validated());

        return response()->json([
            'message' => 'Pesanan '.$order->order_number.' berhasil disimpan.',
            'order_number' => $order->order_number,
        ], 201);
    }

    public function show(Order $order): View
    {
        $order->load(['items.toppings']);

        $transitions = collect($this->statuses->allowedFrom($order->status))
            ->map(fn (OrderStatus $status): array => [
                'value' => $status->value,
                'label' => $this->statuses->label($status),
            ])
            ->all();

        return view('orders.show', [
            'order' => $order,
            'transitions' => $transitions,
        ]);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): RedirectResponse
    {
        $this->statuses->transition($order, OrderStatus::from($request->validated('status')));

        return redirect()
            ->route('orders.show', $order)
            ->with('success', 'Status pesanan '.$order->order_number.' diperbarui menjadi "'.$this->statuses->label($order->status).'".');
    }

    public function receipt(Order $order): View
    {
        $order->load(['items.toppings']);

        return view('orders.receipt', compact('order'));
    }
}
