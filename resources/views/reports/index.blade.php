@extends('layouts.app')

@section('title', 'Laporan Penjualan')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Laporan Penjualan</h2>
                <p class="text-sm text-slate-500">Ringkasan dan rincian transaksi dalam periode terpilih.</p>
            </div>
            <div class="flex w-full flex-wrap items-center gap-2 sm:w-auto">
                <a
                    href="{{ route('reports.export-pdf', array_filter(request()->query(), fn ($v) => $v !== '')) }}"
                    class="btn-primary min-h-11 whitespace-nowrap"
                >
                    <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                        <polyline points="14 2 14 8 20 8" />
                        <line x1="16" y1="13" x2="8" y2="13" />
                        <line x1="16" y1="17" x2="8" y2="17" />
                        <polyline points="10 9 9 9 8 9" />
                    </svg>
                    <span>Ekspor PDF</span>
                </a>
                <a
                    href="{{ route('reports.export', array_filter(request()->query(), fn ($v) => $v !== '')) }}"
                    class="btn-ghost min-h-11 whitespace-nowrap"
                >
                    <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3" />
                    </svg>
                    <span>Ekspor CSV</span>
                </a>
            </div>
        </div>

        {{-- Filter Form --}}
        <div class="card p-5">
            <form method="GET" action="{{ route('reports.index') }}" class="flex flex-wrap items-end gap-3">
                <div class="min-w-0 basis-full sm:basis-44 sm:grow">
                    <label class="label" for="period">Periode</label>
                    <select id="period" name="period" class="input" onchange="toggleCustomDates()">
                        <option value="today" {{ $period === 'today' ? 'selected' : '' }}>Hari Ini</option>
                        <option value="yesterday" {{ $period === 'yesterday' ? 'selected' : '' }}>Kemarin</option>
                        <option value="7days" {{ $period === '7days' ? 'selected' : '' }}>7 Hari Terakhir</option>
                        <option value="30days" {{ $period === '30days' ? 'selected' : '' }}>30 Hari Terakhir</option>
                        <option value="this_month" {{ $period === 'this_month' ? 'selected' : '' }}>Bulan Ini</option>
                        <option value="custom" {{ $period === 'custom' ? 'selected' : '' }}>Rentang Tanggal Custom</option>
                    </select>
                </div>

                <div class="min-w-0 basis-full sm:basis-44 sm:grow" id="start-date-group" style="display: {{ $period === 'custom' ? 'block' : 'none' }};">
                    <label class="label" for="start_date">Tanggal Mulai</label>
                    <input type="date" id="start_date" name="start_date" class="input" value="{{ $startDate ?? '' }}" max="{{ $endDate ?? \Carbon\Carbon::now()->format('Y-m-d') }}">
                </div>

                <div class="min-w-0 basis-full sm:basis-44 sm:grow" id="end-date-group" style="display: {{ $period === 'custom' ? 'block' : 'none' }};">
                    <label class="label" for="end_date">Tanggal Akhir</label>
                    <input type="date" id="end_date" name="end_date" class="input" value="{{ $endDate ?? '' }}" min="{{ $startDate ?? '' }}" max="{{ \Carbon\Carbon::now()->format('Y-m-d') }}">
                </div>

                <div class="min-w-0 basis-full sm:basis-44 sm:grow">
                    <button type="submit" class="btn-primary w-full sm:w-auto">Terapkan</button>
                </div>
            </form>
        </div>

        {{-- Warning if HPP incomplete --}}
        @if ($hasIncompleteHpp)
            <div class="card p-4 border-amber-200 bg-amber-50 rounded-xl">
                <div class="flex items-start gap-3">
                    <svg class="shrink-0 mt-0.5 h-5 w-5 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                        <line x1="12" y1="9" x2="12" y2="13"></line>
                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                    </svg>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-amber-800">Perhitungan Laba Belum Lengkap</p>
                        <p class="mt-1 text-xs text-amber-700">
                            Terdapat <strong>{{ $productsWithoutHpp }}</strong> produk dan <strong>{{ $toppingsWithoutHpp }}</strong> topping yang terjual belum memiliki HPP snapshot.
                            Angka laba kotor dan margin di bawah bersifat <strong>estimasi, dapat lebih tinggi dari laba sebenarnya</strong> (HPP null dianggap 0).
                        </p>
                    </div>
                </div>
            </div>
        @endif

        <x-sales-summary
            :total-revenue="$totalRevenue" :total-orders="$totalOrders" :avg-order-value="$avgOrderValue"
            :total-hpp="$totalHpp" :total-product-hpp="$totalProductHpp" :total-topping-hpp="$totalToppingHpp"
            :gross-profit="$grossProfit" :gross-margin="$grossMargin" :has-incomplete-hpp="$hasIncompleteHpp"
            :payment-analysis="$paymentAnalysis"
            :period-label="$resolvedStart->format('d/m/Y').' – '.$resolvedEnd->format('d/m/Y')" :revenue-label="$period === 'today' ? 'Omzet Hari Ini' : 'Omzet Periode'"
        />

        {{-- Product Sales Detail --}}
        <div class="card p-5">
            <h3 class="text-sm font-semibold text-slate-900 mb-4">Produk Terjual</h3>

            <div class="min-w-0 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="th">Produk</th>
                            <th class="th text-right">Terjual</th>
                            <th class="th text-right">Penjualan</th>
                            <th class="th text-right">HPP Snapshot</th>
                            <th class="th text-right">Laba Kotor</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($productSales as $product)
                            <tr class="hover:bg-slate-50/70">
                                <td class="td font-medium text-slate-900">{{ $product['name'] }}</td>
                                <td class="td text-right text-slate-600">{{ number_format($product['quantity']) }}</td>
                                <td class="td text-right text-slate-700"><x-money :value="$product['sales']" /></td>
                                <td class="td text-right {{ $product['hpp'] !== null ? 'text-slate-600' : 'text-amber-600' }}">
                                    @if ($product['hpp'] !== null)
                                        <x-money :value="$product['hpp']" />
                                    @else
                                        <span class="inline-flex rounded-full bg-amber-50 px-2 py-1 text-xs font-medium text-amber-800">Belum lengkap</span>
                                    @endif
                                </td>
                                <td class="td text-right font-medium {{ $product['profit'] !== null && $product['profit'] >= 0 ? 'text-emerald-600' : ($product['profit'] !== null && $product['profit'] < 0 ? 'text-red-600' : 'text-amber-600') }}">
                                    @if ($product['profit'] !== null)
                                        <x-money :value="$product['profit']" />
                                    @else
                                        <span class="inline-flex rounded-full bg-amber-50 px-2 py-1 text-xs font-medium text-amber-800">Belum lengkap</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-10 text-center text-sm text-slate-500">
                                    Belum ada produk terjual pada periode ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Topping Sales Detail --}}
        <div class="card p-5">
            <h3 class="text-sm font-semibold text-slate-900 mb-4">Topping Terjual</h3>

            <div class="min-w-0 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="th">Topping</th>
                            <th class="th text-right">Terjual</th>
                            <th class="th text-right">Penjualan</th>
                            <th class="th text-right">HPP Snapshot</th>
                            <th class="th text-right">Laba Kotor</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($toppingSales as $topping)
                            <tr class="hover:bg-slate-50/70">
                                <td class="td font-medium text-slate-900">{{ $topping['name'] }}</td>
                                <td class="td text-right text-slate-600">{{ number_format($topping['quantity']) }}</td>
                                <td class="td text-right text-slate-700"><x-money :value="$topping['sales']" /></td>
                                <td class="td text-right {{ $topping['hpp'] !== null ? 'text-slate-600' : 'text-amber-600' }}">
                                    @if ($topping['hpp'] !== null)
                                        <x-money :value="$topping['hpp']" />
                                    @else
                                        <span class="inline-flex rounded-full bg-amber-50 px-2 py-1 text-xs font-medium text-amber-800">Belum lengkap</span>
                                    @endif
                                </td>
                                <td class="td text-right font-medium {{ $topping['profit'] !== null && $topping['profit'] >= 0 ? 'text-emerald-600' : ($topping['profit'] !== null && $topping['profit'] < 0 ? 'text-red-600' : 'text-amber-600') }}">
                                    @if ($topping['profit'] !== null)
                                        <x-money :value="$topping['profit']" />
                                    @else
                                        <span class="inline-flex rounded-full bg-amber-50 px-2 py-1 text-xs font-medium text-amber-800">Belum lengkap</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-10 text-center text-sm text-slate-500">
                                    Belum ada topping terjual pada periode ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Payment Analysis --}}
        <div class="card p-5">
            <h3 class="text-sm font-semibold text-slate-900 mb-4">Analisis Metode Pembayaran</h3>

            <div class="space-y-4">
                <div>
                    <div class="mb-1 flex flex-wrap justify-between gap-x-3 gap-y-1 text-sm">
                        <span class="font-medium text-slate-700">Tunai</span>
                        <span class="text-slate-600"><x-money :value="$paymentAnalysis['cash']['total']" /> ({{ $paymentAnalysis['cash']['percentage'] }}%)</span>
                    </div>
                    <div class="h-3 bg-slate-200 rounded-full overflow-hidden">
                        <div class="h-full rounded-full bg-emerald-600" style="width: {{ $paymentAnalysis['cash']['percentage'] }}%"></div>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">{{ $paymentAnalysis['cash']['count'] }} transaksi</p>
                </div>

                <div>
                    <div class="mb-1 flex flex-wrap justify-between gap-x-3 gap-y-1 text-sm">
                        <span class="font-medium text-slate-700">QRIS</span>
                        <span class="text-slate-600"><x-money :value="$paymentAnalysis['qris']['total']" /> ({{ $paymentAnalysis['qris']['percentage'] }}%)</span>
                    </div>
                    <div class="h-3 bg-slate-200 rounded-full overflow-hidden">
                        <div class="h-full rounded-full bg-blue-600" style="width: {{ $paymentAnalysis['qris']['percentage'] }}%"></div>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">{{ $paymentAnalysis['qris']['count'] }} transaksi</p>
                </div>

                <div class="border-t border-slate-100 pt-3">
                    <p class="text-xs text-slate-500">Total transaksi lunas: <strong>{{ $paymentAnalysis['total_transactions'] }}</strong></p>
                </div>
            </div>
        </div>

        {{-- Transaction History --}}
        <div class="card p-5">
            <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
                <h3 class="text-sm font-semibold text-slate-900">Riwayat Transaksi</h3>
                <form method="GET" action="{{ route('reports.index') }}" class="flex flex-wrap items-end gap-3">
                    @foreach (array_filter(request()->except(['search', 'payment_method', 'page'])) as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach
                    <div class="min-w-0 basis-full sm:basis-52 sm:grow">
                        <input type="text" name="search" class="input" placeholder="Cari nomor transaksi..." value="{{ request()->query('search') }}">
                    </div>
                    <div class="min-w-0 basis-full sm:basis-44 sm:grow">
                        <select name="payment_method" class="input">
                            <option value="">Semua Metode</option>
                            <option value="cash" {{ request()->query('payment_method') === 'cash' ? 'selected' : '' }}>Tunai</option>
                            <option value="qris" {{ request()->query('payment_method') === 'qris' ? 'selected' : '' }}>QRIS</option>
                        </select>
                    </div>
                    <div class="min-w-0 basis-full sm:basis-44 sm:grow">
                        <button type="submit" class="btn-primary w-full sm:w-auto">Filter</button>
                    </div>
                    @if (request()->filled('search') || request()->filled('payment_method'))
                        <a href="{{ route('reports.index', array_filter(request()->except(['search', 'payment_method', 'page']), fn ($v) => $v !== '')) }}" class="btn-ghost text-xs">Reset</a>
                    @endif
                </form>
            </div>

            <div class="min-w-0 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="th">Nomor Transaksi</th>
                            <th class="th">Waktu</th>
                            <th class="th">Tipe</th>
                            <th class="th">Total</th>
                            <th class="th">Metode</th>
                            <th class="th">Status</th>
                            <th class="th text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($transactions as $order)
                            <tr class="hover:bg-slate-50/70">
                                <td class="td font-medium text-slate-900">{{ $order->order_number }}</td>
                                <td class="td text-slate-600">
                                    {{ $order->created_at->timezone(config('app.timezone', 'Asia/Jakarta'))->format('d/m/Y H:i') }}
                                </td>
                                <td class="td"><x-order-type-badge :type="$order->type" /></td>
                                <td class="td font-medium text-slate-700"><x-money :value="$order->total" /></td>
                                <td class="td">
                                    @if ($order->payment_method)
                                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium
                                            @if ($order->payment_method === \App\Enums\PaymentMethod::Cash)
                                                bg-emerald-50 text-emerald-700
                                            @else
                                                bg-blue-50 text-blue-700
                                            @endif
                                        ">
                                            @if ($order->payment_method === \App\Enums\PaymentMethod::Cash)
                                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            @else
                                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                                            @endif
                                            {{ $order->payment_method === \App\Enums\PaymentMethod::Cash ? 'Tunai' : 'QRIS' }}
                                        </span>
                                    @else
                                        <span class="text-slate-400 text-xs">-</span>
                                    @endif
                                </td>
                                <td class="td"><x-order-status-badge :status="$order->status" /></td>
                                <td class="td">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('orders.receipt', $order) }}" class="btn-ghost text-xs" target="_blank">Struk</a>
                                        <a href="{{ route('orders.show', $order) }}" class="btn-ghost text-xs">Detail</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-10 text-center text-sm text-slate-500">
                                    Tidak ada transaksi pada filter ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($transactions->hasPages())
                <div class="mt-4">
                    {{ $transactions->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function toggleCustomDates() {
            const period = document.getElementById('period').value;
            const show = period === 'custom';
            document.getElementById('start-date-group').style.display = show ? 'block' : 'none';
            document.getElementById('end-date-group').style.display = show ? 'block' : 'none';
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', toggleCustomDates);
    </script>
@endpush