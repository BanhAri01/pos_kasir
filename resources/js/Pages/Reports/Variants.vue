<script setup>
/** Laporan varian: terlaris, penjualan per ukuran & warna, dan varian yang menumpuk di outlet ini. */
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import { formatRupiah } from '@/composables/useRupiah';

defineOptions({ layout: AppLayout });

defineProps({
    period: { type: String, required: true },
    periods: { type: Array, required: true },
    best: { type: Array, required: true },
    dimensions: { type: Array, required: true },
    slow: { type: Array, required: true },
});

const go = (period) => router.get(route('reports.variants'), { period }, { preserveScroll: true, replace: true });
const maxQty = (rows) => Math.max(1, ...rows.map((r) => parseFloat(String(r.qty).replace(',', '.')) || 0));
const qtyNum = (q) => parseFloat(String(q).replace(',', '.')) || 0;
</script>

<template>
    <Head title="Laporan Varian" />
    <div class="mx-auto max-w-4xl">
        <PageHeader title="Laporan Varian" subtitle="Ukuran & warna mana yang laku, mana yang menumpuk." :back-href="route('reports.index')" />
        <SegmentedControl :model-value="period" label="Periode" :options="periods" class="mb-6" @update:model-value="go" />

        <EmptyState v-if="!best.length && !slow.length" title="Belum ada data" message="Data muncul setelah ada penjualan barang yang punya varian ukuran & warna." />

        <section v-if="best.length" class="mb-8">
            <h2 class="mb-3 text-xl font-extrabold text-ink">Varian terlaris</h2>
            <ol class="card divide-y divide-line">
                <li v-for="(v, i) in best" :key="v.name" class="flex min-h-touch items-center gap-3 px-4 py-3">
                    <span class="w-8 shrink-0 font-display text-xl font-extrabold text-ink-soft">{{ i + 1 }}</span>
                    <span class="min-w-0 flex-1 truncate text-lg font-bold text-ink">{{ v.name }}</span>
                    <span class="shrink-0 text-right">
                        <span class="block font-bold text-ink tabular-nums">{{ v.qty }} terjual</span>
                        <span class="block text-base text-ink-soft tabular-nums">{{ formatRupiah(v.revenue) }}</span>
                    </span>
                </li>
            </ol>
        </section>

        <div v-if="dimensions.length" class="mb-8 grid gap-6 md:grid-cols-2">
            <section v-for="d in dimensions" :key="d.name">
                <h2 class="mb-3 text-xl font-extrabold text-ink">Per {{ d.name.toLowerCase() }}</h2>
                <ul class="card flex flex-col gap-3 p-4">
                    <li v-for="row in d.rows" :key="row.value">
                        <div class="flex items-center justify-between gap-3 text-lg">
                            <span class="font-bold text-ink">{{ row.value }}</span>
                            <span class="text-ink-soft tabular-nums">{{ row.qty }} · {{ formatRupiah(row.revenue) }}</span>
                        </div>
                        <div class="mt-1 h-3 overflow-hidden rounded-full bg-surface-2" aria-hidden="true">
                            <div class="h-full rounded-full bg-primary" :style="{ width: `${(qtyNum(row.qty) / maxQty(d.rows)) * 100}%` }" />
                        </div>
                    </li>
                </ul>
            </section>
        </div>

        <section v-if="slow.length">
            <h2 class="mb-1 text-xl font-extrabold text-ink">Menumpuk (paling jarang laku)</h2>
            <p class="mb-3 text-base text-ink-soft">Stok masih ada tapi sedikit terjual. Cocok dibuat promo.</p>
            <ul class="card divide-y divide-line">
                <li v-for="v in slow" :key="v.name" class="flex min-h-touch items-center justify-between gap-3 px-4 py-3">
                    <span class="min-w-0">
                        <span class="block truncate text-lg font-bold text-ink">{{ v.name }}</span>
                        <span class="block text-base text-ink-soft">terjual {{ v.sold }} · stok {{ v.stock }}</span>
                    </span>
                    <span class="shrink-0 text-base text-ink-soft tabular-nums">modal {{ formatRupiah(v.value) }}</span>
                </li>
            </ul>
        </section>
    </div>
</template>
