<script setup>
/**
 * Laporan gudang (gudang yang sedang dipilih):
 *  - Susut: barang berkurang karena kadar air, hama, rusak, tumpah, atau kurang saat hitung stok.
 *  - Selisih timbang: berat nota pemasok vs berat timbangan asli.
 *  - Hitung stok: hasil hitung di gudang vs catatan aplikasi.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import MoneyDisplay from '@/Components/ui/MoneyDisplay.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import { formatRupiah } from '@/composables/useRupiah';

defineOptions({ layout: AppLayout });

const props = defineProps({
    tabs: { type: Array, required: true },
    tab: { type: String, required: true },
    period: { type: String, required: true },
    periods: { type: Array, required: true },
    shrinkage: { type: Object, default: null },
    weighing: { type: Object, default: null },
    opnames: { type: Array, default: null },
});

const tabLabels = { susut: 'Susut', timbang: 'Selisih Timbang', hitung: 'Hitung Stok' };

function go(params) {
    router.get(route('warehouse.reports'), { tab: props.tab, period: props.period, ...params }, { preserveScroll: true, replace: true });
}

const signed = (value) => (String(value).startsWith('-') ? value : `+${value}`);
</script>

<template>
    <Head title="Laporan Gudang" />
    <div class="mx-auto max-w-4xl">
        <PageHeader title="Laporan Gudang" subtitle="Untuk gudang yang sedang dipilih." :back-href="route('stock.index')" />

        <div class="mb-5 flex flex-col gap-3">
            <SegmentedControl v-if="tabs.length > 1" :model-value="tab" label="Jenis laporan" :options="tabs.map((t) => ({ value: t, label: tabLabels[t] }))" @update:model-value="(t) => go({ tab: t })" />
            <SegmentedControl :model-value="period" label="Periode" :options="periods" @update:model-value="(p) => go({ period: p })" />
        </div>

        <!-- Susut -->
        <template v-if="tab === 'susut' && shrinkage">
            <div class="card mb-5 p-5">
                <MoneyDisplay :amount="shrinkage.total_value" size="xl" label="Nilai susut (modal)" />
            </div>
            <EmptyState v-if="!shrinkage.products.length" title="Tidak ada susut" message="Belum ada stok keluar karena susut di periode ini." />
            <template v-else>
                <h2 class="mb-3 text-xl font-extrabold text-ink">Penyebab</h2>
                <ul class="card mb-6 divide-y divide-line">
                    <li v-for="r in shrinkage.reasons" :key="r.label" class="flex min-h-touch items-center justify-between gap-3 px-4 py-3 text-lg">
                        <span class="text-ink">{{ r.label }}</span>
                        <span class="font-bold text-danger-ink tabular-nums">{{ formatRupiah(r.value) }}</span>
                    </li>
                </ul>
                <h2 class="mb-3 text-xl font-extrabold text-ink">Per barang</h2>
                <ul class="card divide-y divide-line">
                    <li v-for="p in shrinkage.products" :key="p.name" class="flex min-h-touch items-center justify-between gap-3 px-4 py-3">
                        <span class="min-w-0">
                            <span class="block truncate text-lg font-bold text-ink">{{ p.name }}</span>
                            <span class="block text-base text-ink-soft">berkurang {{ p.qty }} {{ p.unit }}</span>
                        </span>
                        <span class="font-bold text-danger-ink tabular-nums">{{ formatRupiah(p.value) }}</span>
                    </li>
                </ul>
            </template>
        </template>

        <!-- Selisih timbang pemasok -->
        <template v-if="tab === 'timbang' && weighing">
            <div class="card mb-5 p-5">
                <MoneyDisplay :amount="weighing.total_value" size="xl" label="Nilai selisih timbang" />
                <p class="mt-1 text-base text-ink-soft">Minus = barang yang diterima lebih ringan dari nota.</p>
            </div>
            <EmptyState v-if="!weighing.suppliers.length" title="Belum ada barang ditimbang" message="Pakai timbangan saat Terima Barang supaya selisihnya tercatat di sini." />
            <template v-else>
                <h2 class="mb-3 text-xl font-extrabold text-ink">Per pemasok</h2>
                <ul class="mb-6 grid gap-3 md:grid-cols-2">
                    <li v-for="s in weighing.suppliers" :key="s.name" class="card p-4">
                        <p class="text-xl font-extrabold text-ink">{{ s.name }}</p>
                        <p class="text-base text-ink-soft">{{ s.deliveries }} kali kirim · nota {{ s.billed }} · timbangan {{ s.received }}</p>
                        <p class="mt-1 text-lg font-bold" :class="s.short ? 'text-danger-ink' : 'text-primary-ink'">
                            Selisih {{ signed(s.diff) }} ({{ formatRupiah(s.value) }})
                        </p>
                    </li>
                </ul>
                <template v-if="weighing.items.length">
                    <h2 class="mb-3 text-xl font-extrabold text-ink">Kiriman yang selisih</h2>
                    <ul class="card divide-y divide-line">
                        <li v-for="(i, idx) in weighing.items" :key="idx">
                            <Link :href="route('purchases.show', i.purchase_uuid)" class="flex min-h-touch items-center justify-between gap-3 px-4 py-3 hover:bg-surface-2">
                                <span class="min-w-0">
                                    <span class="block truncate text-lg font-bold text-ink">{{ i.product }}</span>
                                    <span class="block text-base text-ink-soft">{{ i.date }} · {{ i.supplier }} · nota {{ i.billed }}, timbangan {{ i.received }} {{ i.unit }}</span>
                                </span>
                                <span class="shrink-0 font-bold tabular-nums" :class="i.short ? 'text-danger-ink' : 'text-primary-ink'">{{ signed(i.diff) }} {{ i.unit }}</span>
                            </Link>
                        </li>
                    </ul>
                </template>
            </template>
        </template>

        <!-- Selisih hitung stok -->
        <template v-if="tab === 'hitung' && opnames">
            <EmptyState v-if="!opnames.length" title="Belum ada hitung stok" message="Hitung stok di gudang (timbang ulang), hasilnya muncul di sini." />
            <div v-for="o in opnames" :key="o.number" class="card mb-4 overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-4 py-3">
                    <span>
                        <span class="block text-lg font-extrabold text-ink">{{ o.number }}</span>
                        <span class="block text-base text-ink-soft">{{ o.date }}<template v-if="o.user"> · {{ o.user }}</template></span>
                    </span>
                    <span class="font-bold tabular-nums" :class="o.value < 0 ? 'text-danger-ink' : 'text-ink'">{{ formatRupiah(o.value) }}</span>
                </div>
                <p v-if="!o.items.length" class="px-4 py-3 text-lg text-ink-soft">Semua cocok, tidak ada selisih.</p>
                <ul v-else class="divide-y divide-line">
                    <li v-for="(i, idx) in o.items" :key="idx" class="flex min-h-touch items-center justify-between gap-3 px-4 py-3">
                        <span class="min-w-0">
                            <span class="block truncate text-lg font-bold text-ink">{{ i.product }}</span>
                            <span class="block text-base text-ink-soft">catatan {{ i.system }} · dihitung {{ i.counted }} {{ i.unit }}</span>
                        </span>
                        <span class="shrink-0 text-right">
                            <span class="block font-bold tabular-nums" :class="i.short ? 'text-danger-ink' : 'text-primary-ink'">{{ signed(i.diff) }} {{ i.unit }}</span>
                            <span class="block text-base text-ink-soft tabular-nums">{{ formatRupiah(i.value) }}</span>
                        </span>
                    </li>
                </ul>
            </div>
        </template>
    </div>
</template>
