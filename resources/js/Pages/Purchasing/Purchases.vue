<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ChevronRight, Plus, ShoppingBasket, Truck } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import MoneyDisplay from '@/Components/ui/MoneyDisplay.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { formatRupiah } from '@/composables/useRupiah';

defineOptions({ layout: AppLayout });

defineProps({
    purchases: { type: Object, required: true },
    totalDebt: { type: Number, required: true },
    filter: { type: String, default: '' },
});

const STATUS = { paid: ['Lunas', 'bg-primary-soft text-primary-ink'], partial: ['Dicicil', 'bg-accent-soft text-accent-ink'], unpaid: ['Belum bayar', 'bg-danger-soft text-danger-ink'] };
</script>

<template>
    <Head title="Belanja ke Pemasok" />
    <PageHeader title="Belanja ke Pemasok" subtitle="Barang yang dibeli otomatis menambah stok.">
        <template #action>
            <div class="flex flex-wrap gap-2">
                <BigButton variant="secondary" :href="route('suppliers.index')"><Truck :size="22" aria-hidden="true" /> Pemasok</BigButton>
                <BigButton :href="route('purchases.create')"><Plus :size="24" aria-hidden="true" /> Catat Belanja</BigButton>
            </div>
        </template>
    </PageHeader>

    <div class="mb-5 grid gap-3 sm:grid-cols-2">
        <div class="card p-4"><MoneyDisplay :amount="totalDebt" size="lg" :tone="totalDebt ? 'negative' : 'default'" label="Utang ke pemasok" /></div>
        <div class="flex gap-2 sm:items-center">
            <Link :href="route('purchases.index')" class="pressable flex min-h-touch flex-1 items-center justify-center rounded-2xl px-4 text-lg font-bold" :class="!filter ? 'bg-primary text-on-primary' : 'bg-surface-2 text-ink'">Semua</Link>
            <Link :href="route('purchases.index', { status: 'utang' })" class="pressable flex min-h-touch flex-1 items-center justify-center rounded-2xl px-4 text-lg font-bold" :class="filter === 'utang' ? 'bg-primary text-on-primary' : 'bg-surface-2 text-ink'">Belum lunas</Link>
        </div>
    </div>

    <EmptyState v-if="!purchases.data.length" title="Belum ada belanja" message="Catat setiap kali beli barang dari pemasok supaya stok & modal selalu benar.">
        <template #icon><ShoppingBasket :size="48" aria-hidden="true" /></template>
        <BigButton :href="route('purchases.create')">Catat Belanja Pertama</BigButton>
    </EmptyState>

    <ul v-else class="card divide-y divide-line overflow-hidden">
        <li v-for="p in purchases.data" :key="p.uuid">
            <Link :href="route('purchases.show', p.uuid)" class="flex min-h-touch-lg items-center gap-3 px-4 py-3 hover:bg-surface-2">
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-lg font-extrabold text-ink">{{ p.supplier }}</span>
                    <span class="block text-base text-ink-soft">{{ p.date }} · {{ p.number }}<template v-if="p.due_date && p.payment_status !== 'paid'"> · tempo {{ p.due_date }}</template></span>
                </span>
                <span class="text-right">
                    <span class="block font-display text-lg font-extrabold text-ink tabular-nums">{{ formatRupiah(p.total) }}</span>
                    <span class="inline-block rounded-full px-2 py-0.5 text-sm font-bold" :class="p.overdue ? 'bg-danger text-white' : STATUS[p.payment_status][1]">{{ p.overdue ? 'Lewat tempo' : STATUS[p.payment_status][0] }}</span>
                </span>
                <ChevronRight :size="24" class="shrink-0 text-ink-soft" aria-hidden="true" />
            </Link>
        </li>
    </ul>

    <div v-if="purchases.last_page > 1" class="mt-4 flex justify-between gap-3">
        <BigButton v-if="purchases.prev_page_url" variant="secondary" :href="purchases.prev_page_url">Sebelumnya</BigButton>
        <span v-else />
        <BigButton v-if="purchases.next_page_url" variant="secondary" :href="purchases.next_page_url">Berikutnya</BigButton>
    </div>
</template>
