import { reactive } from 'vue';
import { playTing, vibrate } from '@/composables/useFeedback';
import { api } from './lib/api';
import { showToast } from './lib/toast';
import { addToCart, hasModule, openTable, saveToTable, store } from './store';

export const selfOrders = reactive({ list: [], busy: null, seen: new Set(), ready: false });

let timer = null;

export async function refreshSelfOrders() {
    if (!store.boot?.shift || !hasModule('qr_order') || !navigator.onLine) return;
    try {
        const { data } = await api.get(route('self-orders.pending'));
        const fresh = data.filter((o) => !selfOrders.seen.has(o.uuid));
        if (fresh.length && selfOrders.ready) {
            playTing();
            vibrate([120, 80, 120]);
            showToast(`${fresh.length} pesanan QR baru masuk.`, 'info');
        }
        data.forEach((o) => selfOrders.seen.add(o.uuid));
        selfOrders.list = data;
        selfOrders.ready = true;
    } catch {
        return;
    }
}

export function startSelfOrderPolling() {
    stopSelfOrderPolling();
    if (!hasModule('qr_order')) return;
    refreshSelfOrders();
    timer = setInterval(() => {
        if (!document.hidden) refreshSelfOrders();
    }, 15000);
}

export function stopSelfOrderPolling() {
    if (timer) clearInterval(timer);
    timer = null;
}

export async function acceptSelfOrder(order) {
    if (!order.paid && store.cart.length && store.tableId !== order.table_id) {
        if (store.tableId) {
            await saveToTable();
        } else {
            throw new Error('Selesaikan atau kosongkan keranjang dulu, lalu terima pesanan ini.');
        }
    }

    selfOrders.busy = order.uuid;
    try {
        const res = await api.post(route('self-orders.accept', order.uuid), { shift_uuid: order.paid ? store.boot.shift.uuid : null });
        if (!order.paid) await loadIntoTable(order);
        selfOrders.list = selfOrders.list.filter((o) => o.uuid !== order.uuid);
        return res.message;
    } finally {
        selfOrders.busy = null;
    }
}

export async function rejectSelfOrder(order, reason) {
    selfOrders.busy = order.uuid;
    try {
        const res = await api.post(route('self-orders.reject', order.uuid), { reason });
        selfOrders.list = selfOrders.list.filter((o) => o.uuid !== order.uuid);
        return res.message;
    } finally {
        selfOrders.busy = null;
    }
}

async function loadIntoTable(order) {
    if (order.table_id) {
        if (store.tableId !== order.table_id) openTable(order.table_id);
    } else {
        store.orderType = 'dine_in';
    }

    const missing = [];
    for (const item of order.items) {
        const product = store.boot.products.find((p) => p.id === item.product_id);
        if (!product) {
            missing.push(item.name);
            continue;
        }
        addToCart(product, { qty: item.qty, modifierIds: item.modifier_ids ?? [], note: item.note ?? '' });
    }

    const tag = `QR ${order.code} (${order.customer_name})${order.note ? `: ${order.note}` : ''}`;
    store.note = store.note ? `${store.note} · ${tag}` : tag;

    if (store.tableId) await saveToTable();
    if (missing.length) showToast(`Menu tidak ditemukan di kasir: ${missing.join(', ')}. Muat ulang kasir.`, 'error');
}
