/** UUID dibuat di HP, supaya transaksi tetap unik walau dibuat saat offline. */
export function uuid() {
    if (crypto.randomUUID) return crypto.randomUUID();
    // Cadangan untuk browser lama.
    return '10000000-1000-4000-8000-100000000000'.replace(/[018]/g, (c) =>
        (c ^ (crypto.getRandomValues(new Uint8Array(1))[0] & (15 >> (c / 4)))).toString(16),
    );
}

/**
 * Nomor nota berurutan per HP per hari: {kode HP}-{yymmdd}-{urut}, contoh "A-251006-0007".
 * Disimpan di HP sehingga tetap berurutan walau offline.
 */
export function nextSaleNumber(deviceCode = 'X') {
    const now = new Date();
    const ymd = `${String(now.getFullYear()).slice(2)}${String(now.getMonth() + 1).padStart(2, '0')}${String(now.getDate()).padStart(2, '0')}`;
    const key = `hermes.saleSeq.${deviceCode}.${ymd}`;
    let seq = 1;
    try {
        seq = (parseInt(localStorage.getItem(key) ?? '0', 10) || 0) + 1;
        localStorage.setItem(key, String(seq));
    } catch {
        seq = Math.floor(Date.now() / 1000) % 10000;
    }
    return `${deviceCode}-${ymd}-${String(seq).padStart(4, '0')}`;
}
