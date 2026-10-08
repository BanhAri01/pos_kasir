<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;

/**
 * Daftar modul (fitur). Nama & penjelasan memakai bahasa sehari-hari karena
 * langsung tampil di halaman "Atur Fitur".
 */
class ModuleSeeder extends Seeder
{
    public const MODULES = [
        // ---- Core: selalu menyala ----
        ['code' => 'pos', 'name' => 'Kasir', 'group' => 'core', 'icon' => 'calculator',
            'description' => 'Melayani pembeli, hitung kembalian, dan cetak struk.'],
        ['code' => 'products', 'name' => 'Barang & Layanan', 'group' => 'core', 'icon' => 'package',
            'description' => 'Daftar barang atau layanan yang dijual beserta harganya.'],
        ['code' => 'inventory', 'name' => 'Stok', 'group' => 'core', 'icon' => 'boxes',
            'description' => 'Catat stok masuk, stok keluar, dan hitung stok.'],
        ['code' => 'customers', 'name' => 'Pelanggan', 'group' => 'core', 'icon' => 'users',
            'description' => 'Simpan nama dan no HP pelanggan beserta riwayat belanjanya.'],
        ['code' => 'shifts', 'name' => 'Buka & Tutup Kasir', 'group' => 'core', 'icon' => 'clock',
            'description' => 'Hitung uang di laci saat mulai dan selesai jualan.'],
        ['code' => 'expenses', 'name' => 'Pengeluaran', 'group' => 'core', 'icon' => 'wallet',
            'description' => 'Catat uang keluar: belanja bahan, listrik, gaji.'],
        ['code' => 'reports', 'name' => 'Laporan', 'group' => 'core', 'icon' => 'bar-chart',
            'description' => 'Lihat penjualan, untung, dan barang paling laku.'],

        // ---- Modul bersama: bisa dinyalakan / dimatikan ----
        ['code' => 'variants_modifiers', 'name' => 'Pilihan Ukuran & Tambahan', 'icon' => 'sliders',
            'description' => 'Contoh: ukuran besar/kecil, gula sedikit, tambah topping.'],
        ['code' => 'recipe', 'name' => 'Resep & Modal per Menu', 'icon' => 'chef-hat',
            'description' => 'Stok bahan berkurang otomatis setiap menu terjual, modal per menu terhitung.'],
        ['code' => 'tables', 'name' => 'Meja & Bayar Belakangan', 'icon' => 'armchair',
            'description' => 'Catat pesanan per meja, bayar saat pelanggan selesai makan.'],
        ['code' => 'kitchen_display', 'name' => 'Layar Dapur', 'icon' => 'monitor',
            'description' => 'Pesanan langsung muncul di HP/tablet dapur atau barista.'],
        ['code' => 'queue', 'name' => 'Nomor Antrean', 'icon' => 'list-ordered',
            'description' => 'Beri nomor antrean dan panggil pelanggan sesuai urutan.'],
        ['code' => 'booking', 'name' => 'Janji Temu', 'icon' => 'calendar',
            'description' => 'Pelanggan pesan jadwal dulu. Pengingat dikirim lewat WhatsApp.'],
        ['code' => 'staff_commission', 'name' => 'Komisi Karyawan', 'icon' => 'hand-coins',
            'description' => 'Hitung bagian karyawan dari setiap layanan secara otomatis.'],
        ['code' => 'membership', 'name' => 'Member & Paket', 'icon' => 'id-card',
            'description' => 'Paket langganan pelanggan dengan masa aktif dan absen masuk.'],
        ['code' => 'order_status', 'name' => 'Status Pesanan', 'icon' => 'list-checks',
            'description' => 'Lacak pesanan: diterima, dikerjakan, siap diambil.'],
        ['code' => 'kasbon', 'name' => 'Kasbon / Utang Pelanggan', 'icon' => 'notebook',
            'description' => 'Catat utang pelanggan, cicilan, dan kirim pengingat WhatsApp.'],
        ['code' => 'barcode', 'name' => 'Scan Barcode', 'icon' => 'scan-barcode',
            'description' => 'Cari barang dengan scan barcode lewat kamera HP atau alat scanner.'],
        ['code' => 'multi_unit', 'name' => 'Banyak Satuan', 'icon' => 'ruler',
            'description' => 'Jual dalam beberapa satuan. Contoh: per dus dan per bungkus.'],
        ['code' => 'price_levels', 'name' => 'Harga Grosir & Harga Khusus', 'icon' => 'tags',
            'description' => 'Harga berbeda untuk grosir, tukang, atau pelanggan tertentu.'],
        ['code' => 'supplier_purchase', 'name' => 'Belanja ke Pemasok', 'icon' => 'truck',
            'description' => 'Catat belanja barang dari pemasok dan utang ke pemasok.'],
        ['code' => 'credit_sales', 'name' => 'Jual Tempo & Uang Muka', 'icon' => 'file-clock',
            'description' => 'Pembeli bayar belakangan atau bayar uang muka dulu, ada tanggal jatuh tempo.'],
        ['code' => 'delivery', 'name' => 'Antar Barang', 'icon' => 'bike',
            'description' => 'Catat pengiriman atau antar-jemput, cetak surat jalan.'],
        ['code' => 'whatsapp_notification', 'name' => 'Kirim Pesan WhatsApp', 'icon' => 'message-circle',
            'description' => 'Kirim struk, pengingat utang, dan kabar pesanan lewat WhatsApp.'],
        ['code' => 'service_history', 'name' => 'Catatan Layanan Pelanggan', 'icon' => 'clipboard',
            'description' => 'Simpan catatan seperti warna cat rambut atau perawatan terakhir.'],

        // ---- Gudang & produksi (Fase 9-10) ----
        ['code' => 'base_unit_stock', 'name' => 'Stok dalam Kg', 'icon' => 'scale',
            'description' => 'Stok barang curah dihitung dalam kg. Karung/sak hanya kemasan, tampil sebagai "setara karung".'],
        ['code' => 'multi_warehouse', 'name' => 'Banyak Gudang', 'icon' => 'warehouse',
            'description' => 'Punya beberapa gudang, blok penyimpanan, dan kirim stok antar gudang.'],
        ['code' => 'weighed_receiving', 'name' => 'Terima Barang Pakai Timbangan', 'icon' => 'scale',
            'description' => 'Catat jumlah karung dan berat timbangan asli. Selisih dengan nota pemasok tercatat otomatis.',
            'depends_on' => ['supplier_purchase']],
        ['code' => 'landed_cost', 'name' => 'Modal Lengkap (HPP)', 'icon' => 'calculator',
            'description' => 'Ongkos angkut dan bongkar muat ikut dihitung ke modal per kg.',
            'depends_on' => ['supplier_purchase']],
        ['code' => 'shrinkage', 'name' => 'Catat Susut', 'icon' => 'trending-down',
            'description' => 'Catat barang susut (kadar air, hama, tumpah) dan lihat laporan selisih hitung stok.'],
        ['code' => 'packaging_materials', 'name' => 'Bahan Kemas', 'icon' => 'package-open',
            'description' => 'Stok karung kosong, benang jahit, dan label berkurang otomatis saat mengemas.'],
        ['code' => 'repack', 'name' => 'Kemas Ulang', 'icon' => 'package',
            'description' => 'Ubah barang curah jadi barang kemasan (25 kg jagung + 1 karung = 1 Jagung Karung 25 kg), atau sebaliknya.'],
        ['code' => 'production', 'name' => 'Olah / Produksi', 'icon' => 'factory',
            'description' => 'Resep produksi (giling, racik pakan), catat bahan terpakai, hasil, susut, dan biaya olah.'],
        ['code' => 'batch_lot', 'name' => 'Nomor Batch & Kedaluwarsa', 'icon' => 'calendar-clock',
            'description' => 'Catat nomor batch, tanggal kedaluwarsa, dan kadar air. Barang lama keluar duluan.'],

        // ---- Toko baju (Fase 11) ----
        ['code' => 'variant_matrix', 'name' => 'Varian Ukuran & Warna', 'icon' => 'grid',
            'description' => 'Satu model punya banyak ukuran dan warna, masing-masing dengan stok dan barcode sendiri.'],
        ['code' => 'barcode_label', 'name' => 'Cetak Label Barcode', 'icon' => 'printer',
            'description' => 'Cetak label barcode untuk ditempel di barang (printer label atau kertas stiker A4).'],
        ['code' => 'returns_exchange', 'name' => 'Retur & Tukar Barang', 'icon' => 'repeat',
            'description' => 'Pembeli menukar ukuran atau mengembalikan barang, stok menyesuaikan otomatis.'],
        ['code' => 'promotions', 'name' => 'Promo & Diskon Musiman', 'icon' => 'tags',
            'description' => 'Diskon persen atau rupiah dengan tanggal mulai dan selesai. Kasir otomatis memakai harga promo.'],
        ['code' => 'consignment', 'name' => 'Barang Titipan (Konsinyasi)', 'icon' => 'hand-coins',
            'description' => 'Jual barang titipan orang lain dengan bagi hasil. Laporan berapa yang harus dibayar ke pemiliknya.',
            'depends_on' => ['supplier_purchase']],

        ['code' => 'loyalty', 'name' => 'Poin Pelanggan', 'icon' => 'gift',
            'description' => 'Pelanggan dapat poin setiap belanja, lalu tukar poin dengan potongan harga.'],
        ['code' => 'online_order', 'name' => 'Toko Online (Pesan Antar & Ambil)', 'icon' => 'shopping-bag',
            'description' => 'Bagikan link toko di WhatsApp / Instagram. Pelanggan pesan untuk diambil atau diantar.'],

        ['code' => 'qr_order', 'name' => 'Pesan Lewat QR di Meja', 'icon' => 'qr-code',
            'description' => 'Pelanggan scan QR di meja lalu pesan sendiri dari HP-nya.',
            'depends_on' => ['tables']],
    ];

    public function run(): void
    {
        foreach (self::MODULES as $index => $data) {
            $group = $data['group'] ?? 'shared';

            Module::updateOrCreate(['code' => $data['code']], [
                'name' => $data['name'],
                'description' => $data['description'],
                'group' => $group,
                'is_core' => $group === 'core',
                'icon' => $data['icon'],
                'depends_on' => $data['depends_on'] ?? null,
                'sort_order' => $index,
                'is_active' => $data['available'] ?? true,
            ]);
        }
    }
}
