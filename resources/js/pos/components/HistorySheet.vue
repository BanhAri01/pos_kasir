<script setup>
/**
 * Transaksi hari ini: lihat detail, cetak ulang, kirim WhatsApp, batalkan (void),
 * atau kembalikan barang (refund). Batal & kembali butuh izin atau PIN atasan.
 */
import { computed, ref, watch } from 'vue';
import { ChevronLeft, MessageCircle, Printer, RotateCcw, XCircle } from 'lucide-vue-next';
import BigButton from '@/Components/ui/BigButton.vue';
import BottomSheet from '@/Components/ui/BottomSheet.vue';
import QtyInput from '@/Components/ui/QtyInput.vue';
import ToggleSwitch from '@/Components/ui/ToggleSwitch.vue';
import { formatRupiah } from '@/composables/useRupiah';
import ApprovalSheet from './ApprovalSheet.vue';
import { can, refundSale, store, todaySales, voidSale } from '../store';
import { loadPrinterSettings, printReceipt } from '../lib/printer';
import { showToast } from '../lib/toast';
import { whatsappReceiptUrl } from '../lib/whatsapp';

const open = defineModel('open', { type: Boolean, default: false });

const sales = ref([]);
const loading = ref(false);
const selected = ref(null);
const mode = ref('detail'); // detail | void | refund
const reason = ref('');
const refundQty = ref({});
const restock = ref(true);
const approvalOpen = ref(false);
const approvalError = ref('');
const busy = ref(false);
let pendingAction = null;

const statusLabel = { void: 'Dibatalkan', refunded: 'Dikembalikan', partially_refunded: 'Dikembalikan sebagian' };

watch(open, async (v) => {
    if (!v) return;
    selected.value = null;
    await load();
});

async function load() {
    loading.value = true;
    try {
        sales.value = await todaySales();
    } catch (e) {
        showToast(e.message, 'error');
    } finally {
        loading.value = false;
    }
}

function select(sale) {
    selected.value = sale;
    mode.value = 'detail';
    reason.value = '';
    restock.value = true;
    refundQty.value = Object.fromEntries(sale.items.map((i) => [i.id, '']));
}

async function print(sale) {
    try {
        showToast(await printReceipt(sale, store.boot, loadPrinterSettings()), 'info');
    } catch (e) {
        showToast(e.message, 'error');
    }
}

function sendWhatsapp(sale) {
    window.open(whatsappReceiptUrl(sale, store.boot, sale.customer?.phone), '_blank', 'noopener');
}

const refundItems = computed(() =>
    Object.entries(refundQty.value)
        .map(([id, q]) => ({ sale_item_id: Number(id), qty: parseFloat(String(q).replace(',', '.')) || 0 }))
        .filter((i) => i.qty > 0),
);

/** Jalankan aksi; kalau server minta persetujuan, buka layar PIN atasan lalu ulangi. */
async function run(action, permission, approval = {}) {
    if (!reason.value.trim()) {
        showToast('Tulis alasannya dulu.', 'error');
        return;
    }
    busy.value = true;
    try {
        const res = await action(approval);
        approvalOpen.value = false;
        showToast(res.message);
        await load();
        selected.value = null;
    } catch (e) {
        if (e.errors?.approval) {
            approvalError.value = Object.keys(approval).length ? e.message : '';
            pendingAction = { action, permission };
            approvalOpen.value = true;
        } else {
            showToast(e.message, 'error');
        }
    } finally {
        busy.value = false;
    }
}

const doVoid = () => run((approval) => voidSale(selected.value.uuid, reason.value, approval), 'void_sale');
const doRefund = () => {
    if (!refundItems.value.length) {
        showToast('Isi jumlah barang yang dikembalikan.', 'error');
        return;
    }
    run((approval) => refundSale(selected.value.uuid, { reason: reason.value, restock: restock.value, items: refundItems.value }, approval), 'refund_sale');
};

function onApprove(approval) {
    if (pendingAction) run(pendingAction.action, pendingAction.permission, approval);
}
</script>

