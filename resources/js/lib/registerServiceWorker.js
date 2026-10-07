/**
 * Daftarkan Service Worker (public/sw.js) supaya layar kasir bisa dibuka saat offline.
 * Tidak dipakai saat development dengan `npm run dev` (supaya perubahan langsung terlihat).
 */
export function registerServiceWorker() {
    if (!('serviceWorker' in navigator) || import.meta.env.DEV) return;

    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {
            /* browser tidak mendukung / mode pribadi: aplikasi tetap jalan selama online */
        });
    });
}
