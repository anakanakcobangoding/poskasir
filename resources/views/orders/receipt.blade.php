@extends('layouts.print')

@section('title', 'Struk '.$order->order_number)

@php
    $typeLabel = match ($order->type) {
        \App\Enums\OrderType::TA => 'Bungkus',
        \App\Enums\OrderType::DI => 'Dine-in',
        \App\Enums\OrderType::DLV => 'Delivery',
    };
@endphp

@section('content')
    <div class="mx-auto w-full max-w-xs px-4">
        <div class="no-print mb-4 flex items-center justify-between gap-2">
            <a href="{{ route('orders.show', $order) }}" class="btn-ghost">Kembali</a>
            <button type="button" onclick="window.print()" class="btn-primary">Cetak</button>
        </div>

        <div class="receipt card mx-auto w-full max-w-[320px] p-5 font-mono text-xs leading-relaxed text-slate-800">
            <div class="text-center">
                <p class="text-sm font-bold uppercase tracking-wide text-slate-900">{{ config('app.name') }}</p>
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
                                + {{ $topping->topping_name }}
                                <span class="text-slate-400">(<x-money :value="$topping->price" />)</span>
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
                <div class="flex justify-between gap-2 text-sm font-bold text-slate-900">
                    <span>Total</span>
                    <span><x-money :value="$order->total" /></span>
                </div>
                <div class="flex justify-between gap-2">
                    <span class="text-slate-500">Pembayaran</span>
                    <span>{{ $order->payment_status === \App\Enums\PaymentStatus::Paid ? 'Lunas' : 'Belum dibayar' }}</span>
                </div>
            </div>

            <div class="my-3 border-t border-dashed border-slate-300"></div>

            <p class="text-center text-[11px] text-slate-500">Terima kasih telah berbelanja.</p>

            @if ($order->notes)
                <p class="mt-2 text-center text-[11px] text-slate-500">Catatan: {{ $order->notes }}</p>
            @endif
        </div>
    </div>
@endsection