<template>
    <BottomSheet v-model:open="open" :title="selected ? `Nota ${selected.number}` : 'Transaksi Hari Ini'">
        <!-- Daftar -->
        <div v-if="!selected">
            <p v-if="loading" class="py-8 text-center text-lg text-ink-soft">Memuat...</p>
            <p v-else-if="!sales.length" class="py-8 text-center text-lg text-ink-soft">Belum ada transaksi hari ini.</p>
            <ul v-else class="flex flex-col gap-2">
                <li v-for="s in sales" :key="s.uuid">
                    <button type="button" class="pressable flex w-full items-center gap-3 rounded-2xl border-2 border-line p-3 text-left hover:border-primary" @click="select(s)">
                        <span class="min-w-0 flex-1">
                            <span class="block text-lg font-bold text-ink">{{ s.time }} · {{ s.number }}</span>
                            <span class="block truncate text-base text-ink-soft">{{ s.items.map((i) => i.name).join(', ') }}</span>
                            <span v-if="s.pending" class="mt-1 mr-1 inline-block rounded-full bg-warn-soft px-2.5 text-base font-bold text-warn-ink">Belum terkirim</span>
                            <span v-if="statusLabel[s.status === 'void' ? 'void' : s.payment_status]" class="mt-1 inline-block rounded-full bg-danger-soft px-2.5 text-base font-bold text-danger-ink">
                                {{ statusLabel[s.status === 'void' ? 'void' : s.payment_status] }}
                            </span>
                        </span>
                        <span class="font-display text-lg font-extrabold text-ink tabular-nums" :class="s.status === 'void' ? 'line-through opacity-60' : ''">{{ formatRupiah(s.total) }}</span>
                    </button>
                </li>
            </ul>
        </div>

        <!-- Detail -->
        <div v-else class="flex flex-col gap-4">
            <button type="button" class="-ml-2 inline-flex min-h-12 items-center gap-1 self-start rounded-xl px-2 text-lg font-bold text-ink-soft hover:bg-ink/5" @click="selected = null">
                <ChevronLeft :size="24" aria-hidden="true" /> Semua transaksi
            </button>

            <div class="rounded-2xl bg-surface-2 p-4">
                <p class="text-base text-ink-soft">{{ selected.date }} {{ selected.time }} · Kasir {{ selected.cashier }}</p>
                <ul class="mt-2 flex flex-col gap-1">
                    <li v-for="item in selected.items" :key="item.id" class="flex justify-between gap-2 text-lg">
                        <span>{{ item.qty }} × {{ item.name }}<span v-if="item.refunded_qty" class="text-base text-danger-ink"> ({{ item.refunded_qty }} dikembalikan)</span></span>
                        <span class="tabular-nums">{{ formatRupiah(item.subtotal) }}</span>
                    </li>
                </ul>
                <p class="mt-2 flex justify-between border-t border-line pt-2 text-xl font-extrabold"><span>Total</span><span class="tabular-nums">{{ formatRupiah(selected.total) }}</span></p>
                <p v-if="selected.status === 'void'" class="mt-2 font-bold text-danger-ink">Dibatalkan: {{ selected.void_reason }}</p>
            </div>

            <template v-if="mode === 'detail'">
                <div class="grid gap-2 sm:grid-cols-2">
                    <BigButton variant="secondary" @click="print(selected)"><Printer :size="22" aria-hidden="true" /> Cetak Ulang</BigButton>
                    <BigButton variant="soft" @click="sendWhatsapp(selected)">
                        <MessageCircle :size="22" aria-hidden="true" /> Kirim WhatsApp
                    </BigButton>
                </div>
                <p v-if="selected.pending" class="rounded-2xl bg-warn-soft p-4 text-lg text-warn-ink">
                    Transaksi ini belum terkirim ke server. Batal & pengembalian bisa dilakukan setelah terkirim.
                </p>
                <div v-else-if="selected.status !== 'void'" class="grid gap-2 sm:grid-cols-2">
                    <BigButton v-if="!selected.refunded_amount" variant="ghost" class="text-danger-ink" @click="mode = 'void'">
                        <XCircle :size="22" aria-hidden="true" /> Batalkan Transaksi
                    </BigButton>
                    <BigButton v-if="selected.payment_status !== 'refunded'" variant="ghost" @click="mode = 'refund'">
                        <RotateCcw :size="22" aria-hidden="true" /> Kembalikan Barang
                    </BigButton>
                </div>
            </template>

            <!-- Batalkan -->
            <template v-else-if="mode === 'void'">
                <p class="rounded-2xl bg-danger-soft p-4 text-lg text-danger-ink">
                    Seluruh transaksi dibatalkan dan stok dikembalikan.
                    <template v-if="!can('void_sale')"> Butuh PIN manajer atau pemilik.</template>
                </p>
                <label class="block">
                    <span class="mb-2 block text-lg font-bold text-ink">Alasan</span>
                    <input v-model="reason" type="text" placeholder="Contoh: salah input pesanan" class="min-h-touch w-full rounded-2xl border-2 border-line bg-surface px-4 text-lg text-ink focus:border-focus focus:outline-none" />
                </label>
                <BigButton variant="danger" block :loading="busy" @click="doVoid">Ya, Batalkan Transaksi</BigButton>
                <BigButton variant="secondary" block @click="mode = 'detail'">Tidak Jadi</BigButton>
            </template>

            <!-- Kembalikan barang -->
            <template v-else>
                <p class="text-lg text-ink-soft">Isi jumlah barang yang dikembalikan pembeli.</p>
                <div v-for="item in selected.items.filter((i) => i.refundable_qty > 0)" :key="item.id" class="rounded-2xl border-2 border-line p-3">
                    <p class="mb-2 text-lg font-bold text-ink">{{ item.name }} <span class="font-normal text-ink-soft">(dibeli {{ item.qty }})</span></p>
                    <QtyInput v-model="refundQty[item.id]" :label="`Jumlah ${item.name} dikembalikan`" compact :unit="item.unit" />
                </div>
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                    <span class="min-w-48 flex-1 text-lg text-ink">Barang dikembalikan ke stok</span>
                    <ToggleSwitch v-model="restock" label="Kembalikan ke stok" class="ml-auto" />
                </div>
                <label class="block">
                    <span class="mb-2 block text-lg font-bold text-ink">Alasan</span>
                    <input v-model="reason" type="text" placeholder="Contoh: barang rusak" class="min-h-touch w-full rounded-2xl border-2 border-line bg-surface px-4 text-lg text-ink focus:border-focus focus:outline-none" />
                </label>
                <BigButton block :loading="busy" @click="doRefund">Simpan Pengembalian</BigButton>
                <BigButton variant="secondary" block @click="mode = 'detail'">Tidak Jadi</BigButton>
            </template>
        </div>
    </BottomSheet>

    <ApprovalSheet v-model:open="approvalOpen" :error="approvalError" @approve="onApprove" />
</template>
