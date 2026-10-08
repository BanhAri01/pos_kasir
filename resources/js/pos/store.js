/**
 * Status layar kasir: data awal, keranjang, kasir (shift), dan aksi-aksinya.
 *
 * OFFLINE-FIRST: setiap aksi (buka kasir, jual, uang masuk/keluar, pelanggan baru, bayar kasbon,
 * kirim ke dapur) disimpan dulu di HP (antrean IndexedDB), lalu dikirim ke server.
 * Online maupun offline memakai jalur yang sama.
 *
 * Yang butuh internet: tutup kasir, batal/kembalikan transaksi, tandai "habis hari ini".
 */
import { computed, reactive } from 'vue';
import { api, ApiError } from './lib/api';
import { calculate, lineMoney } from './lib/calculator';
import { db, enqueue, getKv, setKv } from './lib/db';
import { nextSaleNumber, uuid } from './lib/ids';
import { resolvePrice, todayIn } from './lib/pricing';
import { onSynced, syncNow, syncState } from './lib/sync';

const state = reactive({
    loading: true,
    error: null,
    fromCache: false, // data awal diambil dari HP karena offline
    boot: null,
    cart: [],
    discount: { type: null, value: 0 },
    customer: null,
    orderType: 'walk_in',
    note: '',
    tableId: null, // meja yang sedang dilayani (modul tables)
    bills: {}, // { [table_id]: bill } pesanan meja yang belum dibayar
    payOnlyKeys: null, // split bill: hanya baris ini yang dibayar sekarang
    redeemPoints: 0,
});

export const store = state;

// ---------------------------------------------------------------- data awal

export async function loadBootstrap() {
    state.loading = true;
    state.error = null;
    try {
        const boot = await api.get(route('pos.api.bootstrap'));
        // Kasir yang dibuka di HP ini tapi belum terkirim tetap dipakai.
        if (!boot.shift) {
            const pendingShift = await db.outbox.where('type').equals('shift.open').first().catch(() => null);
            if (pendingShift && pendingShift.payload.outlet_id === boot.outlet.id) {
                boot.shift = { uuid: pendingShift.payload.uuid, opened_at: pendingShift.payload.opened_at, opening_cash: pendingShift.payload.opening_cash, pending: true };
            }
        }
        state.boot = boot;
        state.fromCache = false;
        await setKv('boot', boot);
    } catch (e) {
        const cached = await getKv('boot');
        if (e instanceof ApiError && e.offline && cached) {
            state.boot = cached;
            state.fromCache = true;
        } else if (e instanceof ApiError && e.offline) {
            state.error = 'Kasir butuh internet saat pertama kali dibuka di HP ini. Sambungkan internet, lalu coba lagi.';
        } else {
            state.error = e.message;
        }
    } finally {
        state.loading = false;
    }

    if (state.boot) {
        applyPreferences(state.boot.user.preferences);
        if (state.boot.tenant.pos_layout === 'fnb') state.orderType = 'dine_in';
        await loadBills();
    }
}

/** Setelah sinkron, perbarui data awal (stok, harga) diam-diam kalau sedang tidak ada belanjaan. */
onSynced(async () => {
    if (state.boot && !state.cart.length && !state.loading) {
        try {
            const fresh = await api.get(route('pos.api.bootstrap'));
            if (!fresh.shift && state.boot.shift) fresh.shift = state.boot.shift;
            state.boot = fresh;
            state.fromCache = false;
            await setKv('boot', fresh);
        } catch {
            /* tetap pakai data lama */
        }
    }
});

function persistBoot() {
    return setKv('boot', state.boot);
}

function applyPreferences(prefs = {}) {
    document.documentElement.dataset.size = prefs.display_size ?? 'normal';
    document.documentElement.dataset.theme = prefs.theme ?? 'system';
}

export const can = (permission) => state.boot?.user.permissions.includes(permission) ?? false;
export const hasModule = (code) => state.boot?.tenant.modules.includes(code) ?? false;
export const simpleMode = () => state.boot?.user.preferences?.simple_mode ?? true;
export const canPayLater = () => hasModule('kasbon') || hasModule('credit_sales') || hasModule('order_status');

