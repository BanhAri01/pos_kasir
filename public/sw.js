/*
 * Service Worker Hermes POS: menyimpan layar kasir di HP supaya tetap terbuka saat offline.
 *
 * Strategi:
 *  - /build/assets/*   : simpan permanen (nama file berubah setiap versi baru).
 *  - /kasir (halaman)  : coba internet dulu (maks. 4 detik), kalau gagal pakai salinan terakhir.
 *  - foto barang       : pakai salinan, perbarui diam-diam di belakang.
 *  - halaman lain      : butuh internet; kalau offline tampil halaman "offline.html".
 *  - data / API        : TIDAK disimpan di sini (diurus IndexedDB oleh aplikasi kasir).
 */
const VERSION = 'hermes-v3'; // v3: logo caduceus + gaya Yunani
const STATIC = `${VERSION}-static`;
const PAGES = `${VERSION}-pages`;
const IMAGES = `${VERSION}-images`;

const PRECACHE = ['/offline.html', '/favicon.svg', '/manifest.webmanifest', '/icons/icon-192.png', '/icons/icon-512.png'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(STATIC).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((k) => !k.startsWith(VERSION)).map((k) => caches.delete(k))))
            .then(() => self.clients.claim()),
    );
});

function timeout(ms) {
    return new Promise((_, reject) => setTimeout(() => reject(new Error('timeout')), ms));
}

async function networkFirstPage(request) {
    const cache = await caches.open(PAGES);
    try {
        const response = await Promise.race([fetch(request), timeout(4000)]);
        // Hanya simpan halaman kasir yang berhasil dibuka (bukan halaman login / error).
        if (response.ok && !response.redirected) cache.put('/kasir', response.clone());
        return response;
    } catch {
        return (await cache.match('/kasir')) ?? (await caches.match('/offline.html'));
    }
}

async function cacheFirst(request, cacheName) {
    const cached = await caches.match(request);
    if (cached) return cached;
    const response = await fetch(request);
    if (response.ok) (await caches.open(cacheName)).put(request, response.clone());
    return response;
}

async function staleWhileRevalidate(request) {
    const cache = await caches.open(IMAGES);
    const cached = await cache.match(request);
    const network = fetch(request)
        .then((response) => {
            if (response.ok) cache.put(request, response.clone());
            return response;
        })
        .catch(() => cached);
    return cached ?? network;
}

self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    if (request.mode === 'navigate') {
        if (url.pathname === '/kasir') {
            event.respondWith(networkFirstPage(request));
        } else {
            event.respondWith(fetch(request).catch(() => caches.match('/offline.html')));
        }
        return;
    }

    if (url.pathname.startsWith('/build/assets/') || PRECACHE.includes(url.pathname) || url.pathname.startsWith('/icons/')) {
        event.respondWith(cacheFirst(request, STATIC));
        return;
    }

    if (url.pathname.startsWith('/storage/')) {
        event.respondWith(staleWhileRevalidate(request));
    }
});
