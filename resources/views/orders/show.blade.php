@extends('layouts.app')

@section('title', 'Detail Pesanan')

@section('content')
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ route('orders.index') }}" class="text-xs font-medium text-slate-500 hover:text-slate-700">&larr; Kembali ke daftar pesanan</a>
            <h2 class="mt-1 text-lg font-semibold text-slate-900">{{ $order->order_number }}</h2>
            <p class="text-sm text-slate-500">
                {{ $order->created_at->timezone(\App\Services\OrderService::LOCAL_TIMEZONE)->format('d/m/Y H:i') }}
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <x-order-type-badge :type="$order->type" />
            <x-order-status-badge :status="$order->status" />
            <a href="{{ route('orders.receipt', $order) }}" class="btn-ghost">Lihat Struk</a>
        </div>
    </div>

    <div class="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="card overflow-hidden">
            <div class="border-b border-slate-100 px-5 py-4">
                <h3 class="text-sm font-semibold text-slate-900">Item Pesanan</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="th">Produk</th>
                            <th class="th text-center">Qty</th>
                            <th class="th text-right">Harga</th>
                            <th class="th text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($order->items as $item)
                            <tr>
                                <td class="td">
                                    <p class="font-medium text-slate-900">{{ $item->product_name }}</p>
                                    @if ($item->toppings->isNotEmpty())
                                        <ul class="mt-1 space-y-0.5">
                                            @foreach ($item->toppings as $topping)
                                                <li class="text-xs text-slate-500">
                                                    + {{ $topping->topping_name }} x{{ $topping->quantity }} per produk
                                                    <span class="text-slate-400">(@ <x-money :value="$topping->price" />; <x-money :value="$topping->price * $topping->quantity * $item->quantity" /> total)</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                    @if ($item->notes)
                                        <p class="mt-1 text-xs italic text-slate-500">Catatan: {{ $item->notes }}</p>
                                    @endif
                                </td>
                                <td class="td text-center text-slate-600">{{ $item->quantity }}</td>
                                <td class="td text-right text-slate-600"><x-money :value="$item->unit_price" /></td>
                                <td class="td text-right font-medium text-slate-700"><x-money :value="$item->subtotal" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t border-slate-100">
                        <tr>
                            <td colspan="3" class="td text-right font-semibold text-slate-900">Total</td>
                            <td class="td text-right text-base font-bold text-emerald-600"><x-money :value="$order->total" /></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="space-y-4">
            <div class="card space-y-3 p-5">
                <h3 class="text-sm font-semibold text-slate-900">Ubah Status</h3>
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
                                >{{ $transition['value'] === 'cancelled' ? 'Batalkan Pesanan' : 'Tandai '.$transition['label'] }}</button>
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

            <div class="card space-y-3 p-5 text-sm">
                <h3 class="text-sm font-semibold text-slate-900">Informasi</h3>
                <dl class="space-y-2">
                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-slate-500">Pelanggan</dt>
                        <dd class="text-right font-medium text-slate-800">{{ $order->customer_name ?: '—' }}</dd>
                    </div>
                    @if ($order->type === \App\Enums\OrderType::DI)
                        <div class="flex items-start justify-between gap-3">
                            <dt class="text-slate-500">Nomor meja</dt>
                            <dd class="text-right font-medium text-slate-800">{{ $order->table_number ?: '—' }}</dd>
                        </div>
                    @endif
                    @if ($order->type === \App\Enums\OrderType::DLV)
                        <div class="flex items-start justify-between gap-3">
                            <dt class="text-slate-500">Alamat</dt>
                            <dd class="text-right font-medium text-slate-800">{{ $order->delivery_address ?: '—' }}</dd>
                        </div>
                    @endif
                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-slate-500">Pembayaran</dt>
                        <dd class="text-right font-medium text-slate-800">
                            {{ $order->payment_status === \App\Enums\PaymentStatus::Paid ? 'Lunas' : 'Belum dibayar' }}
                        </dd>
                    </div>
                    @if ($order->payment_method)
                        <div class="flex items-start justify-between gap-3">
                            <dt class="text-slate-500">Metode</dt>
                            <dd class="text-right font-medium text-slate-800">
                                {{ $order->payment_method === \App\Enums\PaymentMethod::Qris ? 'QRIS' : 'Tunai' }}
                            </dd>
                        </div>
                    @endif
                    @if ($order->payment_status === \App\Enums\PaymentStatus::Paid)
                        @if ($order->amount_paid !== null)
                            <div class="flex items-start justify-between gap-3">
                                <dt class="text-slate-500">Dibayar</dt>
                                <dd class="text-right font-medium text-slate-800"><x-money :value="$order->amount_paid" /></dd>
                            </div>
                        @endif
                        @if ($order->change !== null && (float) $order->change > 0)
                            <div class="flex items-start justify-between gap-3">
                                <dt class="text-slate-500">Kembalian</dt>
                                <dd class="text-right font-medium text-slate-800"><x-money :value="$order->change" /></dd>
                            </div>
                        @endif
                        @if ($order->paid_at)
                            <div class="flex items-start justify-between gap-3">
                                <dt class="text-slate-500">Waktu bayar</dt>
                                <dd class="text-right font-medium text-slate-800">
                                    {{ $order->paid_at->timezone(\App\Services\OrderService::LOCAL_TIMEZONE)->format('d/m/Y H:i') }}
                                </dd>
                            </div>
                        @endif
                    @endif
                    @if ($order->notes)
                        <div class="border-t border-slate-100 pt-2">
                            <dt class="text-slate-500">Catatan</dt>
                            <dd class="mt-1 text-slate-700">{{ $order->notes }}</dd>
                        </div>
                    @endif
                </dl>
            </div>
        </div>
    </div>
@endsection
