<script setup>
/**
 * Tutup kasir: aplikasi menghitung uang yang SEHARUSNYA ada di laci,
 * kasir menghitung uang yang BENAR-BENAR ada, lalu selisihnya dicatat.
 */
import { computed, ref, watch } from 'vue';
import { Lock } from 'lucide-vue-next';
import BigButton from '@/Components/ui/BigButton.vue';
import BottomSheet from '@/Components/ui/BottomSheet.vue';
import MoneyDisplay from '@/Components/ui/MoneyDisplay.vue';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import { formatRupiah } from '@/composables/useRupiah';
import { closeShift, shiftSummary } from '../store';
import { showToast } from '../lib/toast';

const open = defineModel('open', { type: Boolean, default: false });

const summary = ref(null);
const counted = ref(null);
const note = ref('');
const saving = ref(false);

const difference = computed(() => (summary.value && counted.value !== null ? counted.value - summary.value.expected_cash : null));

watch(open, async (v) => {
    if (!v) return;
    summary.value = null;
    counted.value = null;
    note.value = '';
    try {
        summary.value = await shiftSummary();
    } catch (e) {
        showToast(e.message, 'error');
        open.value = false;
    }
});

async function close() {
    if (counted.value === null) return showToast('Hitung uang di laci, lalu isi jumlahnya.', 'error');
    saving.value = true;
    try {
        const res = await closeShift(counted.value, note.value || null);
        showToast(res.message, res.data.cash_difference === 0 ? 'success' : 'info');
        open.value = false;
    } catch (e) {
        showToast(e.message, 'error');
    } finally {
        saving.value = false;
    }
}

const row = 'flex justify-between gap-3 text-lg';
</script>

<template>
    <BottomSheet v-model:open="open" title="Tutup Kasir">
        <p v-if="!summary" class="py-8 text-center text-lg text-ink-soft">Menghitung...</p>
        <div v-else class="flex flex-col gap-5">
            <div class="rounded-2xl bg-surface-2 p-4">
                <p :class="row"><span>Jumlah transaksi</span><strong>{{ summary.sales_count }}</strong></p>
                <p :class="row"><span>Total penjualan</span><strong class="tabular-nums">{{ formatRupiah(summary.sales_total) }}</strong></p>
                <p v-for="m in summary.by_method" :key="m.name" :class="row" class="text-ink-soft">
                    <span>· {{ m.name }}</span><span class="tabular-nums">{{ formatRupiah(m.total) }}</span>
                </p>
            </div>

            <div class="rounded-2xl border-2 border-line p-4">
                <p class="mb-2 text-lg font-bold text-ink">Uang tunai di laci</p>
                <p :class="row"><span>Uang awal</span><span class="tabular-nums">{{ formatRupiah(summary.opening_cash) }}</span></p>
                <p :class="row"><span>+ Penjualan tunai</span><span class="tabular-nums">{{ formatRupiah(summary.cash_sales) }}</span></p>
                <p v-if="summary.cash_in" :class="row"><span>+ Uang masuk</span><span class="tabular-nums">{{ formatRupiah(summary.cash_in) }}</span></p>
                <p v-if="summary.cash_out" :class="row"><span>− Uang keluar</span><span class="tabular-nums">{{ formatRupiah(summary.cash_out) }}</span></p>
                <p v-if="summary.cash_refunds" :class="row"><span>− Pengembalian</span><span class="tabular-nums">{{ formatRupiah(summary.cash_refunds) }}</span></p>
                <div class="mt-2 border-t border-line pt-2">
                    <MoneyDisplay :amount="summary.expected_cash" size="lg" label="Seharusnya ada" />
                </div>
            </div>

            <MoneyInput v-model="counted" label="Uang yang benar-benar ada di laci" hint="Hitung semua uang tunai di laci, lalu isi jumlahnya." />

            <div v-if="difference !== null" class="rounded-2xl p-4 text-center text-xl font-extrabold" :class="difference === 0 ? 'bg-primary-soft text-primary-ink' : 'bg-warn-soft text-warn-ink'">
                {{ difference === 0 ? 'Pas! Tidak ada selisih.' : difference < 0 ? `Kurang ${formatRupiah(-difference)}` : `Lebih ${formatRupiah(difference)}` }}
            </div>

            <input
                v-if="difference !== null && difference !== 0"
                v-model="note"
                type="text"
                aria-label="Catatan selisih"
                placeholder="Catatan (misal: uang dipakai beli galon)"
                class="min-h-touch w-full rounded-2xl border-2 border-line bg-surface px-4 text-lg text-ink focus:border-focus focus:outline-none"
            />

            <BigButton size="large" block :loading="saving" @click="close">
                <Lock :size="24" aria-hidden="true" /> Tutup Kasir
            </BigButton>
        </div>
    </BottomSheet>
</template>
