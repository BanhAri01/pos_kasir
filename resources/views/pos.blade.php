<!DOCTYPE html>
<html lang="id" data-theme="{{ $theme }}" data-size="{{ $size }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#1f2937" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#0b1120" media="(prefers-color-scheme: dark)">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="Hermes POS">
        <meta name="format-detection" content="telephone=no">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
        <link rel="manifest" href="/manifest.webmanifest">
        <title>Kasir - Hermes POS</title>

        @routes
        @vite(['resources/css/app.css', 'resources/js/pos/main.js'])
    </head>
    <body>
        {{-- Aplikasi kasir (Vue). Terpisah dari halaman lain supaya bisa berjalan offline (Fase 5). --}}
        <div id="pos"></div>
    </body>
</html>
