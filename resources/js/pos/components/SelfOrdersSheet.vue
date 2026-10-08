<script setup>
import { ref } from 'vue';
import { BadgeCheck, Check, X } from 'lucide-vue-next';
import BottomSheet from '@/Components/ui/BottomSheet.vue';
import { formatRupiah } from '@/composables/useRupiah';
import { acceptSelfOrder, rejectSelfOrder, selfOrders } from '../selfOrders';
import { showToast } from '../lib/toast';

const open = defineModel('open', { type: Boolean, default: false });
const rejecting = ref(null);
const reason = ref('');
const QUICK_REASONS = ['Menu habis', 'Dapur sudah tutup', 'Pesanan dobel'];

async function accept(order) {
    try {
        showToast(await acceptSelfOrder(order));
        if (!selfOrders.list.length) open.value = false;
    } catch (e) {
        showToast(e.message, 'error');
    }
}

async function reject() {
    if (!reason.value.trim()) return;
    try {
        showToast(await rejectSelfOrder(rejecting.value, reason.value.trim()), 'info');
        rejecting.value = null;
        reason.value = '';
    } catch (e) {
        showToast(e.message, 'error');
    }
}
</script>

<template>
    <BottomSheet v-model:open="open" title="Pesanan Masuk (QR & Online)">
        <p v-if="!selfOrders.list.length" class="py-8 text-center text-lg text-ink-soft">Belum ada pesanan baru dari meja atau toko online.</p>
        <ul v-else class="flex flex-col gap-3">
            <li v-for="o in selfOrders.list" :key="o.uuid" class="rounded-2xl border-2 border-line p-4">
                <div class="mb-2 flex flex-wrap items-center gap-2">
                    <span class="rounded-xl bg-brand px-3 py-1 font-display text-lg font-extrabold text-accent">{{ o.table ?? (o.order_type === 'delivery' ? 'Diantar' : 'Ambil sendiri') }}</span>
                    <span class="text-lg font-bold text-ink">{{ o.customer_name }}</span>
                    <span class="text-base text-ink-soft">{{ o.code }} · {{ o.time }}</span>
                    <span v-if="o.paid" class="ml-auto flex items-center gap-1 rounded-full bg-primary-soft px-3 py-1 text-base font-bold text-primary-ink"><BadgeCheck :size="18" aria-hidden="true" /> Lunas QRIS</span>
                    <span v-else class="ml-auto rounded-full bg-accent-soft px-3 py-1 text-base font-bold text-accent-ink">Bayar di kasir</span>
                </div>
                <ul class="mb-2 text-lg text-ink">
                    <li v-for="(l, i) in o.items" :key="i">
                        <b>{{ l.qty }}×</b> {{ l.name }}
                        <span v-if="l.modifiers.length" class="text-base text-ink-soft">({{ l.modifiers.join(', ') }})</span>
                        <span v-if="l.note" class="block pl-6 text-base text-ink-soft">Catatan: {{ l.note }}</span>
                    </li>
                </ul>
                <p v-if="o.customer_phone || o.address" class="mb-2 rounded-xl bg-info-soft px-3 py-2 text-base text-info-ink">{{ [o.customer_phone, o.address].filter(Boolean).join(' · ') }}<template v-if="o.delivery_fee"> · ongkir {{ formatRupiah(o.delivery_fee) }}</template></p>
                <p v-if="o.note" class="mb-2 rounded-xl bg-surface-2 px-3 py-2 text-base text-ink">Catatan: {{ o.note }}</p>
                <p class="mb-3 font-display text-xl font-extrabold text-ink">{{ formatRupiah(o.total) }}</p>

                <div v-if="rejecting?.uuid === o.uuid" class="flex flex-col gap-2">
                    <div class="flex flex-wrap gap-2">
                        <button v-for="r in QUICK_REASONS" :key="r" type="button" class="pressable min-h-12 rounded-2xl border-2 px-3 text-base font-bold" :class="reason === r ? 'border-primary bg-primary-soft text-primary-ink' : 'border-line text-ink'" @click="reason = r">{{ r }}</button>
                    </div>
                    <input v-model="reason" type="text" maxlength="200" placeholder="Alasan lain" class="min-h-touch rounded-2xl border-2 border-line bg-surface px-4 text-lg text-ink" />
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" class="pressable min-h-touch rounded-2xl border-2 border-line text-lg font-bold text-ink" @click="rejecting = null">Tidak Jadi</button>
                        <button type="button" class="pressable min-h-touch rounded-2xl bg-danger text-lg font-bold text-white disabled:opacity-50" :disabled="!reason.trim() || selfOrders.busy === o.uuid" @click="reject">Ya, Tolak</button>
                    </div>
                </div>
                <div v-else class="grid grid-cols-[auto_1fr] gap-2">
                    <button type="button" class="pressable flex min-h-touch items-center gap-1 rounded-2xl border-2 border-line px-4 text-lg font-bold text-danger-ink" @click="rejecting = o; reason = ''">
                        <X :size="22" aria-hidden="true" /> Tolak
                    </button>
                    <button type="button" class="pressable flex min-h-touch items-center justify-center gap-2 rounded-2xl bg-primary text-lg font-bold text-on-primary disabled:opacity-50" :disabled="selfOrders.busy === o.uuid" @click="accept(o)">
                        <Check :size="22" aria-hidden="true" /> {{ o.paid ? 'Terima & Kirim ke Dapur' : o.table ? 'Terima ke Meja' : 'Terima ke Keranjang' }}
                    </button>
                </div>
            </li>
        </ul>
    </BottomSheet>
</template>
