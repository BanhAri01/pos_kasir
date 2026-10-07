<script setup>
/** Daftar transaksi per hari, dengan ringkasan total. */
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, ReceiptText, Search } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import MoneyDisplay from '@/Components/ui/MoneyDisplay.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SalesTabs from '@/Components/SalesTabs.vue';
import { formatRupiah } from '@/composables/useRupiah';

defineOptions({ layout: AppLayout });

const props = defineProps({
    sales: { type: Array, required: true },
    pagination: { type: Object, required: true },
    summary: { type: Object, required: true },
    filters: { type: Object, required: true },
});

const search = ref(props.filters.q);
let timer = null;

function apply(changes) {
    router.get(route('sales.index'), { ...props.filters, ...changes, page: undefined }, { preserveState: true, replace: true });
}

watch(search, (v) => {
    clearTimeout(timer);
    timer = setTimeout(() => apply({ q: v || undefined }), 350);
});

function shiftDay(days) {
    const d = new Date(`${props.filters.tanggal}T00:00:00`);
    d.setDate(d.getDate() + days);
    apply({ tanggal: d.toISOString().slice(0, 10) });
}

const today = new Date().toISOString().slice(0, 10);
const statusLabel = { void: 'Dibatalkan', refunded: 'Dikembalikan', partially_refunded: 'Dikembalikan sebagian' };
</script>

<template>
    <Head title="Transaksi" />
    <PageHeader title="Transaksi" help="sales" />
    <SalesTabs />

    <!-- Pilih tanggal -->
    <div class="card mb-4 flex items-center gap-2 p-2">
        <button type="button" class="pressable flex size-14 items-center justify-center rounded-xl hover:bg-surface-2" aria-label="Hari sebelumnya" @click="shiftDay(-1)">
            <ChevronLeft :size="28" aria-hidden="true" />
        </button>
        <label class="flex-1 text-center">
            <span class="sr-only">Tanggal</span>
            <input
                type="date"
                :value="filters.tanggal"
                :max="today"
                class="min-h-14 w-full rounded-xl bg-transparent text-center text-lg font-bold text-ink focus:outline-none"
                @change="apply({ tanggal: $event.target.value })"
            />
        </label>
        <button
            type="button"
            class="pressable flex size-14 items-center justify-center rounded-xl hover:bg-surface-2 disabled:opacity-30"
            aria-label="Hari berikutnya"
            :disabled="filters.tanggal >= today"
            @click="shiftDay(1)"
        >
            <ChevronRight :size="28" aria-hidden="true" />
        </button>
    </div>

    <div class="mb-4 grid grid-cols-2 gap-3">
        <div class="card p-4">
            <p class="text-lg text-ink-soft">Jumlah transaksi</p>
            <p class="font-display text-3xl font-extrabold text-ink">{{ summary.count }}</p>
        </div>
        <div class="card p-4">
            <MoneyDisplay :amount="summary.total" size="lg" tone="positive" label="Total penjualan" />
        </div>
    </div>

    <label class="relative mb-4 block">
        <span class="sr-only">Cari nomor nota</span>
        <Search :size="24" class="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-ink-soft" aria-hidden="true" />
        <input
            v-model="search"
            type="search"
            placeholder="Cari nomor nota"
            class="min-h-touch w-full rounded-2xl border-2 border-line bg-surface pr-4 pl-13 text-lg text-ink focus:border-focus focus:outline-none"
        />
    </label>

    <ul v-if="sales.length" class="card divide-y divide-line overflow-hidden">
        <li v-for="s in sales" :key="s.uuid">
            <Link :href="route('sales.show', s.uuid)" class="flex min-h-touch-lg items-center gap-3 px-4 py-3 hover:bg-surface-2">
                <span class="w-14 shrink-0 font-display text-lg font-extrabold text-ink tabular-nums">{{ s.time }}</span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-lg font-bold text-ink">{{ s.items.map((i) => i.name).join(', ') }}</span>
                    <span class="block text-base text-ink-soft">
                        {{ s.number }} · {{ s.cashier }}<template v-if="s.customer"> · {{ s.customer.name }}</template>
                    </span>
                    <span v-if="statusLabel[s.status === 'void' ? 'void' : s.payment_status]" class="mt-1 inline-block rounded-full bg-danger-soft px-2.5 text-base font-bold text-danger-ink">
                        {{ statusLabel[s.status === 'void' ? 'void' : s.payment_status] }}
                    </span>
                </span>
                <span class="font-display text-lg font-extrabold text-ink tabular-nums" :class="s.status === 'void' ? 'line-through opacity-50' : ''">{{ formatRupiah(s.total) }}</span>
            </Link>
        </li>
    </ul>
    <EmptyState v-else title="Tidak ada transaksi di tanggal ini">
        <template #icon><ReceiptText :size="48" /></template>
    </EmptyState>

    <div v-if="pagination.last > 1" class="mt-6 flex items-center justify-between gap-3">
        <BigButton variant="secondary" :disabled="pagination.current <= 1" @click="router.get(route('sales.index'), { ...filters, page: pagination.current - 1 })">Sebelumnya</BigButton>
        <span class="text-lg font-bold text-ink-soft">{{ pagination.current }} / {{ pagination.last }}</span>
        <BigButton variant="secondary" :disabled="pagination.current >= pagination.last" @click="router.get(route('sales.index'), { ...filters, page: pagination.current + 1 })">Berikutnya</BigButton>
    </div>
</template>
