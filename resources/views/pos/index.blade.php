@extends('layouts.app')

@section('title', 'Kasir')

@section('content')
    <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <section class="space-y-4">
            <div class="card flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
                <label class="relative flex-1">
                    <span class="sr-only">Cari produk</span>
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" />
                    </svg>
                    <input id="pos-search" type="search" class="input pl-9" placeholder="Cari produk atau kategori...">
                </label>
                <p class="text-xs text-slate-500 sm:w-44">Ketuk produk untuk menambah ke pesanan.</p>
            </div>

            <div id="pos-filters" class="flex flex-wrap gap-2">
                <button
                    type="button"
                    class="js-category-filter rounded-full border border-slate-200 bg-white px-3.5 py-1.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50 aria-pressed:border-emerald-600 aria-pressed:bg-emerald-600 aria-pressed:text-white"
                    data-category="all"
                    aria-pressed="true"
                >Semua</button>
                @foreach ($categories as $category)
                    <button
                        type="button"
                        class="js-category-filter rounded-full border border-slate-200 bg-white px-3.5 py-1.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50 aria-pressed:border-emerald-600 aria-pressed:bg-emerald-600 aria-pressed:text-white"
                        data-category="{{ $category->id }}"
                        aria-pressed="false"
                    >{{ $category->name }}</button>
                @endforeach
            </div>

            <div id="pos-no-results" class="card hidden p-10 text-center text-sm text-slate-500">
                Tidak ada produk yang cocok dengan pencarian Anda.
            </div>

            @forelse ($categories as $category)
                <div class="js-category-section space-y-3" data-category="{{ $category->id }}">
                    <div class="flex items-baseline justify-between">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ $category->name }}</h2>
                        <span class="text-xs text-slate-400">{{ $category->products->count() }} produk</span>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($category->products as $product)
                            <button
                                type="button"
                                class="js-product flex flex-col items-start gap-2 rounded-2xl border border-slate-200 bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-emerald-500/40"
                                data-id="{{ $product->id }}"
                                data-name="{{ $product->name }}"
                                data-price="{{ $product->price }}"
                                data-category="{{ $product->category_id }}"
                                data-search="{{ mb_strtolower($product->name.' '.$category->name) }}"
                            >
                                <span class="font-medium leading-snug text-slate-900">{{ $product->name }}</span>
                                <span class="text-sm font-semibold text-emerald-600"><x-money :value="$product->price" /></span>
                                @if ($toppings->isNotEmpty())
                                    <span class="badge-slate">bisa + topping</span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="card p-10 text-center">
                    <p class="text-sm text-slate-500">Belum ada kategori aktif. Aktifkan kategori di menu Kategori agar produknya tampil di kasir.</p>
                    <a href="{{ route('categories.index') }}" class="btn-primary mt-4">Kelola Kategori</a>
                </div>
            @endforelse
        </section>

        <aside class="xl:sticky xl:top-24">
            <div class="card flex flex-col">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">Pesanan</h2>
                        <p class="text-xs text-slate-500"><span id="pos-line-count">0</span> item di keranjang</p>
                    </div>
                    <button type="button" id="pos-clear-cart" class="text-xs font-medium text-slate-500 hover:text-red-600">
                        Kosongkan
                    </button>
                </div>

                <div id="pos-cart-lines" class="max-h-80 space-y-3 overflow-y-auto px-5 py-4"></div>

                <p id="pos-cart-empty" class="px-5 py-10 text-center text-sm text-slate-500">
                    Keranjang kosong. Ketuk produk untuk mulai mencatat pesanan.
                </p>

                <dl class="space-y-2 border-t border-slate-100 px-5 py-4 text-sm">
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500">Subtotal</dt>
                        <dd id="pos-subtotal" class="font-medium text-slate-700">Rp 0</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500">Total topping</dt>
                        <dd id="pos-topping-total" class="font-medium text-slate-700">Rp 0</dd>
                    </div>
                    <div class="flex items-center justify-between border-t border-slate-100 pt-2">
                        <dt class="font-semibold text-slate-900">Total</dt>
                        <dd id="pos-total" class="text-base font-bold text-emerald-600">Rp 0</dd>
                    </div>
                </dl>

                <div class="space-y-2 border-t border-slate-100 px-5 py-4">
                    <button type="button" id="pos-checkout" class="btn-primary w-full disabled:cursor-not-allowed disabled:opacity-50" disabled>
                        Lanjut ke Pembayaran
                    </button>
                    <p id="pos-checkout-note" class="hidden text-center text-xs font-medium text-emerald-700">
                        Pembayaran akan tersedia pada tahap berikutnya.
                    </p>
                    <p class="text-center text-xs text-slate-400">Keranjang hanya tersimpan sementara di perangkat ini.</p>
                </div>
            </div>
        </aside>
    </div>

    <dialog id="pos-topping-modal" class="w-full max-w-md rounded-2xl border-0 p-0 shadow-xl backdrop:bg-slate-950/50">
        <div class="flex items-start justify-between border-b border-slate-100 px-5 py-4">
            <div>
                <p id="pos-modal-title" class="text-sm font-semibold text-slate-900">Topping</p>
                <p class="text-xs text-slate-500">Pilih tambahan untuk produk ini.</p>
            </div>
            <button type="button" id="pos-modal-close" class="grid h-8 w-8 place-items-center rounded-lg text-xl leading-none text-slate-400 hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">&times;</button>
        </div>

        <div id="pos-modal-toppings" class="max-h-72 space-y-1 overflow-y-auto px-5 py-3"></div>

        <div class="space-y-3 border-t border-slate-100 px-5 py-4">
            <div class="flex items-center justify-between text-sm">
                <span class="text-slate-500">Jumlah</span>
                <div class="flex items-center gap-2">
                    <button type="button" id="pos-modal-qty-minus" class="btn-ghost h-9 w-9 px-0!">−</button>
                    <span id="pos-modal-qty" class="w-8 text-center font-semibold text-slate-900">1</span>
                    <button type="button" id="pos-modal-qty-plus" class="btn-ghost h-9 w-9 px-0!">+</button>
                </div>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-sm text-slate-500">Subtotal item</span>
                <span id="pos-modal-total" class="font-semibold text-slate-900">Rp 0</span>
            </div>
            <button type="button" id="pos-modal-add" class="btn-primary w-full">Tambahkan ke pesanan</button>
        </div>
    </dialog>
@endsection

@push('scripts')
    <script>
        window.posToppings = @json($posToppings);
    </script>
@endpush
