# LAPORAN PERUBAHAN & PEMBARUAN SISTEM SAAS POS KASIR
**Sistem Aplikasi Kasir & Manajemen Gudang Universal Multi-Tenant**  
*Dokumentasi Resmi Pembaruan Fitur, Logika Bisnis, Audit Keamanan, Pengujian & Panduan Deployment*  
*Tanggal: 13 Oktober 2026*

---

## 1. Ringkasan Eksekutif
Laporan ini merangkum seluruh pembaruan, optimalisasi sistem, perbaikan bug, dan penguatan keamanan yang telah diimplementasikan pada sistem **SaaS POS Kasir**.

Pembaruan utama berfokus pada:
1. **Sistem Pesan Otomatis Tagihan Kasbon via WhatsApp (Fonnte API & wa.me)** dengan jendela operasional ketat **07:00 – 19:00** dan integrasi portal mandiri pelanggan.
2. **Modul Gudang Universal & Titip Uang (Customer Deposit)** yang fleksibel untuk segala jenis bidang usaha (Grosir, Retail, F&B, Distribusi Bahan Baku), lengkap dengan audit trail mutasi saldo.
3. **Otomasi Kelebihan Bayar & Pelunasan Piutang via Deposit**: Kelebihan nominal bayar otomatis masuk deposit, serta kasir/gudang dapat melunasi nota langsung dari saldo titip uang.
4. **Restorasi Landing Page Modern Universal**: Menghilangkan modul spesifik peternakan yang telah dipindahkan ke aplikasi HEFAM, menyajikan value proposition komprehensif, simulasi bisnis, serta optimasi SEO (`sitemap.xml` & `robots.txt`).
5. **Audit Keamanan Ketat Multi-Tenancy**: Mencegah IDOR pada rincian deposit, pembatasan hak akses (*permission*), rate limiting, dan proteksi transaksi atomik (*race condition*).

Seluruh **428 skenario pengujian otomatis (2.403 assertions)** telah dijalankan dan dinyatakan **LULUS 100% (PASS)** tanpa kendala.

---

## 2. Rincian Pembaruan Fitur & Logika Bisnis

### A. Pengingat Otomatis Tagihan Kasbon WhatsApp (Fonnte API) & Jam Kirim 07:00 – 19:00
* **Latar Belakang**: Pelanggan/tengkulak yang membeli secara tempo (kasbon) memerlukan pengingat faktur yang transparan dan tepat waktu tanpa membuat risih pelanggan di luar jam kerja.
* **Fitur & Logika Bisnis**:
  1. **Jendela Jam Operasional (07:00 – 19:00)**: Pengiriman otomatis melalui command penjadwal hanya berjalan antara pukul 07:00 pagi hingga 19:00 malam (WITA/WIB sesuai zona waktu tenant). Di luar rentang waktu ini, sistem otomatis menahan pengiriman untuk menjaga etika bisnis.
  2. **Bypass Pengujian (`ignoreHours` / `--force`)**: Tersedia opsi pengujian dari terminal (`php artisan reminders:send`) untuk memverifikasi integrasi gateway tanpa terhalang jam operasional.
  3. **Penghitungan Saldo Bersih Transparan**: Sistem otomatis menghitung total utang nota, memotongnya dengan saldo titip uang/deposit pelanggan, lalu menampilkan **Sisa Kewajiban Bersih**.
  4. **Pemberian Tautan Portal Resmi**: Pesan WhatsApp memuat tautan aman tanpa login (`https://domain/tagihan/{customer}/{token}`) agar pelanggan dapat memeriksa rincian tiap nota kasbon dan mengunggah bukti transfer mandiri.
  5. **Tombol Cepat wa.me di Back-Office**: Di halaman detail piutang (`ReceivableShow.vue`) dan modul titip uang gudang (`Deposits.vue`), pemilik usaha dapat mengklik tombol WhatsApp untuk membuka pesan tagihan siap kirim via WhatsApp Web/Desktop.
