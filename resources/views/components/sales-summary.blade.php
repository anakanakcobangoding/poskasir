@props(['totalRevenue', 'totalOrders', 'avgOrderValue', 'totalHpp', 'totalProductHpp', 'totalToppingHpp', 'grossProfit', 'grossMargin', 'hasIncompleteHpp', 'paymentAnalysis', 'periodLabel', 'revenueLabel' => 'Omzet Periode'])

@php
    $cards = [
        ['key' => 'revenue', 'label' => $revenueLabel, 'value' => $totalRevenue, 'color' => 'border-emerald-200 border-t-emerald-500 bg-emerald-50/40', 'iconColor' => 'bg-emerald-100 text-emerald-700', 'path' => 'M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6'],
        ['key' => 'orders', 'label' => 'Jumlah Transaksi', 'value' => $totalOrders, 'color' => 'border-blue-200 border-t-blue-500 bg-blue-50/40', 'iconColor' => 'bg-blue-100 text-blue-700', 'path' => 'M9 6h11M9 12h11M9 18h11M4 6h.01M4 12h.01M4 18h.01'],
        ['key' => 'hpp', 'label' => 'Total HPP', 'value' => $totalHpp, 'color' => 'border-amber-200 border-t-amber-500 bg-amber-50/40', 'iconColor' => 'bg-amber-100 text-amber-800', 'path' => 'M4 4h16v16H4zM8 8h8M8 12h3M8 16h3M15 12h1M15 16h1'],
        ['key' => 'profit', 'label' => 'Laba Kotor', 'value' => $grossProfit, 'color' => 'border-violet-200 border-t-violet-500 bg-violet-50/40', 'iconColor' => 'bg-violet-100 text-violet-700', 'path' => 'M3 17l6-6 4 4 8-10M15 5h6v6'],
        ['key' => 'cash', 'label' => 'Pembayaran Tunai', 'value' => $paymentAnalysis['cash']['total'], 'color' => 'border-teal-200 border-t-teal-500 bg-teal-50/40', 'iconColor' => 'bg-teal-100 text-teal-700', 'path' => 'M3 6h18v12H3zM7 6v3H3M17 18v-3h4M14 12a2 2 0 1 1-4 0 2 2 0 0 1 4 0'],
        ['key' => 'qris', 'label' => 'Pembayaran QRIS', 'value' => $paymentAnalysis['qris']['total'], 'color' => 'border-indigo-200 border-t-indigo-500 bg-indigo-50/40', 'iconColor' => 'bg-indigo-100 text-indigo-700', 'path' => 'M3 3h6v6H3zM15 3h6v6h-6zM3 15h6v6H3zM15 15h2v2h-2zM19 15h2v6h-6v-2'],
    ];
@endphp

<dl data-sales-summary class="grid auto-rows-fr gap-4 sm:grid-cols-2 xl:grid-cols-3">
    @foreach ($cards as $card)
        <div data-stat-card="{{ $card['key'] }}" class="flex min-w-0 flex-col gap-3 rounded-2xl border border-t-4 p-5 shadow-sm {{ $card['color'] }}">
            <dt class="flex min-w-0 items-center justify-between gap-3">
                <span class="text-xs font-semibold uppercase leading-5 tracking-wide text-slate-600">{{ $card['label'] }}</span>
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl {{ $card['iconColor'] }}">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $card['path'] }}" /></svg>
                </span>
            </dt>
            <dd data-stat-value class="min-w-0 break-words text-2xl font-bold leading-tight tabular-nums {{ $card['key'] === 'profit' && $grossProfit < 0 ? 'text-red-700' : 'text-slate-900' }}">
                @if ($card['key'] === 'orders')
                    {{ number_format($card['value'], 0, ',', '.') }}
                @else
                    <x-money :value="$card['value']" />
                @endif
            </dd>
            <dd class="mt-auto space-y-1 text-xs leading-5 text-slate-600">
                @switch($card['key'])
                    @case('revenue')
                        <p>{{ $periodLabel }}</p>
                        @break
                    @case('orders')
                        <p>Rata-rata <x-money :value="$avgOrderValue" /> per transaksi</p>
                        @break
                    @case('hpp')
                        <p class="flex flex-wrap gap-x-3"><span>Produk: <x-money :value="$totalProductHpp" /></span><span>Topping: <x-money :value="$totalToppingHpp" /></span></p>
                        @if ($hasIncompleteHpp)
                            <p class="font-medium text-amber-800">HPP tercatat; data belum lengkap</p>
                        @endif
                        @break
                    @case('profit')
                        <p>Margin: {{ number_format($grossMargin, 1, ',', '.') }}%</p>
                        @if ($hasIncompleteHpp)
                            <p class="font-medium text-amber-800">Estimasi — HPP belum lengkap</p>
                        @endif
                        @break
                    @default
                        <p>{{ $paymentAnalysis[$card['key']]['count'] }} transaksi ({{ number_format($paymentAnalysis[$card['key']]['percentage'], 1, ',', '.') }}% dari nominal pembayaran)</p>
                @endswitch
            </dd>
        </div>
    @endforeach
</dl>
