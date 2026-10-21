<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Penjualan — {{ \App\Http\Controllers\SettingController::storeName() }}</title>
    <style>
        @page { margin: 10mm 10mm 14mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8pt; line-height: 1.35; color: #1e293b; }
        h1, h2, p { margin: 0; }
        h1 { font-size: 14pt; color: #065f46; }
        h2 { margin: 10px 0 4px; font-size: 9pt; page-break-after: avoid; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        th, td { padding: 4px 6px; border-bottom: 1px solid #e2e8f0; vertical-align: top; overflow-wrap: break-word; }
        th { background: #f1f5f9; font-size: 7pt; text-align: left; color: #475569; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        .header { margin-bottom: 8px; border-bottom: 2px solid #059669; }
        .header td { padding: 0 0 7px; border: 0; }
        .meta, .muted { font-size: 7pt; color: #64748b; }
        .right { text-align: right; }
        .center { text-align: center; }
        .summary { table-layout: fixed; border-collapse: separate; border-spacing: 3px 0; margin: 8px 0; }
        .summary td { width: 25%; padding: 8px; background: #f8fafc; border: 1px solid #e2e8f0; }
        .summary .profit { background: #ecfdf5; border-color: #a7f3d0; }
        .label { font-size: 7pt; color: #475569; }
        .value { margin: 3px 0; font-size: 13pt; font-weight: bold; }
        .positive { color: #047857; }
        .negative { color: #b91c1c; }
        .warning { padding: 5px 8px; margin: 6px 0; background: #fffbeb; color: #92400e; border-left: 3px solid #f59e0b; font-size: 7pt; }
        .incomplete { color: #92400e; }
        .daily { table-layout: fixed; }
        .daily td { font-size: 7pt; }
        .trend-track { height: 3px; margin-top: 2px; background: #e2e8f0; }
        .trend-bar { height: 3px; background: #059669; }
        .empty { padding: 5px 0; color: #64748b; }
        .footer { position: fixed; bottom: -9mm; left: 0; color: #64748b; font-size: 7pt; }
    </style>
</head>
<body>
    <div class="footer">WarungCerdas POS · Laporan penjualan · Semua nominal dalam Rupiah</div>
    <table class="header">
        <tr>
            <td>
                <h1>{{ \App\Http\Controllers\SettingController::storeName() }}</h1>
                <p>Laporan Penjualan · {{ \App\Http\Controllers\SettingController::posName() }}</p>
            </td>
            <td class="right meta">
                <p>Periode: {{ $resolvedStart->format('d/m/Y') }} – {{ $resolvedEnd->format('d/m/Y') }}</p>
                <p>Metode: {{ $paymentMethod ? ($paymentMethod === 'cash' ? 'Tunai' : 'QRIS') : 'Semua metode' }} · Transaksi lunas, tidak dibatalkan</p>
                <p>Dicetak: {{ now()->timezone(\App\Services\OrderService::LOCAL_TIMEZONE)->format('d/m/Y H:i') }} WIB</p>
            </td>
        </tr>
    </table>

    <table class="summary">
        <tr>
            <td>
                <p class="label">TOTAL OMZET</p>
                <p class="value"><x-money :value="$totalRevenue" /></p>
                <p class="muted">Sesuai periode dan metode terpilih</p>
            </td>
            <td>
                <p class="label">TRANSAKSI LUNAS</p>
                <p class="value">{{ number_format($totalOrders, 0, ',', '.') }}</p>
                <p class="muted">Rata-rata <x-money :value="$avgOrderValue" /> / transaksi</p>
            </td>
            <td>
                <p class="label">TOTAL HPP TERCATAT</p>
                <p class="value"><x-money :value="$totalHpp" /></p>
                <p class="muted">Produk: <x-money :value="$totalProductHpp" /> · Topping: <x-money :value="$totalToppingHpp" /></p>
            </td>
            <td class="profit">
                <p class="label">LABA KOTOR{{ $hasIncompleteHpp ? ' (ESTIMASI)' : '' }}</p>
                <p class="value {{ $grossProfit >= 0 ? 'positive' : 'negative' }}"><x-money :value="$grossProfit" /></p>
                <p class="muted">Margin {{ number_format($grossMargin, 1, ',', '.') }}%</p>
            </td>
        </tr>
    </table>

    @if ($hasIncompleteHpp)
        <p class="warning"><strong>HPP belum lengkap:</strong> {{ $productsWithoutHpp }} produk dan {{ $toppingsWithoutHpp }} topping terjual belum memiliki snapshot HPP. HPP yang belum diketahui dihitung 0; laba dan margin merupakan estimasi dan dapat lebih tinggi dari nilai sebenarnya.</p>
    @endif

    <h2>Metode Pembayaran</h2>
    <table>
        <thead><tr><th>Metode</th><th class="center">Transaksi</th><th class="right">Nominal</th><th class="right">% nominal pembayaran tercatat</th></tr></thead>
        <tbody>
            @foreach (['cash' => 'Tunai', 'qris' => 'QRIS'] as $key => $label)
                <tr><td>{{ $label }}</td><td class="center">{{ $paymentAnalysis[$key]['count'] }}</td><td class="right"><x-money :value="$paymentAnalysis[$key]['total']" /></td><td class="right">{{ number_format($paymentAnalysis[$key]['percentage'], 1, ',', '.') }}%</td></tr>
            @endforeach
            @if ($totalOrders > $paymentAnalysis['total_transactions'])
                <tr><td>Metode belum tercatat</td><td class="center">{{ $totalOrders - $paymentAnalysis['total_transactions'] }}</td><td class="right"><x-money :value="$totalRevenue - $paymentAnalysis['cash']['total'] - $paymentAnalysis['qris']['total']" /></td><td class="right">—</td></tr>
            @endif
        </tbody>
    </table>

    <h2>Ringkasan Harian</h2>
    <p class="muted">Grafik batang menunjukkan perbandingan omzet harian dalam periode terpilih.</p>
    @php($maxDailyRevenue = max(array_column($dailySales, 'revenue')) ?: 1)
    <table class="daily">
        <thead><tr>@for ($column = 0; $column < 3; $column++)<th>Tanggal</th><th class="center">Transaksi</th><th class="right">Omzet</th>@endfor</tr></thead>
        <tbody>
            @foreach (array_chunk($dailySales, 3) as $days)
                <tr>
                    @foreach ($days as $day)
                        <td>{{ $day['date'] }}</td><td class="center">{{ $day['count'] }}</td><td class="right"><x-money :value="$day['revenue']" /><div class="trend-track"><div class="trend-bar" style="width: {{ $day['revenue'] / $maxDailyRevenue * 100 }}%"></div></div></td>
                    @endforeach
                    @for ($column = count($days); $column < 3; $column++)<td></td><td></td><td></td>@endfor
                </tr>
            @endforeach
        </tbody>
    </table>

    @foreach (['Produk Terjual' => $productSales, 'Topping Terjual' => $toppingSales] as $title => $sales)
        <h2>{{ $title }}</h2>
        @if (empty($sales))
            <p class="empty">Belum ada {{ strtolower($title) }} pada periode ini.</p>
        @else
            <table>
                <thead><tr><th style="width: 4%">No</th><th style="width: 32%">{{ $title === 'Produk Terjual' ? 'Produk' : 'Topping' }}</th><th class="center">Jumlah</th><th class="right">Omzet</th><th class="right">HPP Snapshot</th><th class="right">Laba Kotor</th></tr></thead>
                <tbody>
                    @foreach ($sales as $index => $sale)
                        <tr>
                            <td class="center">{{ $index + 1 }}</td><td>{{ $sale['name'] }}</td><td class="center">{{ number_format($sale['quantity'], 0, ',', '.') }}</td><td class="right"><x-money :value="$sale['sales']" /></td>
                            <td class="right">@if ($sale['hpp'] !== null)<x-money :value="$sale['hpp']" />@else<span class="incomplete">Belum lengkap</span>@endif</td>
                            <td class="right">@if ($sale['profit'] !== null)<span class="{{ $sale['profit'] >= 0 ? 'positive' : 'negative' }}"><x-money :value="$sale['profit']" /></span>@else<span class="incomplete">Belum lengkap</span>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endforeach
    <p class="muted">Omzet dan HPP produk mencakup topping yang dipilih. Rincian topping merupakan bagian dari angka tersebut, sehingga tidak dijumlahkan ulang. HPP memakai snapshot transaksi.</p>

    <h2>Riwayat Transaksi ({{ $transactions->count() }} transaksi)</h2>
    @if ($transactions->isEmpty())
        <p class="empty">Tidak ada transaksi pada periode ini.</p>
    @else
        <table>
            <thead><tr><th style="width: 4%">No</th><th style="width: 26%">Nomor Transaksi</th><th>Waktu (WIB)</th><th>Jenis</th><th class="right">Total</th><th>Metode</th><th>Status</th></tr></thead>
            <tbody>
                @foreach ($transactions as $index => $order)
                    <tr>
                        <td class="center">{{ $index + 1 }}</td><td>{{ $order->order_number }}</td><td>{{ $order->created_at->timezone(\App\Services\OrderService::LOCAL_TIMEZONE)->format('d/m/Y H:i') }}</td><td>{{ $order->type->label() }}</td><td class="right"><x-money :value="$order->total" /></td>
                        <td>{{ match ($order->payment_method) { \App\Enums\PaymentMethod::Cash => 'Tunai', \App\Enums\PaymentMethod::Qris => 'QRIS', default => '—' } }}</td><td>{{ $order->payment_status->label() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
