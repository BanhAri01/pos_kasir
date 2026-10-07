/**
 * Penyimpanan di HP (IndexedDB) supaya kasir tetap jalan saat internet mati.
 *
 *  - kv     : data awal kasir (barang, harga, pelanggan, pengaturan) per pengguna
 *  - outbox : antrean data yang belum terkirim ke server (urut sesuai waktu dibuat)
 *  - sales  : salinan transaksi yang dibuat di HP ini (untuk riwayat & cetak ulang saat offline)
 *  - bills  : pesanan meja yang belum dibayar (open bill), per HP
 */
import Dexie from 'dexie';

export const db = new Dexie('hermes-pos');

db.version(1).stores({
    kv: 'key',
    outbox: '++id, type, created_at',
    sales: 'uuid, shift_uuid, created_at, synced',
});

// v2: pesanan meja yang belum dibayar (open bill), disimpan di HP ini (berjalan offline).
db.version(2).stores({
    bills: 'table_id, updated_at',
});

export async function getKv(key, fallback = null) {
    try {
        return (await db.kv.get(key))?.value ?? fallback;
    } catch {
        return fallback;
    }
}

export async function setKv(key, value) {
    try {
        await db.kv.put({ key, value: JSON.parse(JSON.stringify(value)) });
    } catch {
        /* penyimpanan penuh / mode pribadi: kasir tetap jalan selama online */
    }
}

export async function enqueue(type, payload) {
    return db.outbox.add({ type, payload: JSON.parse(JSON.stringify(payload)), created_at: Date.now(), attempts: 0, last_error: null });
}

export async function pendingCount() {
    try {
        return await db.outbox.count();
    } catch {
        return 0;
    }
}
