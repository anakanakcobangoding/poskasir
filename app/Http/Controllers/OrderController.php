<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orders,
    ) {}

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = $this->orders->create($request->validated());

        return response()->json([
            'message' => 'Pesanan '.$order->order_number.' berhasil disimpan.',
            'order_number' => $order->order_number,
        ], 201);
    }
}
