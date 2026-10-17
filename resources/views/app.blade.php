@php
    // Diterapkan langsung dari server supaya tidak ada "kedipan" tema/ukuran saat halaman dibuka.
    $prefs = auth()->user()?->preferences ?? [];
    $appName = config('app.name', 'Kasloka');
    // Hanya halaman publik yang boleh masuk Google; tagihan, pesanan, dan aplikasi selalu noindex.
    $indexable = request()->routeIs('home', 'register', 'login');
    $appDescription = 'Aplikasi kasir (POS) dan manajemen gudang untuk UMKM Indonesia: toko ritel, grosir & distributor, F&B, dan jasa. Bisa jualan offline, struk & tagihan WhatsApp, kasbon, dan stok timbangan.';
@endphp
<!DOCTYPE html>
<html lang="id" data-theme="{{ $prefs['theme'] ?? 'system' }}" data-size="{{ $prefs['display_size'] ?? 'normal' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="description" content="{{ $appDescription }}">
        @if ($indexable)
            <meta name="robots" content="index, follow, max-image-preview:large">
            <link rel="canonical" href="{{ url()->current() }}">
            <meta property="og:type" content="website">
            <meta property="og:site_name" content="{{ $appName }}">
            <meta property="og:title" content="{{ $appName }} - Aplikasi Kasir & Gudang untuk UMKM">
            <meta property="og:description" content="{{ $appDescription }}">
            <meta property="og:url" content="{{ url('/') }}">
            <meta property="og:locale" content="id_ID">
            <meta property="og:image" content="{{ asset('icons/apple-touch-icon.png') }}">
            <meta name="twitter:card" content="summary">
        @else
            <meta name="robots" content="noindex, nofollow">
        @endif
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

        <title inertia>{{ $appName }}</title>

        @if (request()->routeIs('home'))
            {{-- Data terstruktur hanya berisi fakta: tanpa rating/ulasan sampai ada ulasan asli dari pelanggan. --}}
            <script type="application/ld+json">
            {!! json_encode([
                '@context' => 'https://schema.org',
                '@graph' => [
                    [
                        '@type' => 'SoftwareApplication',
                        'name' => $appName,
                        'applicationCategory' => 'BusinessApplication',
                        'operatingSystem' => 'Web, Android, iOS, Windows',
                        'description' => $appDescription,
                        'url' => url('/'),
                        'offers' => [
                            '@type' => 'AggregateOffer',
                            'priceCurrency' => 'IDR',
                            'lowPrice' => (string) collect(config('hermes.plans'))->min('price'),
                            'highPrice' => (string) collect(config('hermes.plans'))->max('price'),
                        ],
                    ],
                    [
                        '@type' => 'Organization',
                        'name' => $appName,
                        'url' => url('/'),
                        'logo' => asset('icons/apple-touch-icon.png'),
                    ],
                ],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
            </script>
        @endif

        @routes
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body>
        @inertia
    </body>
</html>
