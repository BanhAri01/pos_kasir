/**
 * Mengirim antrean (outbox) ke server. Berjalan:
 *  - segera setelah transaksi dibuat (kalau online),
 *  - saat internet menyala lagi,
 *  - saat aplikasi dibuka kembali,
 *  - dan setiap 30 detik (cadangan, karena iPhone tidak mendukung Background Sync).
 *
 * Aman diulang: server memakai uuid dari HP, jadi data tidak akan dobel.
 */
import { reactive } from 'vue';
import { api } from './api';
import { db, pendingCount } from './db';

export const syncState = reactive({
    online: typeof navigator === 'undefined' ? true : navigator.onLine,
    pending: 0,
    syncing: false,
    lastError: null,
    lastSyncedAt: null,
});

const listeners = new Set();
/** Dipanggil setelah ada data yang berhasil terkirim (mis. untuk memperbarui riwayat). */
export function onSynced(fn) {
    listeners.add(fn);
    return () => listeners.delete(fn);
}

let running = null;

export async function refreshPending() {
    syncState.pending = await pendingCount();
}

export function syncNow() {
    running ??= push().finally(() => (running = null));
    return running;
}

async function push() {
    await refreshPending();
    if (!syncState.pending || !navigator.onLine) return;

    syncState.syncing = true;
    try {
        // Kirim per 25 data, berurutan (buka kasir harus sampai sebelum penjualannya).
        while (true) {
            const batch = await db.outbox.orderBy('id').limit(25).toArray();
            if (!batch.length) break;

            const res = await api.post(route('pos.api.sync'), {
                items: batch.map((item) => ({ id: item.id, type: item.type, payload: item.payload })),
            });

            const done = [];
            let hadError = false;
            for (const result of res.results) {
                if (result.status === 'ok' || result.status === 'conflict') {
                    done.push(result.id);
                    const item = batch.find((b) => b.id === result.id);
                    if (item?.type === 'sale') {
                        await db.sales.update(item.payload.uuid, { synced: 1, server: result.data ?? null });
                    }
                } else {
                    hadError = true;
                    await db.outbox.update(result.id, { attempts: (batch.find((b) => b.id === result.id)?.attempts ?? 0) + 1, last_error: result.message });
                }
            }
            await db.outbox.bulkDelete(done);
            syncState.lastSyncedAt = new Date();
            syncState.lastError = null;
            listeners.forEach((fn) => fn());

            // Ada kesalahan sementara: berhenti dulu, coba lagi di putaran berikutnya
            // (supaya urutan tetap terjaga).
            if (hadError) break;
        }
    } catch (e) {
        syncState.lastError = e.message;
        if (e.offline) syncState.online = false;
    } finally {
        syncState.syncing = false;
        await refreshPending();
    }
}

let started = false;

export function startSyncLoop() {
    if (started) return;
    started = true;

    window.addEventListener('online', () => {
        syncState.online = true;
        syncNow();
    });
    window.addEventListener('offline', () => (syncState.online = false));
    document.addEventListener('visibilitychange', () => document.visibilityState === 'visible' && syncNow());
    setInterval(() => syncNow(), 30000);

    refreshPending().then(() => syncNow());
}