function requireOnline(action) {
    if (!navigator.onLine) {
        throw new ApiError(`${action} butuh internet. Tunggu sampai internet menyala, lalu coba lagi.`, { offline: true });
    }
}

const productById = (id) => state.boot.products.find((p) => p.id === id);

// ---------------------------------------------------------------- keranjang

let keySeq = 0;

function priceLine(line) {
    const product = productById(line.product_id);
    if (!product || line.price_overridden) return;
    const resolved = resolvePrice(product, {
        qty: line.qty,
        unitId: line.unit_id,
        modifierIds: line.modifier_ids,
        priceLevelId: state.customer?.price_level_id ?? null,
        promotions: state.boot.promotions ?? [],
        date: todayIn(state.boot.tenant.timezone),
    });
    line.unit_price = resolved.unitPrice;
    line.promo = resolved.promo;
    line.original_price = resolved.unitPrice;
    line.unit = resolved.unitName;
    line.conversion = resolved.conversion;
    line.modifiers = resolved.modifiers;
}

/**
 * @param {object} options  qty, unitPrice (harga manual), note, unitId, modifierIds, staffId
 */
export function addToCart(product, { qty = 1, unitPrice = null, note = '', unitId = null, modifierIds = [], staffId = null } = {}) {
    const simple = !note && unitPrice === null && !unitId && !modifierIds.length && !staffId;

    // Barang yang sama tanpa catatan / pilihan / harga khusus digabung (jumlahnya ditambah).
    const existing = simple
        ? state.cart.find((l) => l.product_id === product.id && !l.note && !l.unit_id && !l.modifier_ids.length && !l.staff_id && !l.price_overridden)
        : null;

    if (existing && product.pricing_mode === 'fixed') {
        existing.qty = round3(Number(existing.qty) + Number(qty));
        priceLine(existing);
        return existing;
    }

    const line = reactive({
        key: ++keySeq,
        product_id: product.id,
        name: product.name,
        unit: product.unit,
        decimal: product.unit_allows_decimal || product.pricing_mode === 'per_weight',
        pricing_mode: product.pricing_mode,
        unit_price: 0,
        original_price: 0,
        price_overridden: unitPrice !== null && product.pricing_mode !== 'open_price' ? true : false,
        qty: round3(Number(qty)),
        discount_amount: 0,
        note,
        unit_id: unitId,
        conversion: 1,
        modifier_ids: [...modifierIds],
        modifiers: [],
        staff_id: staffId,
        image_url: product.image_url,
        is_membership: product.is_membership,
        sent_to_kitchen: false,
    });

    priceLine(line);
    if (unitPrice !== null) line.unit_price = unitPrice;
    state.cart.push(line);
    return line;
}

export function updateLine(line, changes) {
    Object.assign(line, changes);
    if (changes.unit_price === undefined) priceLine(line);
}

export function setQty(line, qty) {
    const value = round3(Number(String(qty).replace(',', '.')) || 0);
    if (value <= 0) removeLine(line);
    else {
        line.qty = value;
        priceLine(line); // harga grosir bisa berubah sesuai jumlah
    }
}

export function removeLine(line) {
    state.cart = state.cart.filter((l) => l.key !== line.key);
}

export function setCustomer(customer) {
    if (state.redeemPoints && state.customer?.uuid !== customer?.uuid) cancelRedeem();
    state.customer = customer;
    state.cart.forEach(priceLine); // harga khusus pelanggan (tukang, kontraktor, grosir)
}

export function clearCart() {
    state.cart = [];
    state.discount = { type: null, value: 0 };
    state.customer = null;
    state.note = '';
    state.tableId = null;
    state.payOnlyKeys = null;
    state.redeemPoints = 0;
    state.orderType = state.boot?.tenant.pos_layout === 'fnb' ? 'dine_in' : 'walk_in';
}

export const loyaltyRule = () => (hasModule('loyalty') ? state.boot?.tenant.loyalty ?? null : null);

export function customerPoints() {
    const c = state.customer ? state.boot.customers.find((x) => x.uuid === state.customer.uuid) : null;
    return c?.points ?? state.customer?.points ?? 0;
}

