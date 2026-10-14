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
    'pin_daily_attempts' => 20,
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
            'highlights' => ['3 outlet', '15 karyawan', '500 pesan WhatsApp per bulan', 'Pesan lewat QR di meja + toko online, bayar QRIS', 'Layar dapur, komisi, member'],
        ],
        'bisnis' => [
            'label' => 'Bisnis',
            'price' => 349000,
            'tagline' => 'Untuk usaha banyak cabang, gudang, dan produksi.',
            'outlets' => null,
            'staff' => null,
            'whatsapp' => 2000,
            'highlights' => ['Outlet & karyawan tanpa batas', '2.000 pesan WhatsApp per bulan', 'Nomor WhatsApp sendiri', 'Asisten Toko: tanya omzet & stok lewat WhatsApp', 'Gudang, olah, kemas ulang, batch'],
        ],
    ],

    'plan_modules' => [
        'qr_order' => 'pro',
        'online_order' => 'pro',
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
        'assistant' => 'bisnis',
    ],

    'durations' => [
        1 => ['discount' => 0, 'label' => '1 bulan'],
        3 => ['discount' => 500, 'label' => '3 bulan (hemat 5%)'],
        6 => ['discount' => 1000, 'label' => '6 bulan (hemat 10%)'],
        12 => ['discount' => 1667, 'label' => '12 bulan (bayar 10 bulan)'],
    ],

    'referral' => [
        'reward_days' => (int) env('HERMES_REFERRAL_REWARD_DAYS', 30),
        'new_tenant_bonus_days' => (int) env('HERMES_REFERRAL_BONUS_DAYS', 7),
    ],

    'payment' => [
        'driver' => env('HERMES_PAYMENT_DRIVER', env('PAYMENT_DRIVER')),
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
    // Asisten Toko (AI lewat aplikasi & WhatsApp). Pulsa dihitung dalam rupiah dari biaya token asli.
    'assistant' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        // Nomor WA khusus asisten di Fonnte; webhook Fonnte diarahkan ke /webhook/asisten/{secret}.
        'fonnte_token' => env('HERMES_AI_FONNTE_TOKEN'),
        'webhook_secret' => env('HERMES_AI_WEBHOOK_SECRET'),
        'wa_device' => env('HERMES_AI_WA_DEVICE'),
        'timeout' => (float) env('HERMES_AI_TIMEOUT', 60),
        'usd_rate' => (float) env('HERMES_AI_USD_RATE', 16500),
        'budget' => (int) env('HERMES_AI_BUDGET', 15000),
        'trial_budget' => (int) env('HERMES_AI_TRIAL_BUDGET', 3000),
        'daily_share' => 0.2,
        'max_phones' => 3,
        'max_rounds' => 6,
        'history' => 6,
        'history_hours' => 3,
        'question_max' => 500,
        'question_estimate' => 40,
        'modes' => [
            'quick' => ['model' => env('HERMES_AI_QUICK_MODEL', 'claude-haiku-5-5'), 'effort' => 'low', 'max_tokens' => 4000, 'reserve' => 200],
            'deep' => ['model' => env('HERMES_AI_DEEP_MODEL', 'claude-haiku-5-5'), 'effort' => env('HERMES_AI_DEEP_EFFORT', 'high'), 'max_tokens' => 12000, 'reserve' => 2500],
        ],
        // Harga USD per 1 juta token.
        'prices' => [
            'claude-haiku-5-5' => ['input' => 0.10, 'output' => 0.50, 'cache_read' => 0.01, 'cache_write' => 0.125],
            'claude-sonnet-5-5' => ['input' => 2.00, 'output' => 10.00, 'cache_read' => 0.20, 'cache_write' => 2.50],
            'claude-opus-5-5' => ['input' => 4.00, 'output' => 20.00, 'cache_read' => 0.40, 'cache_write' => 5.00],
        ],
    ],
];
