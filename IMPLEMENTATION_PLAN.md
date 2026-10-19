# IMPLEMENTATION PLAN — WarungCerdas POS (MVP 7 Hari)

Proyek: Daftarkita (folder lokal tidak diubah) — identitas aplikasi: **WarungCerdas POS**
Stack: Laravel 13.37 · PHP 8.4 · Blade + Tailwind CSS 4 (Vite 8) · MySQL (target produksi) · Pest 5

## Prinsip

- Struktur sederhana: satu store (tanpa multi-tenant), Blade server-rendered, tanpa framework JS tambahan.
- Validasi backend (FormRequest/`$request->validate()`), semua operasi tulis dalam **DB transaction**.
- Uang disimpan sebagai **decimal(12,2)** di kolom uang (cast `decimal:2` di model) — keputusan Tahap 1, menggantikan rencana awal integer Rupiah.
- Setiap fitur punya test Pest (feature test) sebelum lanjut ke fitur berikutnya.
- Tidak ada secret di source code; API key AI hanya di `.env`.

## Rancangan Database (MySQL, utf8mb4)

| Tabel | Tujuan | Kolom utama |
|---|---|---|
| `categories` | Kategori menu | `name`, `sort_order`, `is_active` |
| `products` | Item menu | `category_id` FK, `name`, `price` (decimal 12,2), `is_available` |
| `toppings` | Topping global | `name`, `price` (decimal 12,2), `is_available` |
| `orders` | Pesanan | `order_number` (unik, dibuat otomatis), `type` enum `DLV,DI,TA`, `status` enum `new,preparing,ready,completed,cancelled`, `customer_name?`, `table_number?` (DI), `delivery_address?` (DLV), `subtotal`, `total`, `payment_method` enum `cash,qris,NULL`, `amount_paid`, `change`, `payment_status` enum `unpaid,paid`, `paid_at`, `notes` |
| `order_items` | Item pesanan (snapshot harga) | `order_id` FK, `product_id` FK nullable, `product_name`, `unit_price`, `quantity`, `subtotal`, `notes` |
| `order_item_toppings` | Topping per item (snapshot harga) | `order_item_id` FK, `topping_id` FK nullable, `topping_name`, `price`, `quantity` |
| `settings` | Pengaturan toko (key-value) | `key` unik, `value` (mis. gambar/static QRIS, nama toko) |

Catatan:
- `product_name`/`topping_name` di-order disimpan sebagai **snapshot** agar riwayat tetap benar walau menu diubah/dihapus.
- Tipe pesanan: **DLV** (delivery), **DI** (dine-in), **TA** (takeaway).
- Pembayaran: **cash** (input nominal → hitung kembalian) dan **QRIS manual** (kasir tandai bukti bayar sendiri, tanpa payment gateway).
- Kolom wajib tergantung tipe pesanan divalidasi di backend: `DI` wajib `table_number`, `DLV` wajib `delivery_address`.
- Belum ada tabel `users` khusus kasir untuk MVP: operasi POS berjalan sebagai satu sesi toko (auth sederhana/disimpan untuk fase lanjutan bila diminta).

## Struktur Kode (yang akan dibuat)

```
app/Http/Controllers/
  CategoryController.php  ProductController.php  ToppingController.php   # manajemen menu
  PosController.php        # layar kasir + create order (transaction)
  OrderController.php      # pesanan aktif + riwayat + detail
  PaymentController.php    # bayar cash/QRIS + update status
  DashboardController.php  # ringkasan dashboard
  ReportController.php     # laporan + ekspor PDF
  InsightController.php    # AI Business Insight
app/Http/Requests/         # validasi backend per form
app/Models/                # Category, Product, Topping, Order, OrderItem, OrderItemTopping, Setting
app/Enums/                 # OrderType, OrderStatus, PaymentMethod, PaymentStatus (Tahap 1)
app/Services/OrderService.php  # create order + pembayaran dalam DB::transaction
resources/views/
  layouts/app.blade.php
  pos/index.blade.php            orders/{active,history,show}  dashboard  reports  menu CRUD
routes/web.php              # semua route named + CSRF (Blade default)
database/migrations/        # 7 migrasi baru di atas (tidak menyentuh migrasi bawaan)
tests/Feature/              # ModelRelationsTest, DemoMenuSeederTest (Tahap 1); lalu MenuManagementTest, PosOrderTest, PaymentTest, ReportTest, ...
```

## Jadwal 7 Hari

| Hari | Sasaran | Test |
|---|---|---|
| 1 | Setup MySQL + git + identitas (selesai), layout Blade+Tailwind, migrasi & model + factory | migrasi jalan, model factory |
| 2 | Manajemen menu: kategori, produk, topping (CRUD, aktif/nonaktif) | `MenuManagementTest` |
| 3 | Layar kasir: grid menu, keranjang (JS ringan di Blade), validasi tipe pesanan, `OrderService` transaksi | `PosOrderTest` (termasuk rollback) |
| 4 | Pesanan aktif (board status DLV/DI/TA), riwayat + filter tanggal/tipe, halaman struk | `OrderLifecycleTest` |
| 5 | Pembayaran cash (kembalian) & QRIS manual, kunci perubahan status bayar | `PaymentTest` |
| 6 | Dashboard (omzet, pesanan, menu terlaris) + laporan PDF (harian/mingguan, per tipe & metode) | `DashboardTest`, `ReportTest` |
| 7 | AI Business Insight (heuristik offline: tren omzet, jam sibuk, menu stagnan; opsi LLM API via `.env` key), polish UI, full suite | `InsightTest` + full run |

