@php
    // Diterapkan langsung dari server supaya tidak ada "kedipan" tema/ukuran saat halaman dibuka.
    $prefs = auth()->user()?->preferences ?? [];
@endphp
<!DOCTYPE html>
<html lang="id" data-theme="{{ $prefs['theme'] ?? 'system' }}" data-size="{{ $prefs['display_size'] ?? 'normal' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="description" content="Hermes POS: kasir cepat, kabar sampai. Aplikasi kasir mudah untuk UMKM Indonesia.">
        <meta name="theme-color" content="#1f2937" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#0b1120" media="(prefers-color-scheme: dark)">

        {{-- iPhone / iPad: tampil seperti aplikasi saat ditambahkan ke Layar Utama --}}
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="Hermes POS">
        {{-- Jangan ubah angka harga menjadi link telepon otomatis --}}
        <meta name="format-detection" content="telephone=no">

        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
        <link rel="manifest" href="/manifest.webmanifest">

        <title inertia>{{ config('app.name', 'Hermes POS') }}</title>

        @routes
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body>
        @inertia
    </body>
</html>
