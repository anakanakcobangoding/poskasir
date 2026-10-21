# WarungCerdas POS — Deployment Audit (Phase 1–2)

Audit ini dilakukan pada checkout lokal pada 21 Oktober 2026. Cakupan hanya inspeksi read-only, pemeriksaan kode, dan test suite yang dikonfigurasi memakai SQLite in-memory. Tidak ada koneksi ke MySQL usaha, database Supabase, Vercel, Railway, atau Cloudflare; tidak ada migrasi, deployment, pembelian layanan, atau perubahan data.

## Ringkasan keputusan

**Rekomendasi staging: arsitektur B — host aplikasi yang menjalankan Laravel sebagai service web persisten, Supabase PostgreSQL untuk database, dan Supabase Storage untuk gambar.** Railway adalah pilihan yang sesuai dengan pola ini dan memiliki panduan Laravel resmi; layanan Laravel-compatible lain juga dapat dipertimbangkan setelah biaya, wilayah, dan batas plan diverifikasi. Jangan membuat resource sebelum ada persetujuan.

**Vercel: conditional, bukan rekomendasi pertama untuk staging POS ini.** Vercel menyediakan runtime PHP melalui runtime komunitas `vercel-php` dan contoh routing satu entrypoint, tetapi PHP bukan runtime resmi Vercel. Aplikasi ini belum memiliki adapter/entrypoint Vercel dan memakai Laravel sebagai monolith dengan session, cache, dan queue database serta filesystem lokal. Persistensi file harus dipindahkan, dan seluruh request Laravel harus diuji pada runtime komunitas tersebut. Ini bukan ketidakmungkinan teknis, tetapi menambah ketergantungan dan risiko operasional tanpa manfaat yang jelas untuk demo POS.

**Tidak ada perubahan aplikasi.** `DEPLOYMENT_AUDIT.md` adalah satu-satunya file baru yang diminta sebagai deliverable audit. File ini belum di-commit atau di-push. Tidak ada branch staging yang dibuat karena Phase 3 memerlukan persetujuan setelah laporan ini.

## 1. Arsitektur saat ini

- Framework terpasang: Laravel Framework **13.37.0**; PHP lokal **8.4.25**; `composer.json` mengizinkan PHP `^8.3`; Composer **2.10.2**.
- **Livewire tidak terpasang/ditemukan** pada `composer.json`, `composer.lock`, `app`, `resources`, atau `routes`. Implementasi UI yang ada menggunakan Blade dan JavaScript biasa. Konteks proyek “Laravel + Livewire” tidak sesuai dengan checkout yang diaudit.
- Node lokal **v24.21.0**, npm **11.19.0**. Build menggunakan Vite **8.3.4**, Laravel Vite Plugin **3.2.0**, Tailwind Vite **4.3.3**, dan Tailwind **4.3.3**. Script build adalah `vite build`. Target hosting harus menyediakan Node yang cocok untuk Vite 8 saat build.
- Dependency utama: `laravel/framework ^13.17`, `barryvdh/laravel-dompdf ^3.1` (terpasang 3.1.2), `laravel/tinker ^3.0`; paket dev meliputi Pest **5.3.3**, Pint **1.32.1**, Laravel Boost **2.10.3**. Adapter `league/flysystem-aws-s3-v3` **belum terpasang**, sehingga disk S3/Supabase belum siap digunakan melalui Flysystem.
- Route saat ini berjumlah **49**, memakai session auth Laravel dan middleware role/admin. Route meliputi Welcome, POS, order/struk, katalog, profil toko, QRIS, dashboard, laporan dan ekspor.
- Tidak ditemukan `vercel.json`, Dockerfile, Procfile, Railway config, Render config, Fly config, atau workflow GitHub Actions. `artisan` ada; `routes/console.php` hanya mendaftarkan perintah contoh `inspire`. Tidak ditemukan job `ShouldQueue` atau dispatch job aplikasi. Tidak ada task scheduler aplikasi yang ditemukan.

### Database aplikasi dari migration source

Tidak dilakukan query database lokal atau produksi. Daftar tabel/relasi di bawah bersumber dari migration repo, bukan introspeksi schema live:

