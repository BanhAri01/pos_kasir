<script setup>
/** Riwayat buka / tutup kasir dengan selisih uang yang mudah dilihat. */
import { Head } from '@inertiajs/vue3';
import { Lock } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SalesTabs from '@/Components/SalesTabs.vue';
import { formatRupiah } from '@/composables/useRupiah';

defineOptions({ layout: AppLayout });

defineProps({
    shifts: { type: Array, required: true },
});

const row = 'flex justify-between gap-3 text-lg';
</script>

<template>
    <Head title="Buka / Tutup Kasir" />
    <PageHeader title="Transaksi" />
    <SalesTabs />

    <ul v-if="shifts.length" class="grid gap-3 md:grid-cols-2">
        <li v-for="s in shifts" :key="s.uuid" class="card p-5">
            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                <p class="font-display text-lg font-extrabold text-ink">{{ s.user }}</p>
                <span class="rounded-full px-3 text-base font-bold" :class="s.status === 'open' ? 'bg-primary-soft text-primary-ink' : 'bg-surface-2 text-ink-soft'">
                    {{ s.status === 'open' ? 'Masih buka' : 'Sudah tutup' }}
                </span>
            </div>
            <p class="text-base text-ink-soft">Buka {{ s.opened_at }}<template v-if="s.closed_at"> · Tutup {{ s.closed_at }}</template></p>
            <div class="mt-3 flex flex-col gap-1">
                <p :class="row"><span>Penjualan</span><span class="tabular-nums">{{ formatRupiah(s.sales_total) }}</span></p>
                <p :class="row"><span>Uang awal</span><span class="tabular-nums">{{ formatRupiah(s.opening_cash) }}</span></p>
                <p :class="row"><span>Seharusnya di laci</span><span class="tabular-nums">{{ formatRupiah(s.expected_cash) }}</span></p>
                <p v-if="s.counted_cash !== null" :class="row"><span>Dihitung kasir</span><span class="tabular-nums">{{ formatRupiah(s.counted_cash) }}</span></p>
            </div>
            <p
                v-if="s.cash_difference !== null"
                class="mt-3 rounded-xl px-3 py-2 text-center text-lg font-extrabold"
                :class="s.cash_difference === 0 ? 'bg-primary-soft text-primary-ink' : 'bg-warn-soft text-warn-ink'"
            >
                {{ s.cash_difference === 0 ? 'Pas, tidak ada selisih' : s.cash_difference < 0 ? `Kurang ${formatRupiah(-s.cash_difference)}` : `Lebih ${formatRupiah(s.cash_difference)}` }}
            </p>
            <p v-if="s.closing_note" class="mt-2 text-base text-ink-soft">Catatan: {{ s.closing_note }}</p>
        </li>
    </ul>
    <EmptyState v-else title="Belum ada riwayat buka kasir">
        <template #icon><Lock :size="48" /></template>
    </EmptyState>
</template>
