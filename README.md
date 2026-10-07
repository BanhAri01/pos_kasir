# Hermes POS

Aplikasi kasir (POS) SaaS untuk UMKM Indonesia. Tampilannya besar, sederhana, dan berbahasa
sehari-hari, supaya mudah dipakai orang yang jarang memakai aplikasi selain WhatsApp.

Stack: Laravel 12 · Inertia v3 + Vue 3 · Tailwind CSS 4 · MySQL/MariaDB · Pest

## Menjalankan di komputer lokal (XAMPP)

```bash
composer install
npm install
cp .env.example .env          # lalu sesuaikan DB_* bila perlu
php artisan key:generate

# buat database "hermes_pos" (utf8mb4) di MySQL, lalu:
php artisan migrate --seed                  # data acuan: role, modul, jenis usaha
php artisan db:seed --class=DemoSeeder      # akun contoh (opsional)

npm run build                 # atau `npm run dev` saat mengubah tampilan
php artisan serve             # buka http://localhost:8000
```

### Akun contoh (DemoSeeder): Kedai Kopi Bu Sri

| Siapa   | Cara masuk                              |
|---------|-----------------------------------------|
| Pemilik | No HP `0812 3456 7890`, sandi `rahasia123` (PIN `9999`) |
| Manajer | No HP `0812 3456 7891`, sandi `rahasia123` (PIN `4321`) |
| Kasir   | PIN `1234` (Budi)                        |
| Barista | PIN `5678` (Dewi)                        |

Masuk dengan PIN hanya bisa di HP/browser yang sebelumnya sudah dipakai pemilik atau manajer
untuk masuk dengan no HP.

## Menjalankan test

```bash
php artisan test
```

Test memakai SQLite di memori, jadi tidak menyentuh database MySQL.

## Struktur kode

```
app/Core/            Fondasi lintas modul
  Tenancy/           BelongsToTenant, TenantScope (fail-closed), TenantContext, middleware
  Modules/           ModuleManager, middleware module:{code}
  Support/           Phone (format no HP 628xxx)
app/Modules/<Nama>/  Satu folder per fitur: Http/{Controllers,Requests,Resources}, Policies,
                     Services, routes/web.php (dimuat otomatis oleh ModuleServiceProvider)
app/Enums/           Role & Permission
resources/js/
  Components/ui/     Komponen senior-friendly: BigButton, BigInput, PinPad, ConfirmDialog, ...
  Layouts/ Pages/    Halaman Inertia
resources/css/app.css  Design token (ukuran dasar 18px, warna kontras tinggi)
```

### Design system

Nama **Hermes** diambil dari dewa pengantar pesan dalam mitologi Yunani. Logonya sayap bergaris kecepatan
dengan titik emas "pesan masuk". Tagline: *Kasir cepat, kabar sampai.*

- **Token warna bermakna** di `resources/css/app.css` (`primary`, `surface`, `ink`, `danger-ink`, ...).
  Jangan memakai kode warna langsung (`emerald-700`, `#047857`) di komponen, supaya mode gelap tetap benar.
- **Tema:** Ikuti HP / Terang / Gelap (`data-theme` di `<html>`). **Ukuran:** Normal / Besar / Sangat Besar
  (`data-size`). Keduanya disimpan per pengguna dan diterapkan dari server, jadi tidak ada kedipan.
- **Responsif:** HP memakai navigasi bawah, tablet memakai daftar 2 kolom, laptop (>= 1024px) memakai menu samping.
  Area aman iPhone (notch & garis home) sudah diperhitungkan.
- **Komponen** (`resources/js/Components/ui`): AppLogo, BigButton, BigInput, PinPad, MoneyDisplay,
  SegmentedControl, ToggleSwitch, BottomSheet, ConfirmDialog, HelpButton, OfflineBanner, FlashMessages,
  EmptyState, PageHeader, BottomNav, SideNav.
- **Katalog komponen:** buka `/dev/komponen` (hanya saat `APP_ENV=local`).
- **Isi tombol Bantuan:** `resources/js/help/content.js`.

### Gudang pakan & toko baju (Fase 9-11)

- **Stok barang curah** tetap `DECIMAL(14,3)` dalam satuan dasar (kg), jadi tepat sampai gram. Karung/sak hanya
  kemasan: "isi 1 karung" (`products.pack_size`) dipakai untuk tampilan "setara 25 karung".
- **Gudang** = outlet dengan `type = warehouse`. Stok, kirim stok, dan hitung stok tetap per `outlet_id`.
  Blok/rak penyimpanan ada di `warehouse_locations`.
- **Terima barang pakai timbangan**: pemasok ditagih sesuai nota (karung x berat nota), stok bertambah sesuai
  timbangan asli; selisihnya tercatat. Ongkos angkut & bongkar muat dibagi ke barang sebanding nilainya dan masuk
  ke modal (HPP, rata-rata bergerak), tapi tidak menambah utang ke pemasok.
- **Kemas ulang & olah** (`app/Modules/Warehouse`): resep (`production_formulas`) berisi bahan per 1 kali olah.
  Bahan kemas = bahan baku dengan `is_packaging`. Modal hasil = (bahan + bahan kemas + biaya olah) / hasil.
- **Batch/lot**: barang dengan `track_batch` dicatat per batch (`stock_batches`) oleh `BatchService` yang dipanggil
  dari `StockService`. Barang keluar mengambil batch kedaluwarsa terdekat dulu (FEFO), lalu yang paling lama (FIFO).
- **Varian ukuran x warna**: setiap kombinasi = barang sendiri (`parent_id` ke model) dengan stok, SKU, barcode,
  dan harga sendiri. Model (`has_variants`) tidak punya stok.
- **Promo**: rumusnya ada di dua tempat yang harus selalu sama, `PriceResolver.php` (server) dan
  `resources/js/pos/lib/pricing.js` (kasir offline).
- **Tukar barang** = pengembalian barang lama + penjualan barang baru, supaya stok, uang laci, dan laporan tetap benar.

### Aturan penting

- Setiap model data bisnis wajib memakai trait `BelongsToTenant`. Tanpa tenant aktif, query tidak
  mengembalikan data apa pun (lebih aman daripada bocor ke usaha lain).
- `User` tidak memakai trait itu (dibutuhkan saat login). Untuk data karyawan, pakai `User::sameTenant()`.
- Uang disimpan sebagai integer rupiah. Persen disimpan sebagai basis point (`1100` = 11%).
- Semua teks yang tampil ke pengguna memakai bahasa Indonesia sehari-hari. Pesan error menjelaskan
  apa yang terjadi dan apa yang harus dilakukan.