export function canRedeemMore() {
    const rule = loyaltyRule();
    return !!rule && !!state.customer && customerPoints() - state.redeemPoints >= rule.points_for_reward;
}

export function redeemReward() {
    const rule = loyaltyRule();
    if (!canRedeemMore()) return false;
    state.redeemPoints += rule.points_for_reward;
    state.discount = { type: 'amount', value: (state.redeemPoints / rule.points_for_reward) * rule.reward_value };
    return true;
}

export function cancelRedeem() {
    if (state.redeemPoints) state.discount = { type: null, value: 0 };
    state.redeemPoints = 0;
}

export const cartCount = computed(() => state.cart.reduce((sum, l) => sum + (l.decimal ? 1 : Number(l.qty)), 0));

/** Baris yang dibayar sekarang (semua, atau sebagian kalau split bill). */
export const payingLines = computed(() => (state.payOnlyKeys ? state.cart.filter((l) => state.payOnlyKeys.includes(l.key)) : state.cart));

function outletOptions() {
    const outlet = state.boot?.outlet ?? {};
    return {
        serviceChargeBp: outlet.service_charge_bp ?? 0,
        taxBp: outlet.tax_rate_bp ?? 0,
        taxInclusive: outlet.tax_inclusive ?? false,
    };
}

export const totals = computed(() =>
    calculate(payingLines.value, { discountType: state.discount.type, discountValue: state.discount.value, ...outletOptions() }),
);

function round3(n) {
    return Math.round(n * 1000) / 1000;
}

// ---------------------------------------------------------------- meja & open bill (modul tables)

async function loadBills() {
    try {
        const rows = await db.bills.toArray();
        state.bills = Object.fromEntries(rows.map((b) => [b.table_id, b]));
    } catch {
        state.bills = {};
    }
}

export const tableName = (id) => state.boot?.tables?.find((t) => t.id === id)?.name ?? '';

/** Buka meja: kalau ada pesanan tersimpan, masukkan ke keranjang. */
export function openTable(tableId) {
    const bill = state.bills[tableId];
    clearCart();
    state.tableId = tableId;
    state.orderType = 'dine_in';
    if (bill) {
        state.cart = bill.items.map((l) => reactive({ ...l, key: ++keySeq }));
        state.customer = bill.customer ?? null;
        state.note = bill.note ?? '';
    }
}

/** Simpan pesanan ke meja (bayar nanti) dan kirim barang baru ke dapur. */
export async function saveToTable() {
    if (!state.tableId) return;
    await sendNewLinesToKitchen(`Meja ${tableName(state.tableId)}`);

    const bill = {
        table_id: state.tableId,
        items: JSON.parse(JSON.stringify(state.cart)),
        customer: state.customer ? JSON.parse(JSON.stringify(state.customer)) : null,
        note: state.note,
        updated_at: Date.now(),
    };
    await db.bills.put(bill);
    state.bills = { ...state.bills, [state.tableId]: bill };
    clearCart();
}

async function deleteBill(tableId) {
    await db.bills.delete(tableId).catch(() => {});
    const { [tableId]: _removed, ...rest } = state.bills;
    state.bills = rest;
}

/** Kirim barang yang belum dikirim ke layar dapur (modul kitchen_display). */
async function sendNewLinesToKitchen(label) {
    if (!hasModule('kitchen_display')) return;
    const fresh = state.cart.filter((l) => !l.sent_to_kitchen && !l.is_membership);
    if (!fresh.length) return;

    await enqueue('kitchen.ticket', {
        uuid: uuid(),
        outlet_id: state.boot.outlet.id,
        label,
        order_type: state.orderType,
        note: state.note || null,
        items: fresh.map((l) => ({ name: l.name, qty: String(l.qty).replace('.', ','), note: l.note || null, modifiers: l.modifiers.map((m) => m.name) })),
    });
    fresh.forEach((l) => (l.sent_to_kitchen = true));
    syncNow();
}

// ---------------------------------------------------------------- nomor antrean (modul queue)