| Tabel | Struktur dan relasi utama |
|---|---|
| `users` | bigint ID, nama/email/password, unique email, role dan `is_active` ber-index; timestamps. |
| `password_reset_tokens`, `sessions` | Tabel autentikasi Laravel; session menyimpan user ID nullable dan indeks aktivitas. |
| `categories` | bigint ID, unique nama, urutan, aktif, timestamps. |
| `products` | FK kategori dengan `RESTRICT`, nama, harga decimal(12,2), HPP nullable, image path nullable, tersedia, timestamps. |
| `toppings` | unique nama, harga decimal(12,2), HPP/image nullable, tersedia, timestamps. |
| `product_topping` | FK produk dan topping, cascade delete, unique pasangan produk+topping. |
| `orders` | unique order number; type/status/payment method/payment status berupa enum migration; subtotal/total/paid/change, data pelanggan/pengiriman, diskon/biaya tambahan, paid_at, timestamps; indeks status dan created_at. |
| `order_items` | FK order cascade; product nullable dengan `SET NULL`; nama/harga/quantity/subtotal/HPP snapshot; catatan, timestamps. |
| `order_item_toppings` | FK order item cascade; topping nullable dengan `SET NULL`; nama/harga/quantity/HPP snapshot; timestamps. |
| `settings` | unique key dan value; grup/keterangan nullable. Menyimpan identitas toko, path logo/QRIS, dan status operasional. |
| `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs` | Tabel framework untuk cache/locks, queue dan failed jobs. |

ID dibuat lewat `$table->id()` dan FK memakai `foreignId()`. Nilai uang menggunakan `decimal(12,2)`; kuantitas/urutan menggunakan integer unsigned pada migration MySQL. Foreign key, unique key, serta beberapa indeks ditentukan di migration. Tidak ditemukan kolom JSON atau penggunaan fungsi SQL MySQL-spesifik pada query aplikasi yang diperiksa. Query agregasi laporan memakai `SUM`, `CASE`, dan `COUNT(CASE ...)`; ini perlu tetap diuji terhadap PostgreSQL.

### Request, auth, transaksi, dan aset

- Session default adalah `database`; cache default `database`; queue default `database`. `phpunit.xml` mengganti semuanya menjadi array/sync dan memakai SQLite `:memory:` untuk test. Aplikasi tidak memiliki Livewire request lifecycle yang perlu dipindahkan; seluruh route adalah request Laravel biasa.
- Checkout berada di `OrderService`, memakai transaksi DB, menghitung harga dari data server, menyimpan snapshot order/item/topping/HPP dan memeriksa status toko. `lockForUpdate()` dipakai pada beberapa alur. Perilaku locking/unique key harus diuji pada PostgreSQL nyata karena engine sekarang berbeda.
- Login/logout dan session berbasis Laravel; pengguna aplikasi tidak bergantung pada Supabase Auth. Supabase Auth tidak diperlukan kecuali keputusan produk nanti mengubah sistem autentikasi.
- File produk, topping, logo toko, dan QRIS saat ini disimpan pada disk `public` lokal di `storage/app/public`. Kolom image/settings menyimpan path file. Controller stream gambar melalui route Laravel; ini dapat dipertahankan dengan private bucket dan disk remote, tetapi disk saat ini hard-coded ke `public` di beberapa controller.
- Checkout QRIS adalah QRIS statis gambar dan keputusan bayar manual oleh kasir; tidak ada payment gateway. Struk memakai Blade; PDF memakai `barryvdh/laravel-dompdf`. Ekspor CSV memakai response stream. PDF/CSV tidak memerlukan worker terpisah, tetapi perlu uji memory, ukuran response dan timeout hosting dengan data staging representatif.
- Upload gambar divalidasi maksimal 2 MB dan format JPG/PNG/WebP. Batas itu di bawah batas body request Vercel 4.5 MB, tetapi request multipart dan overhead perlu diuji.
- `public/storage` pada checkout lokal adalah symlink yang menunjuk ke path absolut mesin developer `/Users/ihsanfawwaszaidan/Herd/Daftarkita/storage/app/public`; symlink ini tidak tercatat sebagai file Git. Deployment tidak boleh mengandalkannya. Route stream gambar saat ini memang membaca disk lokal, sedangkan penggunaan URL `/storage` langsung perlu diaudit sebelum storage dipindah.

## 2. Risiko kompatibilitas

