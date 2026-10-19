@extends('layouts.app')

@section('title', 'Pesanan')

@php
    $statusOptions = [
        ['value' => null, 'label' => 'Aktif'],
        ['value' => 'all', 'label' => 'Semua'],
        ['value' => 'new', 'label' => 'Baru'],
        ['value' => 'preparing', 'label' => 'Diproses'],
        ['value' => 'ready', 'label' => 'Siap'],
        ['value' => 'completed', 'label' => 'Selesai'],
        ['value' => 'cancelled', 'label' => 'Dibatalkan'],
    ];
    $typeOptions = [
        ['value' => null, 'label' => 'Semua tipe'],
        ['value' => 'TA', 'label' => 'Bungkus'],
        ['value' => 'DI', 'label' => 'Dine-in'],
        ['value' => 'DLV', 'label' => 'Delivery'],
    ];
    $filterUrl = fn (?string $statusValue, ?string $typeValue) => route('orders.index', array_filter([
        'status' => $statusValue,
        'type' => $typeValue,
    ], fn ($value) => $value !== null && $value !== ''));
    $chipClass = fn (bool $active) => $active
        ? 'rounded-full border border-emerald-600 bg-emerald-600 px-3.5 py-1.5 text-sm font-medium text-white transition'
        : 'rounded-full border border-slate-200 bg-white px-3.5 py-1.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50';
@endphp

@section('content')
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-slate-900">Pesanan</h2>
            <p class="text-sm text-slate-500">Pantau pesanan aktif dan ubah statusnya saat diproses.</p>
        </div>
        <a href="{{ route('pos.index') }}" class="btn-primary">+ Pesanan Baru</a>
    </div>

    <div class="card mb-4 space-y-3 p-4">
        <div class="flex flex-wrap items-center gap-2">
            <span class="mr-1 text-xs font-semibold uppercase tracking-wide text-slate-400">Status</span>
            @foreach ($statusOptions as $option)
                <a
                    href="{{ $filterUrl($option['value'], $type) }}"
                    class="{{ $chipClass($status === $option['value']) }}"
                    @if ($status === $option['value']) aria-current="page" @endif
                >{{ $option['label'] }}</a>
            @endforeach
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span class="mr-1 text-xs font-semibold uppercase tracking-wide text-slate-400">Tipe</span>
            @foreach ($typeOptions as $option)
                <a
                    href="{{ $filterUrl($status, $option['value']) }}"
                    class="{{ $chipClass($type === $option['value']) }}"
                    @if ($type === $option['value']) aria-current="page" @endif
                >{{ $option['label'] }}</a>
            @endforeach
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="th">Nomor</th>
                        <th class="th">Waktu</th>
                        <th class="th">Tipe</th>
                        <th class="th">Item</th>
                        <th class="th">Total</th>
                        <th class="th">Status</th>
                        <th class="th text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($orders as $order)
                        <tr class="hover:bg-slate-50/70">
                            <td class="td font-medium text-slate-900">{{ $order->order_number }}</td>
                            <td class="td text-slate-600">
                                {{ $order->created_at->timezone(\App\Services\OrderService::LOCAL_TIMEZONE)->format('d/m/Y H:i') }}
                            </td>
                            <td class="td"><x-order-type-badge :type="$order->type" /></td>
                            <td class="td text-slate-600">{{ $order->items_count }} item</td>
                            <td class="td font-medium text-slate-700"><x-money :value="$order->total" /></td>
                            <td class="td"><x-order-status-badge :status="$order->status" /></td>
                            <td class="td">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('orders.receipt', $order) }}" class="btn-ghost">Struk</a>
                                    <a href="{{ route('orders.show', $order) }}" class="btn-ghost">Detail</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-10 text-center text-sm text-slate-500">
                                Tidak ada pesanan pada filter ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($orders->hasPages())
        <div class="mt-4">
            {{ $orders->links() }}
        </div>
    @endif
@endsection
