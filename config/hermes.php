<?php

return [
    // Lama masa coba gratis untuk usaha baru (hari).
    'trial_days' => (int) env('HERMES_TRIAL_DAYS', 14),

    // No WhatsApp admin untuk tombol "Hubungi Admin" (format 628xxx).
    'admin_whatsapp' => env('HERMES_ADMIN_WHATSAPP', '6281234567890'),

    // Tempat menyimpan foto barang/logo. "public" = server sendiri; ganti ke "s3" untuk penyimpanan awan.
    'media_disk' => env('HERMES_MEDIA_DISK', 'public'),

    // WhatsApp cadangan bila belum ada akun di database (nomor pusat platform / usaha).
    // driver: log (hanya dicatat, untuk uji coba) | fonnte | wablas | cloud_api
    'whatsapp' => [
        'driver' => env('HERMES_WA_DRIVER', 'log'),
        'credentials' => [
            'token' => env('HERMES_WA_TOKEN'),
            'domain' => env('HERMES_WA_DOMAIN'),
            'phone_number_id' => env('HERMES_WA_PHONE_NUMBER_ID'),
        ],
    ],

    // Batas salah PIN / kata sandi sebelum harus menunggu.
    'max_login_attempts' => 5,
    'login_lockout_seconds' => 60,
    'grace_days' => (int) env('HERMES_GRACE_DAYS', 3),

    'default_plan' => 'bisnis',

    'plans' => [
        'standar' => [
            'label' => 'Standar',
            'price' => 99000,
            'tagline' => 'Untuk warung dan toko dengan satu tempat jualan.',
            'outlets' => 1,
            'staff' => 3,
            'whatsapp' => 100,
            'highlights' => ['1 outlet', '3 karyawan', '100 pesan WhatsApp per bulan', 'Kasir offline, stok, laporan, kasbon'],
        ],
        'pro' => [
            'label' => 'Pro',
            'price' => 199000,
            'tagline' => 'Untuk kafe, rumah makan, dan usaha yang mulai ramai.',
            'outlets' => 3,
            'staff' => 15,
            'whatsapp' => 500,
            'highlights' => ['3 outlet', '15 karyawan', '500 pesan WhatsApp per bulan', 'Pesan lewat QR di meja + bayar QRIS', 'Layar dapur, komisi, member'],
        ],
        'bisnis' => [
            'label' => 'Bisnis',
            'price' => 349000,
            'tagline' => 'Untuk usaha banyak cabang, gudang, dan produksi.',
            'outlets' => null,
            'staff' => null,
            'whatsapp' => 2000,
            'highlights' => ['Outlet & karyawan tanpa batas', '2.000 pesan WhatsApp per bulan', 'Nomor WhatsApp sendiri', 'Gudang, olah, kemas ulang, batch'],
        ],
    ],

    'plan_modules' => [
        'qr_order' => 'pro',
        'kitchen_display' => 'pro',
        'staff_commission' => 'pro',
        'membership' => 'pro',
        'consignment' => 'pro',
        'multi_warehouse' => 'bisnis',
        'weighed_receiving' => 'bisnis',
        'landed_cost' => 'bisnis',
        'repack' => 'bisnis',
        'production' => 'bisnis',
        'batch_lot' => 'bisnis',
    ],

    'plan_features' => [
        'own_whatsapp' => 'bisnis',
    ],

    'durations' => [
        1 => ['discount' => 0, 'label' => '1 bulan'],
        3 => ['discount' => 500, 'label' => '3 bulan (hemat 5%)'],
        6 => ['discount' => 1000, 'label' => '6 bulan (hemat 10%)'],
        12 => ['discount' => 1667, 'label' => '12 bulan (bayar 10 bulan)'],
    ],

    'payment' => [
        'driver' => env('HERMES_PAYMENT_DRIVER'),
        'expiry_minutes' => (int) env('HERMES_PAYMENT_EXPIRY_MINUTES', 60),
        'midtrans' => [
            'server_key' => env('MIDTRANS_SERVER_KEY'),
            'client_key' => env('MIDTRANS_CLIENT_KEY'),
            'is_production' => (bool) env('MIDTRANS_IS_PRODUCTION', false),
        ],
        'fees' => [
            'qris' => ['label' => 'QRIS (semua e-wallet & m-banking)', 'percent_bp' => 70, 'flat' => 0, 'channels' => ['other_qris']],
            'va' => ['label' => 'Transfer Virtual Account (BCA, BNI, BRI, Mandiri, Permata)', 'percent_bp' => 0, 'flat' => 4000, 'channels' => ['bca_va', 'bni_va', 'bri_va', 'echannel', 'permata_va', 'other_va']],
        ],
        'fee_vat_bp' => 1100,
    ],

    'backup' => [
        'path' => env('HERMES_BACKUP_PATH', storage_path('app/backups')),
        'keep_days' => (int) env('HERMES_BACKUP_KEEP_DAYS', 14),
        'dump_binary' => env('HERMES_DUMP_BINARY'),
    ],
];
