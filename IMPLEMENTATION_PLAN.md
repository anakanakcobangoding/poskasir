# IMPLEMENTATION PLAN — WarungCerdas POS (MVP 7 Hari)

Proyek: Daftarkita (folder lokal tidak diubah) — identitas aplikasi: **WarungCerdas POS**
Stack: Laravel 13.37 · PHP 8.4 · Blade + Tailwind CSS 4 (Vite 8) · MySQL (target produksi) · Pest 5

## Prinsip

- Struktur sederhana: satu store (tanpa multi-tenant), Blade server-rendered, tanpa framework JS tambahan.
- Validasi backend (FormRequest/`$request->validate()`), semua operasi tulis dalam **DB transaction**.
- Uang disimpan sebagai **integer Rupiah** (tanpa desimal/pembulatan).
- Setiap fitur punya test Pest (feature test) sebelum lanjut ke fitur berikutnya.
- Tidak ada secret di source code; API key AI hanya di `.env`.

## Rancangan Database (MySQL, utf8mb4)

| Tabel | Tujuan | Kolom utama |
|---|---|---|
| `categories` | Kategori menu | `name`, `sort_order`, `is_active` |
| `products` | Item menu | `category_id` FK, `name`, `price` (int), `is_available` |
| `toppings` | Topping global | `name`, `price` (int), `is_available` |
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
app/Services/OrderService.php  # create order + pembayaran dalam DB::transaction
resources/views/
  layouts/app.blade.php
  pos/index.blade.php            orders/{active,history,show}  dashboard  reports  menu CRUD
routes/web.php              # semua route named + CSRF (Blade default)
database/migrations/        # 7 migrasi baru di atas (tidak menyentuh migrasi bawaan)
tests/Feature/              # MenuManagementTest, PosOrderTest, PaymentTest, ReportTest, ...
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
- [x] MySQL server berjalan (FlyEnv MySQL 26.7, `127.0.0.1:3306`) — **koneksi belum dikonfigurasi** (lihat blokir di bawah)
- [x] Test suite baseline hijau (SQLite `:memory:`)
- [ ] **Butuh tindakan Anda**: buat database + isi kredensial MySQL di `.env`:
  ```
  mysql -u<user> -p -e "CREATE DATABASE warungcerdas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
  ```
  Lalu di `.env`: `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_DATABASE=warungcerdas`, `DB_USERNAME=...`, `DB_PASSWORD=...`
- [ ] `php artisan migrate` di MySQL setelah kredensial terisi (tidak memakai `migrate:fresh`)

## Aturan Prosedural

- Dilarang: `migrate:fresh`, menghapus database, menimpa file pengguna, menampilkan isi `.env`.
- Setiap selesai modul: `vendor/bin/pint --dirty --format agent` + `php artisan test --compact`.
- Perubahan dependensi hanya setelah persetujuan.