### Vercel

Vercel mendokumentasikan runtimes PHP sebagai **community runtimes**, dan halaman konfigurasi memberi contoh `vercel-php` melalui `vercel.json`. Repository runtime komunitas saat audit mendokumentasikan versi PHP 8.4 pada `vercel-php@0.8.0`, satu PHP entrypoint dan rewrite seluruh path ke entrypoint tersebut. Itu menunjukkan Laravel mungkin dapat dijalankan, tetapi bukan jaminan kompatibilitas bagi repo ini: belum ada entrypoint Laravel, rewrite, bundle Composer, atau verifikasi request, session, CSRF, upload, dan PDF di runtime Vercel.

Vercel Functions memiliki filesystem read-only dengan `/tmp` sementara; deployment tidak dapat menyimpan upload permanen di filesystem lokal. Function juga memiliki batas payload **4.5 MB** dan durasi eksekusi berdasarkan plan/runtime. Cold start dan boot Laravel pada setiap function invocation perlu diukur; koneksi DB harus aman terhadap burst koneksi dan memakai Supabase session pooler atau mekanisme pooling yang kompatibel.

Tidak ada scheduler maupun job aplikasi saat ini, sehingga tidak menjadi blocker langsung. Jika fitur itu ditambahkan, Vercel Cron/worker atau service terpisah harus disiapkan; tidak boleh mengandalkan daemon queue yang hidup di dalam function.

**Kesimpulan Vercel:** layak untuk eksperimen staging terisolasi hanya jika runtime komunitas diterima, database dan storage eksternal digunakan, dan semua alur POS lolos test live. Tidak direkomendasikan sebagai staging pertama yang harus stabil dan mudah direproduksi. Karena aplikasi POS menyimpan transaksi bernilai, runtime Laravel persisten lebih aman dari sisi operasional.

### Supabase PostgreSQL

Laravel memiliki koneksi `pgsql` bawaan pada `config/database.php`, dan ekstensi `pdo_pgsql` tersedia di mesin audit. Supabase mendokumentasikan penggunaan Laravel dengan PostgreSQL dan menganjurkan SSL `require`; untuk ORM Laravel, gunakan direct connection yang kompatibel atau **Session Pooler**, bukan Transaction Pooler sebagai koneksi utama karena dukungan prepared statements.

Migration menggunakan enum Laravel; grammar PostgreSQL Laravel 13 mengubahnya menjadi `varchar` dengan `CHECK`, jadi enum bukan blocker otomatis. Modifier `after()` dari migration MySQL tidak mengatur urutan kolom PostgreSQL dan hanya kosmetik. Modifier `unsigned` tidak memberikan constraint non-negatif yang setara secara portable; nilai yang harus selalu non-negatif perlu dijaga oleh validasi/domain dan, jika diperlukan, constraint DB eksplisit. Foreign keys, auto IDs, decimal, nullable dan unique perlu diverifikasi dari migration yang dibuat pada PostgreSQL staging. Tidak ada bukti test migration penuh terhadap PostgreSQL pada audit ini.

Strategi migrasi bukan mengganti koneksi `.env` lokal. Buat project dan database staging terpisah setelah persetujuan, jalankan migration di sana, lalu migrasikan hanya salinan data yang disetujui. IDs dan FK dapat dipertahankan dengan urutan parent sebelum child, lalu sequence PostgreSQL disetel ke nilai maksimum ID. Rekonsiliasi row count per tabel, orphan FK, unique collisions, decimal totals, settings/image paths, dan order snapshots sebelum cutover. Data transaksi historis tidak boleh direkalkulasi.

Supabase Storage mendukung akses S3-compatible dan kontrol bucket/policy. Untuk aplikasi ini, opsi aman awal adalah bucket **private** dengan akses dari server Laravel (stream atau signed URL terbatas), karena endpoint gambar sekarang berada di balik autentikasi. `service_role` atau secret storage tidak boleh dikirim ke browser. Perpindahan membutuhkan adapter Flysystem S3 yang belum tersedia, konfigurasi disk, penggantian pemanggilan hard-coded, dan migrasi file dengan checksum. Jangan hapus file lokal sampai semua object, referensi path, dan rendering diverifikasi.

## 3. Perubahan yang dibutuhkan sebelum staging

