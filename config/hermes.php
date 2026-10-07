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
];