* **File Terkait**:
  - `app/Modules/Operations/Services/KasbonBillService.php`
  - `app/Console/Commands/SendReminders.php`
  - `resources/js/Pages/Operations/ReceivableShow.vue`

---

### B. Modul Gudang Universal & Saldo Titip Uang (Deposit) Pelanggan
* **Latar Belakang**: Modul gudang disesuaikan agar tidak terpaku pada distribusi pakan/ternak saja, melainkan universal untuk distributor sembako, toko grosir, manufaktur bahan, maupun usaha retail. Tengkulak/pelanggan setia sering menitipkan uang di muka (*prepayment/deposit*) atau membayar dengan nominal bulat lebih besar dari nilai nota.
* **Fitur & Logika Bisnis**:
  1. **Tabel Database Mutasi Deposit**: Tabel `warehouse_deposits` mencatat setiap perubahan saldo secara mendetail: tipe transaksi (`deposit`, `withdrawal`, `used_for_bill`, `overpayment`), nominal debit/kredit, saldo sebelum dan sesudah (`balance_before` & `balance_after`), nomor referensi, serta identitas petugas pencatat.
  2. **Antarmuka Manajemen Titip Uang (`/gudang/titip-uang`)**:
     - Metric ringkasan: Total Saldo Titip Uang Aktif, Pelanggan Pemilik Saldo, Total Pemasukan Deposit Bulan Ini, dan Total Terpakai untuk Nota.
     - Pencatatan Setor Titip Uang (Deposit Tunai/Transfer).
     - Pencatatan Tarik Saldo (Withdrawal) dengan validasi batas saldo mencukupi.
     - Riwayat mutasi lengkap (*audit trail*) per pelanggan.
     - Tombol cepat kirim rincian saldo & tagihan via WhatsApp.
  3. **Otomatisasi Kelebihan Bayar (*Overpayment*)**: Saat kasir atau admin mencatat pelunasan piutang/kasbon di mana pembayaran melebihi sisa utang nota, kelebihan tersebut secara otomatis dikonversi menjadi saldo deposit pelanggan.
  4. **Pelunasan Nota Menggunakan Saldo Deposit**: Petugas kasir dapat memotong saldo titip uang untuk melunasi nota aktif tanpa pelanggan harus mengeluarkan uang fisik/transfer baru.
* **File Terkait**:
  - `app/Modules/Warehouse/Database/Migrations/2026_10_14_000001_create_warehouse_deposits_table.php`
  - `app/Modules/Warehouse/Models/CustomerDeposit.php`
  - `app/Modules/Warehouse/Http/Controllers/WarehouseDepositController.php`
  - `app/Modules/Customer/Models/Customer.php` (`addDeposit`, `useDeposit`, `deposit_balance`)
  - `app/Modules/Operations/Services/ReceivableService.php`
  - `resources/js/Pages/Warehouse/Deposits.vue`
  - `resources/js/Pages/Warehouse/Dashboard.vue`
  - `resources/js/composables/useNavigation.js`

---

### C. Portal Mandiri Tagihan & Pembayaran Pelanggan (Customer Bill Portal)
* **Latar Belakang**: Memberikan kenyamanan kepada pelanggan kasbon untuk memantau status utang piutang tanpa perlu menghubungi admin berkali-kali.
* **Fitur & Logika Bisnis**:
  1. **Akses Publik Berbasis Token Kriptografi**: Portal `/tagihan/{customer}/{token}` dapat dibuka pelanggan tanpa login akun, diamankan dengan token kriptografi tenant.
  2. **Penyajian Finansial Komprehensif**:
     - Total Tagihan Kotor (semua nota aktif).
     - Tagihan yang Sudah Jatuh Tempo vs Belum Jatuh Tempo.
     - Potongan Saldo Titip Uang / Deposit.
     - **Total Bersih yang Harus Ditransfer**.
  3. **Rincian Tiap Nota & Bukti Bayar**: Pelanggan dapat melihat rincian barang per nota dan mengunggah foto bukti transfer bank/QRIS langsung dari browser ponsel mereka.