Estimasi relatif; belum dilakukan:

| Area | Perubahan minimum | Kompleksitas |
|---|---|---|
| Arsitektur hosting | Pilih host Laravel persisten; set service web dan staging-only environment variables. Tidak ada resource dibuat. | Rendah–sedang |
| PostgreSQL | Review dan uji semua migrations terhadap versi PostgreSQL Supabase; validasi tipe, constraint, index, lock, dan agregasi; buat database test PostgreSQL terisolasi. | Sedang–tinggi |
| Storage | Tambah adapter S3, disk Supabase Storage terkonfigurasi env, ubah disk hard-coded ke satu disk yang dapat dipilih; migrasi sample files dan verifikasi stream/private access. | Sedang |
| Runtime | Pin PHP >=8.4 dan ekstensi (`pdo_pgsql`, `fileinfo`, `gd`/`imagick` sesuai DomPDF); Node >=20.19 atau >=22.12 untuk Vite 8. | Rendah |
| Session/cache/queue | Gunakan PostgreSQL staging untuk session/cache database. Queue saat ini tidak digunakan oleh app; gunakan `sync` untuk staging awal atau worker terpisah jika queue diperkenalkan. Pastikan migrations tabel Laravel tersedia. | Rendah |
| Rilis aman | Build asset di pipeline, migrasi sebagai langkah release yang eksplisit setelah backup dan verifikasi staging; healthcheck, log stderr, backup/restore dan rollback terdokumentasi. | Sedang |
| Vercel alternatif | Tambahkan runtime-community entrypoint/rewrite dan uji semua route/browser/database/storage/PDF; jangan lanjut jika ada state lokal atau runtime mismatch. | Tinggi |

## 4. Rekomendasi arsitektur staging

1. **Aplikasi:** service Laravel persisten pada host Laravel-compatible (Railway adalah kandidat paling dekat dengan workflow yang sudah pernah dipertimbangkan); deploy branch `deployment/staging` setelah persetujuan Phase 3. Hanya staging yang boleh mendapat domain publik sementara dan data sintetis/salinan yang sudah disetujui.
2. **Database:** Supabase PostgreSQL project khusus staging. Pakai Laravel connection `pgsql`, SSL wajib, dan Session Pooler/direct connection yang kompatibel. Jangan ubah konfigurasi koneksi lokal.
3. **Gambar:** Supabase Storage bucket staging private; app memegang kredensial server-side. Pertahankan path key relatif di kolom database dan transfer file secara terverifikasi. Untuk produksi nanti, evaluasi bucket, backup, egress dan kebijakan retensi terpisah.
4. **Session/cache:** database session dan cache di PostgreSQL staging untuk mendukung banyak instance. Cache lock dan checkout idempotensi perlu tes concurrency PostgreSQL. Tidak ada worker/scheduler yang diperlukan oleh fungsi saat ini.
5. **Build/release:** Node build `npm run build`; deploy PHP 8.4+ dengan `pdo_pgsql`, ekstensi image/PDF yang dibutuhkan, `APP_ENV=production`, `APP_DEBUG=false`, APP_KEY baru khusus staging, URL HTTPS, dan env database/storage staging. Migration hanya setelah salinan staging tersedia dan tes berhasil.
6. **Domain:** jangan beli domain. Gunakan domain sementara bawaan host untuk demo internal; Cloudflare DNS/domain baru hanya jika diminta dan disetujui.

**Fallback:** jika host utama tidak mendukung PHP 8.4, PostgreSQL SSL/pooling, atau storage privat secara terverifikasi, pilih host Laravel container/service lain sebelum menulis adapter khusus Vercel. Vercel tetap fallback eksperimen, bukan tempat database atau gambar disimpan lokal.

### Biaya dan batas free tier yang relevan

