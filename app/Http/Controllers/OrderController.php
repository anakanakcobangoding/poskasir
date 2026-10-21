<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Http\Requests\IndexOrderRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\OrderStatusService;
use App\Services\StoreOperationService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

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
        $data = $request->validated();
        $token = $request->header('Idempotency-Key');

        if ($token === null) {
            return $this->createdResponse($this->orders->create($data));
        }

        if (! Str::isUuid($token)) {
            throw ValidationException::withMessages(['checkout_token' => 'Token checkout tidak valid. Buka ulang checkout.']);
        }

        $cache = Cache::store('database');
        $key = 'checkout:'.$request->user()->getAuthIdentifier().':'.$token;
        $fingerprint = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));

        try {
            return $cache->lock($key.':lock', 120)->block(3, function () use ($cache, $key, $fingerprint, $data): JsonResponse {
                return DB::transaction(function () use ($cache, $key, $fingerprint, $data): JsonResponse {
                    app(StoreOperationService::class)->assertOpenForTransaction();
                    $previous = $cache->get($key);

                    if ($previous !== null) {
                        abort_unless(hash_equals($previous['fingerprint'], $fingerprint), 409, 'Checkout ini sudah tersimpan dengan data berbeda. Periksa daftar pesanan sebelum membuat transaksi baru.');

                        return $this->createdResponse(Order::findOrFail($previous['order_id']));
                    }

                    $order = $this->orders->create($data);

                    if (! $cache->put($key, ['fingerprint' => $fingerprint, 'order_id' => $order->id], now()->addDay())) {
                        throw new RuntimeException('Checkout replay protection could not be stored.');
                    }

                    return $this->createdResponse($order);
                });
            });
        } catch (LockTimeoutException) {
            return response()->json(['message' => 'Checkout masih diproses. Tunggu sebentar lalu coba lagi.'], 409);
        }
    }

    private function createdResponse(Order $order): JsonResponse
    {
        return response()->json([
            'message' => 'Pesanan '.$order->order_number.' berhasil disimpan.',
            'order_number' => $order->order_number,
            'id' => $order->id,
            'receipt_url' => route('orders.receipt', $order, absolute: false),
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
