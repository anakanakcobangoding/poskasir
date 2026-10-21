<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderStatusService
{
    /**
     * Allowed status transitions keyed by the current status value.
     *
     * @return array<string, list<OrderStatus>>
     */
    public function transitions(): array
    {
        return [
            OrderStatus::New->value => [OrderStatus::Preparing, OrderStatus::Cancelled],
            OrderStatus::Preparing->value => [OrderStatus::Ready, OrderStatus::Cancelled],
            OrderStatus::Ready->value => [OrderStatus::Completed, OrderStatus::Cancelled],
            OrderStatus::Completed->value => [],
            OrderStatus::Cancelled->value => [],
        ];
    }

    /**
     * Statuses reachable from the given status.
     *
     * @return list<OrderStatus>
     */
    public function allowedFrom(OrderStatus $status): array
    {
        return $this->transitions()[$status->value] ?? [];
    }

    public function canTransition(OrderStatus $from, OrderStatus $to): bool
    {
        return in_array($to, $this->allowedFrom($from), true);
    }

    /**
     * Apply a validated transition, rejecting invalid or repeated moves.
     *
     * The order row is re-read with a row lock so two concurrent requests
     * cannot both apply a move from the same starting status.
     */
    public function transition(Order $order, OrderStatus $to): Order
    {
        return DB::transaction(function () use ($order, $to): Order {
            if ($to !== OrderStatus::Cancelled) {
                app(StoreOperationService::class)->assertOpenForTransaction();
            }

            $locked = Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $from = $locked->status;

            if (! $this->canTransition($from, $to)) {
                throw ValidationException::withMessages([
                    'status' => 'Status pesanan tidak dapat diubah dari "'.$this->label($from).'" ke "'.$this->label($to).'".',
                ]);
            }

            $locked->update(['status' => $to]);

            return $order->setRawAttributes($locked->getAttributes(), true);
        });
    }

    public function label(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::New => 'Baru',
            OrderStatus::Preparing => 'Diproses',
            OrderStatus::Ready => 'Siap',
            OrderStatus::Completed => 'Selesai',
            OrderStatus::Cancelled => 'Dibatalkan',
        };
    }

    public function badgeClasses(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::New => 'bg-slate-100 text-slate-700 ring-slate-500/20',
            OrderStatus::Preparing => 'bg-amber-50 text-amber-700 ring-amber-500/20',
            OrderStatus::Ready => 'bg-sky-50 text-sky-700 ring-sky-500/20',
            OrderStatus::Completed => 'bg-emerald-50 text-emerald-700 ring-emerald-500/20',
            OrderStatus::Cancelled => 'bg-red-50 text-red-700 ring-red-500/20',
        };
    }
}