* **File Terkait**:
  - `app/Modules/Customer/Http/Controllers/PublicBillController.php`
  - `resources/js/Pages/Order/Bill.vue`

---

### D. Restorasi & Optimalisasi Landing Page SaaS POS Kasir
* **Latar Belakang**: Mengembalikan landing page resmi SaaS POS Kasir menjadi platform universal modern dan menghapus widget spesifik harga pasar peternakan (Pinsar Indonesia) yang sudah dialihkan sepenuhnya ke aplikasi peternakan HEFAM.
* **Fitur & Logika Bisnis**:
  1. **Segmen Usaha Universal**: Menampilkan solusi siap pakai untuk Restoran & Kafe (F&B), Minimarket & Toko Retail, Grosir & Distribusi Gudang, serta Usaha Jasa.
  2. **Interaktif ROI Calculator**: Pengunjung dapat mensimulasikan potensi penghematan waktu kasir, pencegahan kebocoran stok, dan penagihan kasbon otomatis.
  3. **Penyempurnaan SEO & Meta**: Menghadirkan `sitemap.xml` dan `robots.txt` yang rapi guna memaksimalkan performa indeks mesin pencari Google.
* **File Terkait**:
  - `resources/js/Pages/Landing.vue`
  - `resources/views/app.blade.php`
  - `public/robots.txt`
  - `public/sitemap.xml`

---

## 3. Audit Keamanan & Mitigasi Kerentanan (Security Audit)

| Area Audit | Skenario Resiko | Mitigasi yang Diterapkan | Status |
| :--- | :--- | :--- | :---: |
| **Isolasi Multi-Tenant (IDOR)** | Percobaan melihat atau memodifikasi saldo titip uang milik usaha (*tenant*) lain melalui ID pelanggan. | Controller memvalidasi `customer->tenant_id === context->tenant_id`. Permintaan lintas tenant langsung ditolak dengan HTTP 404/403. | **TERUJI AMAN** |
| **Otorisasi Hak Akses (RBAC)** | Karyawan kasir biasa tanpa izin stok/keuangan membuka menu gudang titip uang. | Dilindungi middleware `permission:manage_stock` dan validasi hak akses kasir/manajer. | **TERUJI AMAN** |
| **Race Condition pada Saldo Deposit** | Penarikan atau pemotongan saldo deposit dilakukan ganda dalam waktu bersamaan. | Menggunakan transaksi database atomik `DB::transaction()` dan penguncian baris `lockForUpdate()` pada entitas pelanggan. | **TERUJI AMAN** |
| **Pencegahan Saldo Negatif** | Pengurangan saldo deposit melebihi ketersediaan deposit aktif. | Validasi ketat `if ($amount > $customer->deposit_balance)` melempar `ValidationException` dan rollback otomatis. | **TERUJI AMAN** |
| **Validasi Format Nomor WhatsApp** | Nomor telepon pelanggan format lokal `08...` gagal saat dipanggil API gateway atau wa.me. | Normalisasi otomatis format nomor: menghilangkan karakter non-digit dan mengonversi awalan `0` / `08` menjadi `628`. | **TERUJI AMAN** |
| **Brute Force & Spamming** | Akses berulang ke portal publik atau pengiriman pesan tagihan beruntun. | Penerapan middleware `throttle:60,1` pada portal publik dan pembatasan frekuensi pengingat kasbon. | **TERUJI AMAN** |

---

## 4. Hasil Verifikasi Pengujian Otomatis (Test Suite)

Pengujian dijalankan pada suite PHPUnit / Pest:

```text
Tests:    428 passed (2403 assertions)
Duration: 38.05s
Status:   100% PASS
```