function nextQueueNumber() {
    const code = state.boot.device?.code ?? 'A';
    const today = new Date().toISOString().slice(0, 10);
    const key = `hermes.queue.${code}.${today}`;
    let n = 1;
    try {
        n = (parseInt(localStorage.getItem(key) ?? '0', 10) || 0) + 1;
        localStorage.setItem(key, String(n));
    } catch {
        n = Math.floor(Date.now() / 1000) % 1000;
    }
    return code === 'A' ? String(n) : `${code}${n}`;
}

// ---------------------------------------------------------------- kasir (shift)

export async function openShift(openingCash) {
    const shift = { uuid: uuid(), opened_at: new Date().toISOString(), opening_cash: openingCash, pending: true };
    await enqueue('shift.open', { uuid: shift.uuid, outlet_id: state.boot.outlet.id, opening_cash: openingCash, opened_at: shift.opened_at });
    state.boot.shift = shift;
    await persistBoot();
    syncNow();
    return shift;
}

export async function shiftSummary() {
    requireOnline('Menghitung uang tutup kasir');
    await syncNow();
    if (syncState.pending) {
        throw new ApiError(`Masih ada ${syncState.pending} data yang belum terkirim. Tunggu sebentar lalu coba lagi.`);
    }
    return (await api.get(route('pos.api.shifts.summary', state.boot.shift.uuid))).data;
}

export async function addCashMovement(type, amount, reason) {
    await enqueue('cash', { uuid: uuid(), shift_uuid: state.boot.shift.uuid, type, amount, reason });
    syncNow();
    const label = type === 'in' ? 'Uang masuk' : 'Uang keluar';
    return { message: `${label} Rp${new Intl.NumberFormat('id-ID').format(amount)} sudah dicatat.` };
}

export async function closeShift(countedCash, note) {
    requireOnline('Tutup kasir');
    const res = await api.post(route('pos.api.shifts.close', state.boot.shift.uuid), { counted_cash: countedCash, note });
    state.boot.shift = null;
    clearCart();
    await persistBoot();
    return res;
}

// ---------------------------------------------------------------- bayar

/** Salinan transaksi untuk layar berhasil, struk, & riwayat (sebelum/tanpa jawaban server). */
function buildLocalSale(payload, payments, lines) {
    const t = calculate(lines, { discountType: payload.discount_type ?? null, discountValue: payload.discount_value ?? 0, ...outletOptions() });
    const tendered = payments.reduce((s, p) => s + p.amount, 0);
    let change = Math.max(0, tendered - t.total);
    const methods = state.boot.payment_methods;
    const now = new Date(payload.created_at);

    return {
        uuid: payload.uuid,
        number: payload.number,
        status: 'completed',
        payment_status: tendered >= t.total ? 'paid' : 'partial',
        pending: true,
        queue_number: payload.queue_number ?? null,
        table: payload.table_id ? tableName(payload.table_id) : null,
        date: now.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }),
        time: now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', hour12: false }).replace('.', ':'),
        cashier: state.boot.user.name,
        customer: state.customer ? { name: state.customer.name, phone: state.customer.phone } : null,
        items: lines.map((l, i) => ({
            id: null,
            name: l.name + (l.modifiers.length ? ` (${l.modifiers.map((m) => m.name).join(', ')})` : ''),
            qty: String(l.qty).replace('.', ','),
            unit: l.unit,
            unit_price: l.unit_price,
            discount_amount: t.lines[i].discount,
            subtotal: t.lines[i].subtotal,
            note: l.note || null,
            refundable_qty: 0,
        })),
        payments: payments.map((p) => {
            const method = methods.find((m) => m.id === p.payment_method_id);
            let amount = p.amount;
            if (method?.type === 'cash' && change > 0) {
                const deduct = Math.min(amount, change);
                amount -= deduct;
                change -= deduct;
            }
            return { name: method?.name, type: method?.type, amount };
        }),
        subtotal: t.subtotal,
        discount_amount: t.discountAmount,
        service_charge_amount: t.serviceChargeAmount,
        tax_amount: t.taxAmount,
        total: t.total,
        paid_amount: tendered,
        change_amount: Math.max(0, tendered - t.total),
        due_amount: Math.max(0, t.total - tendered),
        refunded_amount: 0,
        receipt_url: null,
        shift_uuid: payload.shift_uuid,
    };
}