### Ketergantungan baru yang butuh persetujuan Anda

1. **PDF**: `barryvdh/laravel-dompdf` — standar untuk ekspor PDF Laravel. (Alternatif: print-view browser tanpa paket.)
2. **AI Insight fase 1** dibuat **tanpa paket baru** (heuristik dari data penjualan). Integrasi LLM (opsional, fase 2) cukup pakai `guzzlehttp/guzzle` yang sudah terpasang + API key di `.env`.

## Status Setup

- [x] Git diinisialisasi, initial commit bersih (`.env`, sqlite, vendor, node_modules, logs ter-ignore)
- [x] `APP_NAME="WarungCerdas POS"` di `.env` dan `.env.example`
- [x] MySQL server berjalan (FlyEnv MySQL 26.7, `127.0.0.1:3306`), database `warungcerdas_pos` terisi di `.env` dan terverifikasi
- [x] Test suite baseline hijau (SQLite `:memory:`)
- [x] **Tahap 1 — fondasi**: 7 migration baru, 7 model + relasi/casts, 4 enum (`OrderType`, `OrderStatus`, `PaymentMethod`, `PaymentStatus`), 5 factory, `DemoMenuSeeder` (idempoten), 16 test Pest lulus
- [x] **Migrasi MySQL**: 10 migrasi Ran (batch 1: 3 baseline + 7 fondasi POS) — diverifikasi via `migrate:status` dan `db:show`
- [x] **Seeding MySQL**: `db:seed --class=DemoMenuSeeder` sukses — 4 kategori, 17 produk, 7 topping (semua `is_available`)
- [x] Commit Tahap 1: `78dc9e2` (feat: add WarungCerdas POS database foundation)
- [x] **Tahap 2 — manajemen menu + UI kasir**: `routes/web.php` (redirect `/` → `pos.index`, 3 resource route tanpa `show`), layout POS + komponen Blade (`nav-link`, `money`, `flash`, `checkbox`), kelas komponen Tailwind 4 di `app.css`, `CategoryController`/`ProductController`/`ToppingController` + 6 FormRequest (validasi + pesan Indonesia), scope `Category::active()`/`Product::available()`, halaman kasir read-only (grid menu, filter kategori, pencarian, keranjang + modal topping via vanilla JS di `resources/js/app.js`), `MenuManagementTest` + `PosPageTest`, `ExampleTest` disesuaikan ke redirect `/`
- [x] **Verifikasi Tahap 2**: 32 test lulus (112 assertions), `pint --dirty` passed, `node --check` valid, `npm run build` sukses
- [x] **Tahap 3 — simpan pesanan**: `StoreOrderRequest` (validasi tipe `TA`/`DI`/`DLV`, `table_number` wajib untuk `DI`, `delivery_address` wajib untuk `DLV`, item & topping `exists`), `OrderService` (satu transaksi + retry terbatas untuk bentrok nomor, validasi ketersediaan, snapshot nama/harga dari DB, subtotal = (harga produk + Σ topping) × qty, total server-side, nomor harian `WC-YYYYMMDD-###` berbasis zona waktu `Asia/Jakarta`), `OrderController@store` + `POST /orders` (`orders.store`) mengembalikan JSON `201`, form tipe pesanan + field kondisional + catatan di halaman kasir, dan pengiriman JSON via `fetch` + CSRF di `resources/js/app.js` (keranjang hanya dikosongkan setelah server mengonfirmasi), `PosOrderTest`
- [x] **Verifikasi Tahap 3**: 53 test lulus (221 assertions), `pint --dirty` passed, `node --check` valid, `npm run build` sukses, `git diff --check` bersih
- [x] **Tahap 4 — manajemen pesanan aktif + struk**: `OrderStatusService` (peta transisi `New→Preparing→Ready→Completed`, pembatalan hanya dari `New`/`Preparing`/`Ready`, `Completed`/`Cancelled` terminal, tolak no-op & lompatan; label + kelas badge), `IndexOrderRequest` (filter `status`/`type`, nilai tak dikenal diabaikan tanpa redirect), `UpdateOrderStatusRequest`, `OrderController` (`index` default pesanan aktif + filter/pagination 15, `show` detail item+topping, `updateStatus`, `receipt`), 4 route baru (`orders.index`/`orders.show`/`orders.status`/`orders.receipt`), nav "Pesanan", view `orders/{index,show,receipt}` + `layouts/print` + komponen `order-status-badge`/`order-type-badge`, waktu ditampilkan `Asia/Jakarta` (penyimpanan tetap UTC), `OrderManagementTest` + `OrderReceiptTest`
- [x] **Verifikasi Tahap 4**: 73 test lulus (310 assertions), `pint --dirty` passed, `node --check` valid, `npm run build` sukses, `git diff --check` bersih, file terlindungi (database/config/.env/dependency) tidak berubah
- [ ] Tahap 4 belum mencakup: pembayaran, laporan, AI (sesuai batas scope)
- [ ] Menunggu approval: rencana Tahap 5 (pembayaran)

## Aturan Prosedural

- Dilarang: `migrate:fresh`, menghapus database, menimpa file pengguna, menampilkan isi `.env`.
- Setiap selesai modul: `vendor/bin/pint --dirty --format agent` + `php artisan test --compact`.
- Perubahan dependensi hanya setelah persetujuan.
