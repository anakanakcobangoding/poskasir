<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\Topping;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    /**
     * Local timezone used for the daily order number (WC-YYYYMMDD-###) and for
     * displaying order timestamps. Storage stays in the app timezone (UTC).
     */
    public const LOCAL_TIMEZONE = 'Asia/Jakarta';

    /**
     * Maximum attempts when the order number collides with a concurrent request.
     */
    private const MAX_ORDER_NUMBER_ATTEMPTS = 3;

    /**
     * Store an order with its items and toppings inside a single transaction.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Order
    {
        $attempt = 0;

        while (true) {
            try {
                return DB::transaction(fn (): Order => $this->persist($data), attempts: 3);
            } catch (QueryException $exception) {
                $attempt++;

                if ($attempt >= self::MAX_ORDER_NUMBER_ATTEMPTS || ! $this->isOrderNumberConflict($exception)) {
                    throw $exception;
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persist(array $data): Order
    {
        $type = OrderType::from($data['type']);

        $products = Product::query()
            ->available()
            ->whereIn('id', array_column($data['items'], 'product_id'))
            ->get()
            ->keyBy('id');

        $toppings = Topping::query()
            ->whereIn('id', $this->requestedToppingIds($data['items']))
            ->where('is_available', true)
            ->get()
            ->keyBy('id');

        $items = $this->prepareItems($data['items'], $products, $toppings);
        $subtotal = round($items->sum('subtotal'), 2);

        $order = Order::create([
            'order_number' => $this->nextOrderNumber(),
            'type' => $data['type'],
            'status' => OrderStatus::New,
            'customer_name' => $this->normalizeText($data['customer_name'] ?? null),
            'table_number' => $type === OrderType::DI ? $this->normalizeText($data['table_number'] ?? null) : null,
            'delivery_address' => $type === OrderType::DLV ? $this->normalizeText($data['delivery_address'] ?? null) : null,
            'subtotal' => $subtotal,
            'total' => $subtotal,
            'payment_status' => PaymentStatus::Unpaid,
            'notes' => $this->normalizeText($data['notes'] ?? null),
        ]);

        foreach ($items as $item) {
            $orderItem = $order->items()->create(Arr::except($item, ['toppings']));

            $orderItem->toppings()->createMany($item['toppings']);
        }

        return $order;
    }

    /**
     * Load and validate the requested catalog, then snapshot names and prices from the database.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @param  Collection<int, Product>  $products
     * @param  Collection<int, Topping>  $toppings
     * @return Collection<int, array<string, mixed>>
     */
    private function prepareItems(array $items, Collection $products, Collection $toppings): Collection
    {
        return collect($items)->map(function (array $item) use ($products, $toppings): array {
            $product = $products->get((int) $item['product_id']);

            if (! $product instanceof Product) {
                throw ValidationException::withMessages([
                    'items' => 'Ada produk yang tidak tersedia. Muat ulang halaman kasir lalu coba lagi.',
                ]);
            }

            $itemToppings = collect($item['topping_ids'] ?? [])->map(function ($toppingId) use ($toppings): Topping {
                $topping = $toppings->get((int) $toppingId);

                if (! $topping instanceof Topping) {
                    throw ValidationException::withMessages([
                        'items' => 'Ada topping yang tidak tersedia. Muat ulang halaman kasir lalu coba lagi.',
                    ]);
                }

                return $topping;
            });

            $quantity = (int) $item['quantity'];
            $unitPrice = (float) $product->price;
            $toppingPrice = $itemToppings->sum(fn (Topping $topping): float => (float) $topping->price);

            return [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'subtotal' => round(($unitPrice + $toppingPrice) * $quantity, 2),
                'notes' => $this->normalizeText($item['notes'] ?? null),
                'toppings' => $itemToppings->map(fn (Topping $topping): array => [
                    'topping_id' => $topping->id,
                    'topping_name' => $topping->name,
                    'price' => (float) $topping->price,
                    'quantity' => 1,
                ])->all(),
            ];
        });
    }

    /**
     * Collect every topping id requested by the cart.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, int>
     */
    private function requestedToppingIds(array $items): array
    {
        return collect($items)
            ->flatMap(fn (array $item): array => $item['topping_ids'] ?? [])
            ->map(fn ($toppingId): int => (int) $toppingId)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Build the next daily order number, e.g. WC-20260301-001.
     *
     * The daily sequence is read with a row lock where the driver supports it
     * (MySQL); uniqueness is ultimately guaranteed by the unique index on
     * order_number plus the bounded retry in create().
     */
    private function nextOrderNumber(): string
    {
        $prefix = 'WC-'.now(self::LOCAL_TIMEZONE)->format('Ymd').'-';

        $lastSequence = Order::query()
            ->where('order_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->pluck('order_number')
            ->map(fn (string $orderNumber): int => (int) substr($orderNumber, strlen($prefix)))
            ->max() ?? 0;

        return $prefix.str_pad((string) ($lastSequence + 1), 3, '0', STR_PAD_LEFT);
    }

    /**
     * Determine whether the failed query was an order_number uniqueness violation.
     */
    private function isOrderNumberConflict(QueryException $exception): bool
    {
        return (string) $exception->getCode() === '23000'
            && str_contains($exception->getMessage(), 'order_number');
    }

    /**
     * Normalize optional text so blank input is stored as null.
     */
    private function normalizeText(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