### Rincian Pengujian Modul Terkait:
1. **`Tests\Feature\Warehouse\WarehouseDepositTest` (13 skenario - 100% PASS)**:
   - Menampilkan halaman gudang titip uang dengan metric dan daftar pelanggan: **PASS**
   - Mencatat setor titip uang baru dari gudang: **PASS**
   - Menarik saldo titip uang bila saldo mencukupi: **PASS**
   - Gagal menarik saldo titip uang bila melebihi saldo: **PASS**
   - Kelebihan bayar nota otomatis masuk ke saldo deposit pelanggan: **PASS**
   - Memotong utang nota aktif menggunakan saldo titip uang: **PASS**
   - Menghasilkan tautan wa.me dan pesan tagihan dengan potongan saldo deposit: **PASS**
   - Halaman public bill menampilkan `deposit_balance` dan `net_due`: **PASS**
   - Endpoint riwayat mutasi mengembalikan JSON audit trail lengkap: **PASS**
   - Menolak titip uang untuk pelanggan milik usaha lain (*Tenant Isolation*): **PASS**
   - Menolak akses riwayat mutasi pelanggan usaha lain (*IDOR Protection*): **PASS**
   - Menolak kirim WA tagihan untuk pelanggan usaha lain: **PASS**
   - Karyawan tanpa izin stok dilarang mengakses menu titip uang: **PASS**

2. **`Tests\Feature\Operations\FonnteKasbonReminderTest` (7 skenario - 100% PASS)**:
   - Mengirim pesan WhatsApp via Fonnte API saat utang kasbon jatuh tempo: **PASS**
   - Memperhitungkan potongan saldo titip uang / deposit pelanggan: **PASS**
   - Tidak mengirim pesan bila jatuh tempo belum tiba: **PASS**
   - Tidak mengirim pesan bila di luar jam operasional 07:00 – 19:00: **PASS**
   - Bisa mengirim di luar jam operasional bila memakai opsi force/ignoreHours: **PASS**
   - Tidak mengirim pesan bila opsi tagih otomatis dinonaktifkan: **PASS**
   - Menangani kegagalan Fonnte dengan aman (graceful failure): **PASS**

3. **`Tests\Feature\LandingTest` (100% PASS)**:
   - Akses landing page publik universal, respon HTTP 200, dan komponen Inertia Vue ter-render sempurna: **PASS**

4. **`Tests\Feature\Tenancy\TenantIsolationTest` (15 skenario - 100% PASS)**:
   - Isolasi data tenant di level Model, Global Scope, dan HTTP Controller: **PASS**

---

## 5. Panduan Deployment Production (Server & cPanel)

### A. Migrasi Basis Data
Jalankan perintah berikut di lingkungan server production:
```bash
php artisan migrate --force
```
*Tabel yang ditambahkan / dimutasi:*
- `warehouse_deposits`: Menyimpan riwayat setor, tarik, kelebihan bayar, dan pemotongan nota.

### B. Konfigurasi Gateway WhatsApp Fonnte di `.env`
Pastikan kredensial Fonnte aktif pada `.env`:
```env
# Provider WhatsApp Gateway
WA_PROVIDER=fonnte
FONNTE_TOKEN=token_fonnte_anda_disini
FONNTE_URL=https://api.fonnte.com/send

# Zona Waktu Operasional
APP_TIMEZONE=Asia/Makassar
```

### C. Pembersihan Cache & Build Aset Frontend
Lakukan optimasi cache Laravel:
```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
*Catatan: File aset Vite frontend di folder `public/build/` telah dikompilasi siap pakai.*

### D. Konfigurasi Cron Job cPanel
Pastikan cron job cPanel mengeksekusi scheduler Laravel setiap menit:
```bash
* * * * * cd /home/USER/public_html && php artisan schedule:run >> /dev/null 2>&1
```
*Scheduler akan otomatis mengeksekusi:*
- `php artisan reminders:send`: Mengecek nota kasbon yang jatuh tempo pada jendela jam **07:00 – 19:00**.

---

*Laporan ini disusun sebagai dokumentasi teknis resmi pembaruan sistem SaaS POS Kasir.*
