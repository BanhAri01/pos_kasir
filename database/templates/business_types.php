<?php

/*
|--------------------------------------------------------------------------
| Template data contoh per jenis usaha
|--------------------------------------------------------------------------
| Diisi otomatis saat usaha baru mendaftar, supaya pemilik bisa langsung mencoba.
| Barang contoh ditandai is_sample = true dan bisa dihapus sekaligus.
|
| Format barang: [nama, kategori, harga jual, modal, satuan, stok awal, tipe, durasi menit, cara harga,
|                isi 1 kemasan, nama kemasan]
|   tipe: goods (barang) | service (layanan) | ingredient (bahan baku) | packaging (bahan kemas)
|   stok awal null = tidak dilacak stoknya
|   isi 1 kemasan: untuk tampilan "setara karung" pada barang curah (contoh: 50 = 1 karung 50 kg)
|
| price_levels (opsional): tipe harga selain harga eceran, dibuat otomatis.
|
| Jenis usaha baru cukup ditambah di sini + di BusinessTypeSeeder, tanpa menulis ulang kode.
*/

return [
    'toko_kelontong' => [
        'categories' => ['Sembako', 'Minuman', 'Jajanan', 'Rokok', 'Kebutuhan Rumah'],
        'products' => [
            ['Beras Premium 5 kg', 'Sembako', 72000, 65000, 'Pcs', 20, 'goods'],
            ['Minyak Goreng 1 L', 'Sembako', 18000, 16000, 'Botol', 24, 'goods'],
            ['Gula Pasir 1 kg', 'Sembako', 17000, 15500, 'Pcs', 30, 'goods'],
            ['Telur Ayam', 'Sembako', 2500, 2100, 'Pcs', 120, 'goods'],
            ['Mi Instan Goreng', 'Sembako', 3500, 2900, 'Bungkus', 80, 'goods'],
            ['Air Mineral 600 ml', 'Minuman', 4000, 2800, 'Botol', 48, 'goods'],
            ['Teh Kotak', 'Minuman', 5000, 3800, 'Pcs', 36, 'goods'],
            ['Kopi Sachet', 'Minuman', 2000, 1400, 'Bungkus', 60, 'goods'],
            ['Kerupuk', 'Jajanan', 1000, 600, 'Bungkus', 50, 'goods'],
            ['Wafer Cokelat', 'Jajanan', 2000, 1500, 'Pcs', 40, 'goods'],
            ['Sabun Mandi', 'Kebutuhan Rumah', 4500, 3600, 'Pcs', 24, 'goods'],
            ['Deterjen 800 g', 'Kebutuhan Rumah', 22000, 19000, 'Pcs', 12, 'goods'],
        ],
    ],

    'toko_bangunan' => [
        'categories' => ['Semen & Pasir', 'Besi', 'Cat', 'Pipa & Air', 'Alat'],
        'products' => [
            ['Semen 50 kg', 'Semen & Pasir', 68000, 62000, 'Sak', 40, 'goods'],
            ['Pasir Cor', 'Semen & Pasir', 350000, 300000, 'Kubik', 10, 'goods'],
            ['Besi Beton 10 mm', 'Besi', 85000, 76000, 'Batang', 50, 'goods'],
            ['Besi Beton 8 mm', 'Besi', 55000, 49000, 'Batang', 60, 'goods'],
            ['Paku 5 cm', 'Besi', 22000, 18000, 'Kg', 25, 'goods'],
            ['Cat Tembok 5 kg', 'Cat', 95000, 82000, 'Pcs', 15, 'goods'],
            ['Pipa PVC 3/4"', 'Pipa & Air', 32000, 27000, 'Batang', 30, 'goods'],
            ['Kawat Bendrat', 'Besi', 25000, 21000, 'Roll', 20, 'goods'],
            ['Kabel Listrik 2x1,5', 'Alat', 6500, 5200, 'Meter', 200, 'goods'],
            ['Cangkul', 'Alat', 75000, 60000, 'Pcs', 8, 'goods'],
        ],
    ],

    'coffee_shop' => [
        'categories' => ['Kopi', 'Non Kopi', 'Makanan', 'Bahan Baku'],
        'products' => [
            ['Kopi Susu Gula Aren', 'Kopi', 18000, 7000, 'Gelas', null, 'goods'],
            ['Americano', 'Kopi', 15000, 5000, 'Gelas', null, 'goods'],
            ['Cappuccino', 'Kopi', 20000, 7500, 'Gelas', null, 'goods'],
            ['Kopi Tubruk', 'Kopi', 8000, 2500, 'Gelas', null, 'goods'],
            ['Matcha Latte', 'Non Kopi', 22000, 8500, 'Gelas', null, 'goods'],
            ['Cokelat Panas', 'Non Kopi', 18000, 7000, 'Gelas', null, 'goods'],
            ['Es Teh Manis', 'Non Kopi', 6000, 1500, 'Gelas', null, 'goods'],
            ['Roti Bakar Cokelat', 'Makanan', 15000, 6000, 'Porsi', null, 'goods'],
            ['Kentang Goreng', 'Makanan', 15000, 6500, 'Porsi', null, 'goods'],
            ['Biji Kopi Arabika', 'Bahan Baku', 0, 250, 'Gram', 2000, 'ingredient'],
            ['Susu Segar', 'Bahan Baku', 0, 20, 'Liter', 20, 'ingredient'],
            ['Gula Aren Cair', 'Bahan Baku', 0, 60, 'Liter', 5, 'ingredient'],
        ],
    ],

    'warung_makan' => [
        'categories' => ['Makanan', 'Lauk', 'Minuman'],
        'products' => [
            ['Nasi Putih', 'Makanan', 5000, 2000, 'Porsi', null, 'goods'],
            ['Nasi Goreng', 'Makanan', 15000, 7000, 'Porsi', null, 'goods'],
            ['Mi Goreng', 'Makanan', 13000, 6000, 'Porsi', null, 'goods'],
            ['Soto Ayam', 'Makanan', 15000, 7000, 'Porsi', null, 'goods'],
            ['Ayam Goreng', 'Lauk', 12000, 7000, 'Porsi', null, 'goods'],
            ['Telur Dadar', 'Lauk', 5000, 2500, 'Porsi', null, 'goods'],
            ['Tempe Goreng', 'Lauk', 2000, 700, 'Pcs', null, 'goods'],
            ['Sayur Lodeh', 'Lauk', 6000, 2500, 'Porsi', null, 'goods'],
            ['Es Teh Manis', 'Minuman', 5000, 1200, 'Gelas', null, 'goods'],
            ['Es Jeruk', 'Minuman', 7000, 2500, 'Gelas', null, 'goods'],
            ['Kerupuk', 'Lauk', 1000, 500, 'Pcs', 100, 'goods'],
        ],
    ],

    'barbershop' => [
        'categories' => ['Potong Rambut', 'Perawatan', 'Produk'],
        'products' => [
            ['Potong Rambut Dewasa', 'Potong Rambut', 25000, 0, 'Kali', null, 'service', 30],
            ['Potong Rambut Anak', 'Potong Rambut', 20000, 0, 'Kali', null, 'service', 20],
            ['Cukur Jenggot', 'Perawatan', 10000, 0, 'Kali', null, 'service', 15],
            ['Creambath', 'Perawatan', 35000, 8000, 'Kali', null, 'service', 45],
            ['Semir Rambut', 'Perawatan', 50000, 15000, 'Kali', null, 'service', 60],
            ['Pomade', 'Produk', 45000, 30000, 'Pcs', 12, 'goods'],
            ['Sampo Botol', 'Produk', 25000, 17000, 'Botol', 10, 'goods'],
        ],
    ],

    'salon' => [
        'categories' => ['Rambut', 'Wajah', 'Kuku', 'Produk'],
        'products' => [
            ['Potong Rambut Wanita', 'Rambut', 40000, 0, 'Kali', null, 'service', 45],
            ['Cuci & Blow', 'Rambut', 30000, 3000, 'Kali', null, 'service', 30],
            ['Pewarnaan Rambut', 'Rambut', 150000, 45000, 'Kali', null, 'service', 120],
            ['Smoothing', 'Rambut', 250000, 70000, 'Kali', null, 'service', 180],
            ['Facial', 'Wajah', 75000, 15000, 'Kali', null, 'service', 60],
            ['Manicure', 'Kuku', 50000, 8000, 'Kali', null, 'service', 45],
            ['Pedicure', 'Kuku', 60000, 8000, 'Kali', null, 'service', 50],
            ['Vitamin Rambut', 'Produk', 35000, 22000, 'Botol', 15, 'goods'],
        ],
    ],

    'gym' => [
        'categories' => ['Keanggotaan', 'Personal Trainer', 'Minuman & Suplemen'],
        'products' => [
            ['Masuk Harian', 'Keanggotaan', 20000, 0, 'Kali', null, 'service'],
            ['Personal Trainer (1 sesi)', 'Personal Trainer', 100000, 0, 'Kali', null, 'service', 60],
            ['Air Mineral', 'Minuman & Suplemen', 5000, 3000, 'Botol', 48, 'goods'],
            ['Minuman Isotonik', 'Minuman & Suplemen', 8000, 5500, 'Botol', 36, 'goods'],
            ['Whey Protein (sachet)', 'Minuman & Suplemen', 25000, 16000, 'Bungkus', 20, 'goods'],
            ['Sewa Handuk', 'Keanggotaan', 5000, 0, 'Kali', null, 'service'],
        ],
    ],

    'laundry' => [
        'categories' => ['Kiloan', 'Satuan', 'Tambahan'],
        'products' => [
            ['Cuci Setrika (per kg)', 'Kiloan', 7000, 2500, 'Kg', null, 'service', 2880, 'per_weight'],
            ['Cuci Kering (per kg)', 'Kiloan', 5000, 1800, 'Kg', null, 'service', 1440, 'per_weight'],
            ['Setrika Saja (per kg)', 'Kiloan', 4000, 1000, 'Kg', null, 'service', 1440, 'per_weight'],
            ['Bed Cover', 'Satuan', 30000, 8000, 'Pcs', null, 'service', 4320],
            ['Jas', 'Satuan', 25000, 6000, 'Pcs', null, 'service', 2880],
            ['Sepatu', 'Satuan', 35000, 8000, 'Pasang', null, 'service', 4320],
            ['Ekspres (selesai 1 hari)', 'Tambahan', 10000, 0, 'Kali', null, 'service'],
        ],
    ],

    'gudang_pakan' => [
        'categories' => ['Curah', 'Karungan', 'Bahan Kemas'],
        'price_levels' => ['Peternak', 'Agen'],
        'products' => [
            ['Jagung Pipil', 'Curah', 6500, 5200, 'Kg', 2500, 'goods', null, 'per_weight', 50, 'karung'],
            ['Dedak Padi', 'Curah', 4000, 3000, 'Kg', 1500, 'goods', null, 'per_weight', 50, 'karung'],
            ['Konsentrat Layer', 'Curah', 9500, 8200, 'Kg', 500, 'goods', null, 'per_weight', 50, 'sak'],
            ['Jagung Karung 25 kg', 'Karungan', 165000, 135000, 'Karung', 20, 'goods'],
            ['Pakan Layer 50 kg', 'Karungan', 430000, 370000, 'Karung', 10, 'goods'],
            ['Jagung Giling', 'Curah', 7000, 5600, 'Kg', 0, 'goods', null, 'per_weight', 50, 'karung'],
            ['Pakan Racik Layer', 'Curah', 8500, 6800, 'Kg', 0, 'goods', null, 'per_weight', 50, 'karung'],
            ['Karung Kosong 25 kg', 'Bahan Kemas', 0, 1500, 'Pcs', 200, 'packaging'],
            ['Karung Kosong 50 kg', 'Bahan Kemas', 0, 2200, 'Pcs', 100, 'packaging'],
            ['Benang Jahit Karung', 'Bahan Kemas', 0, 15000, 'Roll', 10, 'packaging'],
        ],
        // Barang yang dicatat nomor batch & kedaluwarsanya (modul batch_lot)
        // Karung yang dikemas sendiri, beratnya selalu sama (boleh dihitung per karung saat hitung stok)
        'fixed_packs' => ['Jagung Giling', 'Pakan Racik Layer'],
        'track_batch' => ['Konsentrat Layer', 'Pakan Layer 50 kg', 'Pakan Racik Layer'],
        // Resep kemas ulang & olah: [jenis, nama, hasil, jumlah hasil per 1 kali, [[bahan, jumlah], ...]]
        'formulas' => [
            ['repack', 'Kemas Jagung Karung 25 kg', 'Jagung Karung 25 kg', 1, [['Jagung Pipil', 25], ['Karung Kosong 25 kg', 1], ['Benang Jahit Karung', 0.02]]],
            ['production', 'Giling Jagung', 'Jagung Giling', 100, [['Jagung Pipil', 102]]],
            ['production', 'Racik Pakan Layer', 'Pakan Racik Layer', 100, [['Jagung Pipil', 50], ['Konsentrat Layer', 35], ['Dedak Padi', 15]]],
        ],
    ],

    'toko_baju' => [
        'categories' => ['Kaos', 'Kemeja', 'Celana', 'Aksesoris'],
        'price_levels' => ['Reseller'],
        'products' => [
            ['Topi Baseball', 'Aksesoris', 45000, 25000, 'Pcs', 15, 'goods'],
            ['Kaos Kaki', 'Aksesoris', 15000, 7000, 'Pasang', 40, 'goods'],
        ],
        // Model dengan varian ukuran x warna: [nama, kategori, harga, modal, pilihan, stok awal per varian]
        'variant_models' => [
            ['Kaos Polos Cotton', 'Kaos', 65000, 38000, ['Ukuran' => ['S', 'M', 'L', 'XL'], 'Warna' => ['Hitam', 'Putih', 'Navy']], 5],
            ['Kemeja Flanel', 'Kemeja', 145000, 90000, ['Ukuran' => ['M', 'L', 'XL'], 'Warna' => ['Merah', 'Hijau']], 3],
            ['Celana Chino', 'Celana', 175000, 110000, ['Ukuran' => ['28', '30', '32', '34'], 'Warna' => ['Krem', 'Hitam']], 3],
        ],
    ],

    'lainnya' => [
        'categories' => ['Umum'],
        'products' => [
            ['Barang Contoh', 'Umum', 10000, 7000, 'Pcs', 10, 'goods'],
            ['Layanan Contoh', 'Umum', 25000, 0, 'Kali', null, 'service', 30],
        ],
    ],
];
