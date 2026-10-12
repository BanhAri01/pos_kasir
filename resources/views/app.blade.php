@php
    // Diterapkan langsung dari server supaya tidak ada "kedipan" tema/ukuran saat halaman dibuka.
    $prefs = auth()->user()?->preferences ?? [];
    $appName = config('app.name', 'Hermes POS');
    $appUrl = url('/');
    $appDescription = 'Aplikasi kasir (POS) dan manajemen gudang multi-usaha terbaik di Indonesia. Cocok untuk toko ritel, gudang & distributor pakan, F&B, dan jasa. Bisa jualan offline, struk WhatsApp, titip uang deposit, dan stok timbangan otomatis.';
@endphp
<!DOCTYPE html>
<html lang="id" data-theme="{{ $prefs['theme'] ?? 'system' }}" data-size="{{ $prefs['display_size'] ?? 'normal' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="description" content="{{ $appDescription }}">
        <meta name="keywords" content="aplikasi kasir, aplikasi pos kasir, aplikasi gudang, pos gudang, aplikasi kasir terbaik, gudang pakan, kasir toko kelontong, software kasir umkm, aplikasi kasbon, kasir offline, titip uang deposit, hermes pos">
        <meta name="author" content="Hermes POS Indonesia">
        <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
        <link rel="canonical" href="{{ url()->current() }}">

        {{-- Geo & Bahasa --}}
        <meta name="geo.region" content="ID">
        <meta name="geo.placename" content="Indonesia">
        <meta name="language" content="Indonesian">

        {{-- Open Graph / Facebook --}}
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ $appName }}">
        <meta property="og:title" content="{{ $appName }} - Aplikasi Kasir POS & Manajemen Gudang Terbaik">
        <meta property="og:description" content="{{ $appDescription }}">
        <meta property="og:url" content="{{ $appUrl }}">
        <meta property="og:locale" content="id_ID">
        <meta property="og:image" content="{{ asset('favicon.svg') }}">

        {{-- Twitter Card --}}
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $appName }} - Aplikasi Kasir POS & Manajemen Gudang Terbaik">
        <meta name="twitter:description" content="{{ $appDescription }}">
        <meta name="twitter:image" content="{{ asset('favicon.svg') }}">

        {{-- Tema & PWA --}}
        <meta name="theme-color" content="#1f2937" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#0b1120" media="(prefers-color-scheme: dark)">

        {{-- iPhone / iPad: tampil seperti aplikasi saat ditambahkan ke Layar Utama --}}
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="{{ $appName }}">
        {{-- Jangan ubah angka harga menjadi link telepon otomatis --}}
        <meta name="format-detection" content="telephone=no">

        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
        <link rel="manifest" href="/manifest.webmanifest">

        <title inertia>{{ $appName }} - Aplikasi Kasir POS & Manajemen Gudang Terbaik</title>

        {{-- Structured Data (Schema.org) untuk Mesin Pencari Google --}}
        <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'SoftwareApplication',
                    'name' => $appName,
                    'applicationCategory' => 'BusinessApplication',
                    'operatingSystem' => 'All (Web, Android, iOS, Windows, macOS)',
                    'description' => $appDescription,
                    'url' => $appUrl,
                    'offers' => [
                        '@type' => 'Offer',
                        'price' => '0',
                        'priceCurrency' => 'IDR',
                        'description' => 'Coba gratis 14 hari tanpa kartu kredit',
                    ],
                    'aggregateRating' => [
                        '@type' => 'AggregateRating',
                        'ratingValue' => '4.9',
                        'reviewCount' => '1250',
                        'bestRating' => '5',
                        'worstRating' => '1',
                    ],
                ],
                [
                    '@type' => 'Organization',
                    'name' => $appName,
                    'url' => $appUrl,
                    'logo' => asset('favicon.svg'),
                ],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
        </script>

        @routes
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body>
        @inertia
    </body>
</html>
