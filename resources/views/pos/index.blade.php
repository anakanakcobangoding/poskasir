@extends('layouts.app')

@section('title', 'Kasir')

@section('content')
    <div data-store-closed-banner role="status" @if(\App\Http\Controllers\SettingController::isStoreOpen()) hidden @endif class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-red-800">
        <strong>TOKO SEDANG TUTUP</strong>
        <p class="mt-1 text-sm">Toko sedang tutup. Transaksi tidak dapat diproses. Silakan hubungi admin untuk membuka toko kembali.</p>
    </div>
    <div class="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <section class="space-y-4 lg:grid lg:h-[calc(100dvh-7rem)] lg:min-h-0 lg:grid-rows-[auto_auto_minmax(0,1fr)]">
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

            <div id="pos-menu-list" class="space-y-4 lg:min-h-0 lg:overflow-y-auto lg:pr-1">
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
                                @if ($product->image)
                                    <img src="{{ route('products.image', $product, absolute: false) }}" alt="{{ $product->name }}" class="w-full h-32 object-cover rounded-xl mb-2" onerror="this.style.display='none'">
                                @else
                                    <div class="w-full h-32 bg-slate-100 rounded-xl mb-2 flex items-center justify-center">
                                        <svg class="h-8 w-8 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                    </div>
                                @endif
                                <span class="font-medium leading-snug text-slate-900">{{ $product->name }}</span>
                                <span class="text-sm font-semibold text-emerald-600"><x-money :value="$product->price" /></span>
                                @if ($product->toppings->isNotEmpty())
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
            </div>
        </section>

        <aside class="lg:sticky lg:top-20 lg:min-h-0">
            <div id="pos-cart-panel" class="card flex flex-col lg:h-[calc(100dvh-7rem)] lg:min-h-0">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">Pesanan</h2>
                        <p class="text-xs text-slate-500"><span id="pos-line-count">0</span> item di keranjang</p>
                    </div>
                    <button type="button" id="pos-clear-cart" class="text-xs font-medium text-slate-500 hover:text-red-600">
                        Kosongkan
                    </button>
                </div>

                <div id="pos-cart-lines" class="max-h-80 space-y-3 overflow-y-auto px-5 py-4 lg:max-h-none lg:min-h-0 lg:flex-1"></div>

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
                    <div id="pos-discount-row" class="flex items-center justify-between" hidden><dt id="pos-discount-label" class="text-slate-500">Diskon</dt><dd id="pos-discount-amount" class="font-medium text-red-700">− Rp 0</dd></div>
                    <div id="pos-fee-row" class="flex items-center justify-between" hidden><dt id="pos-fee-label" class="text-slate-500">Biaya tambahan</dt><dd id="pos-fee-amount" class="font-medium text-slate-700">Rp 0</dd></div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" id="pos-discount-open" class="btn-secondary text-xs">Diskon</button>
                        <button type="button" id="pos-fee-open" class="btn-secondary text-xs">+ Tambah Harga</button>
                    </div>
                    <div class="flex items-center justify-between border-t border-slate-100 pt-2">
                        <dt class="font-semibold text-slate-900">Total</dt>
                        <dd id="pos-total" class="text-base font-bold text-emerald-600">Rp 0</dd>
                    </div>
                </dl>

                <div class="space-y-3 border-t border-slate-100 px-5 py-4">
                    <p id="pos-order-feedback" class="hidden rounded-lg px-3 py-2 text-xs font-medium" role="status"></p>
                    <button type="button" id="pos-checkout" class="btn-primary w-full disabled:cursor-not-allowed disabled:opacity-50" disabled>
                        Proses Transaksi
                    </button>
                    <p class="text-center text-xs text-slate-400">Keranjang hanya tersimpan sementara di perangkat ini.</p>
                </div>
            </div>
        </aside>
    </div>

    <dialog id="pos-checkout-step1" class="m-auto max-h-[calc(100vh-2rem)] w-[calc(100%-2rem)] max-w-lg overflow-hidden rounded-2xl border-0 p-0 shadow-xl backdrop:bg-slate-950/50">
        <div class="flex items-start justify-between border-b border-slate-100 px-5 py-4">
            <div>
                <p class="text-sm font-semibold text-slate-900">Proses Transaksi</p>
                <p class="text-xs text-slate-500">1. Tipe Pesanan → 2. Total → 3. Pembayaran</p>
            </div>
            <button type="button" id="pos-step1-close" class="grid h-8 w-8 place-items-center rounded-lg text-xl leading-none text-slate-400 hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">&times;</button>
        </div>
        <div class="max-h-[75vh] space-y-4 overflow-y-auto px-5 py-4">
            <div>
                <p class="label mb-2">Pilih tipe pesanan</p>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                    <button type="button" class="pos-order-type-btn rounded-xl border border-slate-200 px-3 py-3 text-sm font-semibold text-slate-700 transition hover:border-emerald-300 hover:bg-emerald-50/40" data-type="DI">Dine In</button>
                    <button type="button" class="pos-order-type-btn rounded-xl border border-slate-200 px-3 py-3 text-sm font-semibold text-slate-700 transition hover:border-emerald-300 hover:bg-emerald-50/40" data-type="TA">Takeaway</button>
                    <button type="button" class="pos-order-type-btn rounded-xl border border-slate-200 px-3 py-3 text-sm font-semibold text-slate-700 transition hover:border-emerald-300 hover:bg-emerald-50/40" data-type="DLV">Delivery</button>
                </div>
            </div>

            <div id="pos-step1-di" class="hidden">
                <label class="label" for="pos-step1-table">Nomor meja</label>
                <input id="pos-step1-table" class="input" maxlength="50" placeholder="Contoh: A1">
            </div>

            <div id="pos-step1-dlv" class="hidden space-y-3">
                <div>
                    <p class="label mb-2">Pilih platform delivery</p>
                    <div class="grid grid-cols-1 gap-2">
                        <button type="button" class="pos-delivery-platform-btn rounded-xl border border-slate-200 px-3 py-2.5 text-left text-sm font-medium text-slate-700 transition hover:border-emerald-300 hover:bg-emerald-50/40" data-platform="shopeefood">ShopeeFood</button>
                        <button type="button" class="pos-delivery-platform-btn rounded-xl border border-slate-200 px-3 py-2.5 text-left text-sm font-medium text-slate-700 transition hover:border-emerald-300 hover:bg-emerald-50/40" data-platform="gofood">GoFood</button>
                        <button type="button" class="pos-delivery-platform-btn rounded-xl border border-slate-200 px-3 py-2.5 text-left text-sm font-medium text-slate-700 transition hover:border-emerald-300 hover:bg-emerald-50/40" data-platform="grabfood">GrabFood</button>
                    </div>
                </div>
            </div>

            <div>
                <label class="label" for="pos-step1-customer">Nama pelanggan <span class="font-normal text-slate-400">(opsional)</span></label>
                <input id="pos-step1-customer" class="input" maxlength="255" autocomplete="off">
            </div>

            <div>
                <label class="label" for="pos-step1-notes">Catatan <span class="text-slate-400">(opsional)</span></label>
                <textarea id="pos-step1-notes" class="input" rows="2" maxlength="500" placeholder="Contoh: tanpa sayur, pedas sedang"></textarea>
            </div>

            <p id="pos-step1-error" class="hidden rounded-lg bg-red-50 px-3 py-2 text-xs font-medium text-red-700" role="alert"></p>

            <div class="flex flex-col gap-2 border-t border-slate-100 pt-4 sm:flex-row-reverse">
                <button type="button" id="pos-step1-next" class="btn-primary w-full sm:w-auto">Lanjut</button>
                <button type="button" id="pos-step1-cancel" class="btn-ghost w-full sm:w-auto">Batal</button>
            </div>
        </div>
    </dialog>

    <dialog id="pos-checkout-step2" class="m-auto max-h-[calc(100vh-2rem)] w-[calc(100%-2rem)] max-w-md overflow-hidden rounded-2xl border-0 p-0 shadow-xl backdrop:bg-slate-950/50">
        <div class="flex items-start justify-between border-b border-slate-100 px-5 py-4">
            <div>
                <p class="text-sm font-semibold text-slate-900">Preview Total</p>
                <p class="text-xs text-slate-500">2. Total → 3. Pembayaran</p>
            </div>
            <button type="button" id="pos-step2-close" class="grid h-8 w-8 place-items-center rounded-lg text-xl leading-none text-slate-400 hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">&times;</button>
        </div>
        <div class="max-h-[75vh] space-y-6 overflow-y-auto px-5 py-6">
            <div class="text-center">
                <p class="text-xs font-semibold tracking-wide text-slate-500">TOTAL PEMBAYARAN</p>
                <p id="pos-step2-total" class="mt-2 text-4xl font-bold text-slate-900">Rp 0</p>
            </div>
            <p id="pos-step2-error" class="hidden rounded-lg bg-red-50 px-3 py-2 text-xs font-medium text-red-700" role="alert"></p>
            <div class="flex flex-col gap-2 border-t border-slate-100 pt-4 sm:flex-row-reverse">
                <button type="button" id="pos-step2-next" class="btn-primary w-full sm:w-auto">Lanjut ke Pembayaran</button>
                <button type="button" id="pos-step2-back" class="btn-ghost w-full sm:w-auto">Kembali</button>
            </div>
        </div>
    </dialog>

    <dialog id="pos-checkout-step3" class="m-auto max-h-[calc(100vh-2rem)] w-[calc(100%-2rem)] max-w-lg overflow-hidden rounded-2xl border-0 p-0 shadow-xl backdrop:bg-slate-950/50">
        <div class="flex items-start justify-between border-b border-slate-100 px-5 py-4">
            <div>
                <p class="text-sm font-semibold text-slate-900">Pilih Metode Pembayaran</p>
                <p class="text-xs text-slate-500">3. Pembayaran</p>
            </div>
            <button type="button" id="pos-step3-close" class="grid h-8 w-8 place-items-center rounded-lg text-xl leading-none text-slate-400 hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">&times;</button>
        </div>
        <div class="max-h-[75vh] space-y-4 overflow-y-auto px-5 py-4">
            <div class="grid grid-cols-2 gap-2">
                <button type="button" class="pos-payment-method-btn rounded-xl border border-slate-200 px-3 py-3 text-sm font-semibold text-slate-700 transition hover:border-emerald-300 hover:bg-emerald-50/40" data-method="cash">Tunai</button>
                <button type="button" class="pos-payment-method-btn rounded-xl border border-slate-200 px-3 py-3 text-sm font-semibold text-slate-700 transition hover:border-emerald-300 hover:bg-emerald-50/40" data-method="qris">QRIS</button>
            </div>

            <div id="pos-step3-cash" class="hidden space-y-2">
                <label class="label" for="pos-step3-amount">Nominal uang yang diterima</label>
                <input id="pos-step3-amount" type="number" class="input" min="0" step="500" inputmode="numeric" placeholder="Contoh: 50000">
                <p class="text-xs text-slate-500">Kembalian: <span id="pos-step3-change" class="font-medium text-slate-700">Rp 0</span></p>
            </div>

            <div id="pos-step3-qris" class="hidden">
                <button type="button" id="pos-step3-qris-next" class="btn-primary w-full">Lanjut ke Pembayaran QRIS</button>
                <p class="mt-2 text-xs text-slate-500">QRIS akan ditampilkan pada langkah berikutnya. Pembayaran tetap diverifikasi manual oleh kasir.</p>
            </div>

            <p id="pos-step3-error" class="hidden rounded-lg bg-red-50 px-3 py-2 text-xs font-medium text-red-700" role="alert"></p>

            <div class="flex flex-col gap-2 border-t border-slate-100 pt-4 sm:flex-row-reverse">
                <button type="button" id="pos-step3-pay-cash" class="btn-primary hidden w-full sm:w-auto">Bayar &amp; Selesaikan</button>
                <button type="button" id="pos-step3-back" class="btn-ghost w-full sm:w-auto">Kembali</button>
            </div>
        </div>
    </dialog>

    <dialog id="pos-checkout-qris" class="m-auto max-h-[calc(100vh-2rem)] w-[calc(100%-2rem)] max-w-lg overflow-hidden rounded-2xl border-0 p-0 shadow-xl backdrop:bg-slate-950/50">
        <div class="flex items-start justify-between border-b border-slate-100 px-5 py-4">
            <div>
                <p class="text-sm font-semibold text-slate-900">Pembayaran QRIS</p>
                <p class="text-xs text-slate-500">Verifikasi manual oleh kasir</p>
            </div>
            <button type="button" id="pos-qris-close" class="grid h-8 w-8 place-items-center rounded-lg text-xl leading-none text-slate-400 hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">&times;</button>
        </div>
        <div class="max-h-[80vh] space-y-4 overflow-y-auto px-5 py-5">
            <div class="text-center">
                <p class="text-xs font-semibold tracking-wide text-slate-500">TOTAL PEMBAYARAN</p>
                <p id="pos-qris-final-total" class="mt-2 text-3xl font-bold text-slate-900">Rp 0</p>
            </div>
            @if ($posQrisImageUrl)
                <p id="pos-qris-loading" role="status">Memuat gambar QRIS...</p>
                <img
                    id="pos-qris-image"
                    src="{{ $posQrisImageUrl }}"
                    alt="Gambar QRIS pembayaran"
                    class="mx-auto w-full max-w-[320px] rounded-xl border border-slate-200 bg-white p-2"
                >
                <p id="pos-qris-missing" class="hidden rounded-lg bg-amber-50 px-3 py-2 text-center text-xs font-medium text-amber-700">
                    Gambar QRIS tidak dapat dimuat. Minta admin memeriksa gambar di pengaturan QRIS, lalu coba lagi.
                </p>
                <button type="button" id="pos-qris-retry" class="btn-ghost" hidden>Coba muat gambar lagi</button>
            @else
                <p id="pos-qris-missing" class="rounded-lg bg-amber-50 px-3 py-2 text-center text-xs font-medium text-amber-700">
                    Gambar QRIS belum diatur. Minta admin mengunggahnya melalui menu QRIS sebelum menerima pembayaran QRIS.
                </p>
            @endif
            <p class="text-center text-sm text-slate-600">Minta pelanggan memindai kode QRIS di atas, lalu verifikasi pembayaran melalui aplikasi atau bukti transaksi merchant.</p>
            <label class="flex cursor-pointer items-start gap-2 rounded-lg border border-slate-200 px-3 py-2.5">
                <input id="pos-qris-confirmed" type="checkbox" value="1" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500/40">
                <span class="text-xs text-slate-600">Saya sudah memverifikasi pembayaran ini secara manual. Nominal mengikuti total pesanan.</span>
            </label>
            <p id="pos-qris-error" class="hidden rounded-lg bg-red-50 px-3 py-2 text-xs font-medium text-red-700" role="alert"></p>
            <div class="flex flex-col gap-2 border-t border-slate-100 pt-4 sm:flex-row-reverse">
                <button type="button" id="pos-qris-submit" disabled class="btn-primary w-full disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto">Bayar &amp; Selesaikan</button>
                <button type="button" id="pos-qris-back" class="btn-ghost w-full sm:w-auto">Kembali</button>
            </div>
        </div>
    </dialog>

    <dialog id="pos-discount-dialog" aria-labelledby="pos-discount-title" class="m-auto w-[calc(100%-2rem)] max-w-sm rounded-2xl border-0 p-5 shadow-xl backdrop:bg-slate-950/50">
        <h2 id="pos-discount-title" class="text-lg font-semibold">Diskon Pesanan</h2>
        <label for="pos-discount-type" class="label mt-4">Jenis diskon</label><select id="pos-discount-type" class="input"><option value="fixed">Nominal Rupiah</option><option value="percentage">Persentase</option></select>
        <label for="pos-discount-value" class="label mt-3">Nilai diskon</label><input id="pos-discount-value" class="input" inputmode="decimal" type="number" min="0" max="999999999" step="0.01" placeholder="0">
        <p id="pos-discount-error" role="alert" class="mt-2 text-sm text-red-700" hidden></p>
        <div class="mt-5 flex flex-wrap justify-end gap-2"><button type="button" data-discount-remove class="btn-ghost mr-auto">Hapus diskon</button><button type="button" data-discount-cancel class="btn-secondary">Batal</button><button type="button" data-discount-save class="btn-primary">Simpan</button></div>
    </dialog>
    <dialog id="pos-fee-dialog" aria-labelledby="pos-fee-title" class="m-auto w-[calc(100%-2rem)] max-w-sm rounded-2xl border-0 p-5 shadow-xl backdrop:bg-slate-950/50">
        <h2 id="pos-fee-title" class="text-lg font-semibold">Biaya Tambahan</h2>
        <label for="pos-fee-name" class="label mt-4">Label biaya</label><input id="pos-fee-name" class="input" maxlength="100" placeholder="Tip / Biaya Layanan / Lainnya">
        <label for="pos-fee-value-input" class="label mt-3">Nominal</label><input id="pos-fee-value-input" class="input" inputmode="decimal" type="number" min="0" max="999999999" step="0.01" placeholder="0">
        <p id="pos-fee-error" role="alert" class="mt-2 text-sm text-red-700" hidden></p>
        <div class="mt-5 flex flex-wrap justify-end gap-2"><button type="button" data-fee-remove class="btn-ghost mr-auto">Hapus biaya</button><button type="button" data-fee-cancel class="btn-secondary">Batal</button><button type="button" data-fee-save class="btn-primary">Simpan</button></div>
    </dialog>

    <dialog id="pos-topping-modal" class="m-auto max-h-[calc(100vh-2rem)] w-[calc(100%-2rem)] max-w-md overflow-hidden rounded-2xl border-0 p-0 shadow-xl backdrop:bg-slate-950/50">
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

    <form id="pos-order-form" action="{{ route('orders.store') }}" method="POST" class="hidden">
        @csrf
        <input type="hidden" name="type" id="pos-form-type">
        <input type="hidden" name="customer_name" id="pos-form-customer">
        <input type="hidden" name="table_number" id="pos-form-table">
        <input type="hidden" name="delivery_address" id="pos-form-address">
        <input type="hidden" name="delivery_platform" id="pos-form-platform">
        <input type="hidden" name="notes" id="pos-form-notes">
        <input type="hidden" name="payment_status" id="pos-form-payment-status">
        <input type="hidden" name="payment_method" id="pos-form-payment-method">
        <input type="hidden" name="payment_confirmed" id="pos-form-payment-confirmed">
        <input type="hidden" name="amount_paid" id="pos-form-amount-paid">
    </form>
@endsection

@push('scripts')
    <script>
        window.posToppings = @json($posToppings);
        window.posProductToppings = @json($posProductToppings);
        window.posProductImages = @json($posProductImages);
    </script>
@endpush
