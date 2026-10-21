@extends('layouts.print')

@section('title', 'Struk '.$order->order_number)

@php
    $typeLabel = match ($order->type) {
        \App\Enums\OrderType::TA => 'Bungkus',
        \App\Enums\OrderType::DI => 'Dine-in',
        \App\Enums\OrderType::DLV => 'Delivery',
    };
    $statusService = app(\App\Services\OrderStatusService::class);
    $transitions = collect($statusService->allowedFrom($order->status))
        ->map(fn ($status): array => [
            'value' => $status->value,
            'label' => $statusService->label($status),
        ])
        ->all();
@endphp

@section('content')
    <div class="mx-auto w-full max-w-xs px-4">
        <div class="no-print mb-4 flex items-center justify-between gap-2">
            <a href="{{ route('orders.show', $order) }}" class="btn-ghost">Kembali</a>
            <button type="button" onclick="window.print()" class="btn-primary">Cetak</button>
        </div>

        <div class="receipt card mx-auto w-full max-w-[320px] p-5 font-mono text-xs leading-relaxed text-slate-800">
            <div class="text-center">
                <div class="mb-2 flex justify-center"><x-store-logo class="h-12 w-12" /></div>
                <p class="text-sm font-bold uppercase tracking-wide text-slate-900">{{ \App\Http\Controllers\SettingController::storeName() }}</p>
                <p class="text-[11px] text-slate-500">Struk Pesanan</p>
            </div>

            <div class="my-3 border-t border-dashed border-slate-300"></div>

            <dl class="space-y-1">
                <div class="flex justify-between gap-2">
                    <dt class="text-slate-500">No.</dt>
                    <dd class="font-semibold text-slate-900">{{ $order->order_number }}</dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt class="text-slate-500">Waktu</dt>
                    <dd>{{ $order->created_at->timezone(\App\Services\OrderService::LOCAL_TIMEZONE)->format('d/m/Y H:i') }}</dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt class="text-slate-500">Tipe</dt>
                    <dd>{{ $typeLabel }}</dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt class="text-slate-500">Status</dt>
                    <dd><x-order-status-badge :status="$order->status" /></dd>
                </div>
                @if ($order->customer_name)
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Pelanggan</dt>
                        <dd>{{ $order->customer_name }}</dd>
                    </div>
                @endif
                @if ($order->table_number)
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Meja</dt>
                        <dd>{{ $order->table_number }}</dd>
                    </div>
                @endif
                @if ($order->delivery_address)
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Alamat</dt>
                        <dd class="text-right">{{ $order->delivery_address }}</dd>
                    </div>
                @endif
            </dl>

            <div class="my-3 border-t border-dashed border-slate-300"></div>

            <div class="space-y-2">
                @foreach ($order->items as $item)
                    <div>
                        <div class="flex justify-between gap-2">
                            <span>{{ $item->quantity }}x {{ $item->product_name }}</span>
                            <span class="shrink-0"><x-money :value="$item->subtotal" /></span>
                        </div>
                        <p class="text-[11px] text-slate-500">@ <x-money :value="$item->unit_price" /></p>
                        @foreach ($item->toppings as $topping)
                            <p class="pl-3 text-[11px] text-slate-500">
                                + {{ $topping->topping_name }} x{{ $topping->quantity }} per produk
                                <span class="text-slate-400">(@ <x-money :value="$topping->price" />; <x-money :value="$topping->price * $topping->quantity * $item->quantity" /> total)</span>
                            </p>
                        @endforeach
                        @if ($item->notes)
                            <p class="pl-3 text-[11px] italic text-slate-500">{{ $item->notes }}</p>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="my-3 border-t border-dashed border-slate-300"></div>

            <div class="space-y-1">
                <div class="flex justify-between gap-2">
                    <span class="text-slate-500">Subtotal</span>
                    <span><x-money :value="$order->subtotal" /></span>
                </div>
                @if ((float) $order->discount_amount > 0)
                    <div class="flex justify-between gap-2"><span>Diskon{{ $order->discount_type === 'percentage' ? ' ('.number_format((float) $order->discount_value, 2, ',', '.').'%)' : '' }}</span><span>− <x-money :value="$order->discount_amount" /></span></div>
                @endif
                @if ((float) $order->additional_fee_amount > 0)
                    <div class="flex justify-between gap-2"><span>{{ $order->additional_fee_label }}</span><span><x-money :value="$order->additional_fee_amount" /></span></div>
                @endif
                <div class="flex justify-between gap-2 text-sm font-bold text-slate-900">
                    <span>Total</span>
                    <span><x-money :value="$order->total" /></span>
                </div>
                <div class="flex justify-between gap-2">
                    <span class="text-slate-500">Pembayaran</span>
                    <span>{{ $order->payment_status === \App\Enums\PaymentStatus::Paid ? 'Lunas' : 'Belum dibayar' }}</span>
                </div>
                @if ($order->payment_method)
                    <div class="flex justify-between gap-2">
                        <span class="text-slate-500">Metode</span>
                        <span>{{ $order->payment_method === \App\Enums\PaymentMethod::Qris ? 'QRIS' : 'Tunai' }}</span>
                    </div>
                @endif
                @if ($order->payment_status === \App\Enums\PaymentStatus::Paid)
                    @if ($order->amount_paid !== null)
                        <div class="flex justify-between gap-2">
                            <span class="text-slate-500">Dibayar</span>
                            <span><x-money :value="$order->amount_paid" /></span>
                        </div>
                    @endif
                    @if ($order->change !== null && (float) $order->change > 0)
                        <div class="flex justify-between gap-2">
                            <span class="text-slate-500">Kembalian</span>
                            <span><x-money :value="$order->change" /></span>
                        </div>
                    @endif
                @endif
            </div>

            <div class="my-3 border-t border-dashed border-slate-300"></div>

            <p class="text-center text-[11px] text-slate-500">Terima kasih telah berbelanja.</p>

            @if ($order->notes)
                <p class="mt-2 text-center text-[11px] text-slate-500">Catatan: {{ $order->notes }}</p>
            @endif
        </div>

        <div class="no-print mt-4 space-y-2">
            <h4 class="text-sm font-semibold text-slate-900">Ubah Status Pesanan</h4>
            @if (count($transitions) > 0)
                <div class="flex flex-col gap-2">
                    @foreach ($transitions as $transition)
                        <form
                            method="POST"
                            action="{{ route('orders.status', $order) }}"
                            onsubmit="return confirm('Ubah status pesanan menjadi {{ $transition['label'] }}?')"
                        >
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="{{ $transition['value'] }}">
                            <button
                                type="submit"
                                class="{{ $transition['value'] === 'cancelled' ? 'btn-danger w-full' : 'btn-primary w-full' }}"
                            >
                                {{ $transition['value'] === 'cancelled' ? 'Batalkan Pesanan' : 'Tandai '.$transition['label'] }}
                            </button>
                        </form>
                    @endforeach
                </div>
            @else
                <p class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500">
                    Pesanan sudah
                    <span class="font-medium text-slate-700">{{ app(\App\Services\OrderStatusService::class)->label($order->status) }}</span>
                    dan tidak dapat diubah lagi.
                </p>
            @endif
        </div>
    </div>
@endsection