- Vercel Hobby saat ini gratis, tetapi persyaratannya untuk penggunaan personal/non-komersial; kurang cocok untuk POS toko yang benar-benar dipakai sebagai bisnis. Vercel Pro tercantum mulai **US$20/bulan** dengan kredit penggunaan tertentu; runtime functions tetap dapat menimbulkan biaya sesuai penggunaan.
- Supabase Free tercantum menyediakan 500 MB database dan 1 GB file storage, maksimal dua project aktif, dan project dapat dipause setelah satu minggu tidak aktif. Cocok untuk demo staging kecil, bukan asumsi SLA produksi. Supabase Pro tercantum mulai **US$25/bulan** dan termasuk backup harian 7 hari, namun biaya egress/storage tambahan dapat berlaku.
- Railway/host lain, compute/database, bandwidth dan volume dapat berbiaya; harga tepat bergantung plan/region dan belum diverifikasi untuk akun pengguna. Jangan membuat resource atau mengaktifkan billing tanpa persetujuan eksplisit.
- Cloudflare DNS tidak memerlukan pembelian domain dari Cloudflare, tetapi domain tetap perlu dibeli dari registrar. Tidak ada domain yang dibeli atau resource Cloudflare dibuat.

## 5. Pemeriksaan dan bukti

| Pemeriksaan | Hasil aktual |
|---|---|
| `git status -sb`, branch | Awal audit: `main...origin/main`, working tree bersih. Tidak ada perubahan lokal sebelumnya. |
| Dependency | `composer show --direct`: Laravel 13.37.0, DomPDF 3.1.2, Pest 5.3.3; `npm ls --depth=0`: Vite 8.3.4/Tailwind 4.3.3; tidak ada Livewire. |
| `composer validate --no-check-publish` | Exit 0, composer.json valid. |
| `php artisan route:list --except-vendor` | Exit 0, 49 routes. |
| `php artisan test --compact` | Exit 1: 289 tes, 283 lulus, 6 gagal, 1.598 assertions. Kegagalan mengharapkan `/` atau login mengarah langsung ke root, sedangkan route sekarang redirect ke `/welcome`; akar spesifik terlihat di `tests/Feature/ExampleTest.php:8`, `StoreLogoTest.php:19`, `StoreOperationTest.php:62`, `WelcomeBrandingTest.php:8,15,22`. Tes menggunakan SQLite in-memory sesuai `phpunit.xml`; tidak menyentuh MySQL lokal. |
| Build frontend | Tidak dijalankan karena menulis artefak; tidak ada hasil build baru dalam audit ini. |
| Migration PostgreSQL/live database | Tidak dijalankan. Tidak ada koneksi atau query terhadap MySQL usaha/Supabase. |
| Railway/Vercel/Supabase project settings | Tidak diperiksa melalui akun pengguna; konfigurasi runtime, secrets, cost tier, backup dan networking aktual belum diverifikasi. |
| File symlink | `public/storage` pada checkout lokal menunjuk ke path absolut workstation; tidak tercatat di Git. |

## 6. Referensi resmi yang diperiksa

- [Vercel runtimes: runtime PHP adalah community runtime](https://vercel.com/docs/functions/runtimes)
- [Vercel konfigurasi runtime function](https://vercel.com/docs/functions/configuring-functions/runtime)
- [vercel-community/php README: versi PHP, route ke satu entrypoint, contoh Laravel](https://github.com/vercel-community/php)
- [Vercel Function limits](https://vercel.com/docs/functions/limitations)
- [Supabase: menggunakan PostgreSQL dengan Laravel, SSL dan Session Pooler](https://supabase.com/docs/guides/getting-started/quickstarts/laravel)
- [Supabase Storage](https://supabase.com/docs/guides/storage)
- [Supabase Storage S3 compatibility](https://supabase.com/docs/guides/storage/s3/compatibility)
- [Railway: Deploy a Laravel App, termasuk Postgres, queue dan cron services](https://docs.railway.com/guides/laravel)
- [Railway persistent services and deployment](https://docs.railway.com/build-deploy)
- [Vercel Hobby restrictions and limits](https://vercel.com/docs/plans/hobby)
- [Vercel pricing](https://vercel.com/pricing)
- [Supabase pricing and quotas](https://supabase.com/pricing)

## Gate sebelum Phase 3

Phase 3 belum dimulai. Sebelum staging preparation, minta persetujuan eksplisit untuk: (1) membuat branch lokal `deployment/staging` tanpa mengubah `main`; (2) memilih dan membuat project Supabase staging yang dapat menimbulkan biaya; (3) mengizinkan perubahan deployment-only pada branch staging; dan (4) apakah staging boleh dipublikasikan pada URL sementara. Tidak ada production cutover atau migrasi data produksi dalam lingkup staging ini.