/**
 * Simpan penjualan: masuk antrean di HP, lalu langsung dikirim kalau online.
 * Selalu berhasil walau offline (pembeli tidak boleh menunggu internet).
 *
 * @param {Array<{payment_method_id:number, amount:number}>} payments
 * @param {{payLater?:boolean, dueDate?:string|null, sendWhatsapp?:boolean}} options
 */
export async function submitSale(payments, { payLater = false, dueDate = null, sendWhatsapp = false } = {}) {
    const lines = payingLines.value;
    const isSplit = !!state.payOnlyKeys;
    const kitchenLabel = state.tableId ? `Meja ${tableName(state.tableId)}` : null;
    const useQueue = hasModule('queue') && state.boot.tenant.pos_layout === 'fnb' && !state.tableId;
    const alreadySentToKitchen = lines.every((l) => l.sent_to_kitchen);

    const payload = {
        uuid: uuid(),
        number: nextSaleNumber(state.boot.device?.code ?? 'W'),
        shift_uuid: state.boot.shift.uuid,
        customer_uuid: state.customer?.uuid ?? null,
        order_type: state.orderType,
        note: state.note || null,
        created_at: new Date().toISOString(),
        table_id: state.tableId,
        queue_number: useQueue ? nextQueueNumber() : null,
        pay_later: payLater || undefined,
        due_date: payLater ? dueDate : undefined,
        send_whatsapp: sendWhatsapp || undefined,
        redeem_points: state.redeemPoints && state.customer ? state.redeemPoints : undefined,
        // Pesanan meja yang sudah dikirim ke dapur tidak dikirim lagi.
        send_to_kitchen: !alreadySentToKitchen,
        items: lines.map((l) => ({
            product_id: l.product_id,
            qty: l.qty,
            unit_price: l.unit_price,
            unit_id: l.unit_id || undefined,
            modifiers: l.modifier_ids.length ? l.modifier_ids : undefined,
            staff_id: l.staff_id || undefined,
            discount_amount: l.discount_amount || undefined,
            note: l.note || null,
        })),
        discount_type: state.discount.type && state.discount.value > 0 ? state.discount.type : undefined,
        discount_value: state.discount.type && state.discount.value > 0 ? state.discount.value : undefined,
        payments: payments.map((p) => ({ uuid: uuid(), ...p })),
    };

    const local = buildLocalSale(payload, payments, lines);

    await db.sales.put({ uuid: payload.uuid, shift_uuid: payload.shift_uuid, created_at: Date.now(), synced: 0, local });
    await enqueue('sale', payload);

    // Kurangi stok di layar supaya angka stok tetap masuk akal sampai data dimuat ulang.
    for (const line of lines) {
        const product = productById(line.product_id);
        if (product?.track_stock && product.stock_raw !== null) {
            product.stock_raw = round3(product.stock_raw - Number(line.qty) * (line.conversion || 1));
            product.stock = String(product.stock_raw).replace('.', ',');
        }
    }

    const rule = loyaltyRule();
    if (rule && state.customer) {
        const c = state.boot.customers.find((x) => x.uuid === state.customer.uuid);
        if (c) c.points = Math.max(0, (c.points ?? 0) - (payload.redeem_points ?? 0)) + Math.floor((local.total - local.due_amount) / rule.spend_per_point);
    }

    // Utang pelanggan bertambah (kasbon / bayar nanti).
    if (local.due_amount > 0 && state.customer) {
        const c = state.boot.customers.find((x) => x.uuid === state.customer.uuid);
        if (c) c.balance = (c.balance ?? 0) + local.due_amount;
    }

    // Split bill: sisa pesanan tetap di meja.
    if (isSplit && state.tableId) {
        const tableId = state.tableId;
        const remaining = state.cart.filter((l) => !state.payOnlyKeys.includes(l.key));
        state.cart = remaining;
        state.payOnlyKeys = null;
        if (remaining.length) {
            await saveToTable();
        } else {
            await deleteBill(tableId);
            clearCart();
        }
    } else {
        if (state.tableId) await deleteBill(state.tableId);
        clearCart();
    }
    persistBoot();

    // Selalu panggil syncNow (memperbarui jumlah antrean walau offline).
    // Kalau online, tunggu sebentar supaya dapat link struk dari server.
    const sending = syncNow();
    if (navigator.onLine) {
        await Promise.race([sending, new Promise((r) => setTimeout(r, 5000))]);
        const saved = await db.sales.get(payload.uuid);
        if (saved?.server) {
            return { ...local, ...saved.server, pending: false, kitchenLabel };
        }
    }
    return { ...local, kitchenLabel };
}

