@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Dashboard Penjualan</h2>
                <p class="text-sm text-slate-500">Ringkasan performa bisnis dan analisis HPP.</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-sm font-medium text-slate-600">Periode:</span>
                <select id="period-select" class="input w-auto" onchange="window.location.href = '{{ route('dashboard.index') }}?period=' + this.value">
                    <option value="7" {{ $period === '7' ? 'selected' : '' }}>7 Hari</option>
                    <option value="30" {{ $period === '30' ? 'selected' : '' }}>30 Hari</option>
                </select>
            </div>
        </div>

        {{-- Warning if HPP incomplete --}}
        @if ($hasIncompleteHpp)
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                <div class="flex items-start gap-3">
                    <svg class="shrink-0 mt-0.5 h-5 w-5 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                        <line x1="12" y1="9" x2="12" y2="13"></line>
                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                    </svg>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-amber-800">Perhitungan Laba Belum Lengkap</p>
                        <p class="mt-1 text-xs text-amber-700">
                            Terdapat <strong>{{ $productsWithoutHpp }}</strong> produk dan <strong>{{ $toppingsWithoutHpp }}</strong> topping yang terjual belum memiliki HPP.
                            Angka laba kotor dan margin di bawah bersifat <strong>estimasi, dapat lebih tinggi dari laba sebenarnya</strong> (HPP yang tidak diketahui dianggap 0).
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
            :period-label="$days.' hari terakhir'"
        />

        {{-- Charts --}}
        <div class="grid min-w-0 gap-4 lg:grid-cols-2 [&>div]:min-w-0">
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm p-5">
                <h3 class="text-sm font-semibold text-slate-900 mb-4">Tren Omzet Harian</h3>
                <div class="relative h-72 w-full min-w-0">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm p-5">
                <h3 class="text-sm font-semibold text-slate-900 mb-4">Tren Jumlah Transaksi Harian</h3>
                <div class="relative h-72 w-full min-w-0">
                    <canvas id="ordersChart"></canvas>
                </div>
            </div>
        </div>

        {{-- Payment Analysis & Top Products --}}
        <div class="grid min-w-0 gap-4 lg:grid-cols-2 [&>div]:min-w-0">
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm p-5">
                <h3 class="text-sm font-semibold text-slate-900 mb-4">Analisis Pembayaran</h3>

                <div class="space-y-4">
                    <div>
                        <div class="mb-1 flex flex-wrap justify-between gap-x-3 gap-y-1 text-sm">
                            <span class="font-medium text-slate-700">Tunai</span>
                            <span class="text-slate-600"><x-money :value="$paymentAnalysis['cash']['total']" /> ({{ $paymentAnalysis['cash']['percentage'] }}%)</span>
                        </div>
                        <div class="h-3 bg-slate-200 rounded-full overflow-hidden">
                            <div class="h-full rounded-full bg-emerald-600" style="width: {{ $paymentAnalysis['cash']['percentage'] }}%"></div>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">{{ $paymentAnalysis['cash']['count' ]}} transaksi</p>
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
                        <p class="text-xs text-slate-500">Total transaksi valid: <strong>{{ $paymentAnalysis['total_transactions'] }}</strong></p>
                    </div>
                </div>
            </div>

            <div class="card p-5">
                <h3 class="text-sm font-semibold text-slate-900 mb-4">Produk Terlaris (Top 10)</h3>

                <div class="min-w-0 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="th">Produk</th>
                                <th class="th text-right">Terjual</th>
                                <th class="th text-right">Penjualan</th>
                                <th class="th text-right">HPP</th>
                                <th class="th text-right">Laba Kotor</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($topProducts as $product)
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
                                        Belum ada data penjualan pada periode ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Recent Transactions --}}
        <div class="card p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-slate-900">Transaksi Terbaru</h3>
                <a href="{{ route('orders.index') }}" class="btn-ghost text-sm">Lihat Semua</a>
            </div>

            <div class="min-w-0 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="th">Nomor Pesanan</th>
                            <th class="th">Waktu</th>
                            <th class="th">Tipe</th>
                            <th class="th">Total</th>
                            <th class="th">Pembayaran</th>
                            <th class="th">Status</th>
                            <th class="th text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($recentTransactions as $order)
                            <tr class="hover:bg-slate-50/70">
                                <td class="td font-medium text-slate-900">{{ $order->order_number }}</td>
                                <td class="td text-slate-600">
                                    {{ $order->created_at->timezone(\App\Services\OrderService::LOCAL_TIMEZONE)->format('d/m/Y H:i') }}
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
                                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
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
                                    Belum ada transaksi.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        // Format rupiah for tooltips
        const formatRupiah = (value) => 'Rp ' + Math.round(Number(value)).toLocaleString('id-ID');

        // Revenue Chart
        const revenueCtx = document.getElementById('revenueChart');
        if (revenueCtx) {
            const revenueLabels = @json(array_keys($dailyRevenue));
            const revenueData = @json(array_values($dailyRevenue));

            new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels: revenueLabels.map(d => d.split('-').slice(1).join('/')),
                    datasets: [{
                        label: 'Omzet',
                        data: revenueData,
                        borderColor: '#059669',
                        backgroundColor: 'rgba(5, 150, 105, 0.1)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (ctx) => 'Omzet: ' + formatRupiah(ctx.raw),
                                title: (ctx) => 'Tanggal: ' + ctx[0].label
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: (value) => formatRupiah(value)
                            }
                        }
                    }
                }
            });
        }

        // Orders Chart
        const ordersCtx = document.getElementById('ordersChart');
        if (ordersCtx) {
            const ordersLabels = @json(array_keys($dailyOrders));
            const ordersData = @json(array_values($dailyOrders));

            new Chart(ordersCtx, {
                type: 'bar',
                data: {
                    labels: ordersLabels.map(d => d.split('-').slice(1).join('/')),
                    datasets: [{
                        label: 'Jumlah Transaksi',
                        data: ordersData,
                        backgroundColor: 'rgba(37, 99, 235, 0.7)',
                        borderColor: '#2563eb',
                        borderWidth: 1,
                        borderRadius: 4,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (ctx) => 'Transaksi: ' + ctx.raw,
                                title: (ctx) => 'Tanggal: ' + ctx[0].label
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1,
                                callback: (value) => Number.isInteger(value) ? value : ''
                            }
                        }
                    }
                }
            });
        }
    </script>
@endpush