// ---------------------------------------------------------------- kasbon (bayar utang di kasir)

export async function payReceivable(customer, amount, paymentMethodId) {
    await enqueue('receivable.payment', {
        uuid: uuid(),
        customer_uuid: customer.uuid,
        amount,
        payment_method_id: paymentMethodId,
        shift_uuid: state.boot.shift?.uuid ?? null,
    });
    const c = state.boot.customers.find((x) => x.uuid === customer.uuid);
    if (c) c.balance = Math.max(0, (c.balance ?? 0) - amount);
    persistBoot();
    syncNow();
}

// ---------------------------------------------------------------- riwayat

/** Transaksi hari ini: dari server (kalau online) + yang belum terkirim dari HP ini. */
export async function todaySales() {
    const start = new Date();
    start.setHours(0, 0, 0, 0);
    const localRows = await db.sales.where('created_at').aboveOrEqual(start.getTime()).toArray().catch(() => []);
    const pending = localRows
        .filter((r) => !r.synced)
        .map((r) => r.local)
        .reverse();

    if (!navigator.onLine) {
        return [...pending, ...localRows.filter((r) => r.synced).map((r) => ({ ...r.local, ...(r.server ?? {}), pending: false })).reverse()];
    }

    try {
        const server = (await api.get(route('pos.api.sales.index'))).data;
        const serverUuids = new Set(server.map((s) => s.uuid));
        return [...pending.filter((s) => !serverUuids.has(s.uuid)), ...server];
    } catch {
        return pending;
    }
}

export async function voidSale(saleUuid, reason, approval = {}) {
    requireOnline('Membatalkan transaksi');
    return api.post(route('pos.api.sales.void', saleUuid), { reason, ...approval });
}

export async function refundSale(saleUuid, payload, approval = {}) {
    requireOnline('Pengembalian barang');
    return api.post(route('pos.api.sales.refund', saleUuid), {
        uuid: uuid(),
        shift_uuid: state.boot.shift?.uuid,
        ...payload,
        ...approval,
    });
}

// ---------------------------------------------------------------- pelanggan

/** Dicari di data yang tersimpan di HP (bisa offline). */
export async function searchCustomers(q) {
    const term = q.trim().toLowerCase();
    const digits = term.replace(/\D/g, '').replace(/^0/, '').replace(/^62/, '');
    const list = state.boot.customers ?? [];
    return list
        .filter((c) => !term || c.name.toLowerCase().includes(term) || (digits.length >= 4 && (c.phone ?? '').includes(digits)))
        .slice(0, 20);
}

export async function createCustomer(data) {
    const digits = String(data.phone ?? '').replace(/\D/g, '');
    const phone = digits ? `62${digits.replace(/^62/, '').replace(/^0/, '')}` : null;
    const customer = { uuid: uuid(), name: data.name.trim(), phone, phone_display: data.phone || '', price_level_id: null, credit_limit: null, balance: 0 };

    await enqueue('customer.create', { uuid: customer.uuid, name: customer.name, phone: customer.phone });
    state.boot.customers = [customer, ...(state.boot.customers ?? [])];
    persistBoot();
    syncNow();
    return customer;
}

export async function markSoldOut(product, soldOut) {
    requireOnline('Menandai barang habis');
    await api.put(route('products.sold-out', product.id), { sold_out: soldOut });
    product.sold_out_today = soldOut;
    persistBoot();
}

export { lineMoney };
