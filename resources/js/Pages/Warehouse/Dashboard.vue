<script setup>
/**
 * Beranda Gudang: alur kerja + ringkasan stok per gudang (kg & setara karung),
 * barang hampir habis, batch hampir kedaluwarsa, susut bulan ini, dan HPP per barang.
 */
import { Deferred, Head, Link } from '@inertiajs/vue3';
import { CalendarClock, ChevronRight, ClipboardList, PackageOpen, TrendingDown } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import WarehouseFlow from '@/Components/WarehouseFlow.vue';
import { formatRupiah } from '@/composables/useRupiah';
import { useAuth } from '@/composables/useAuth';

defineOptions({ layout: AppLayout });

defineProps({
    outlets: { type: Array, required: true },
    bulk: { type: Array, default: null },
    low: { type: Array, default: null },
    expiring: { type: Array, default: null },
    shrinkage: { type: Object, default: null },
    costs: { type: Array, default: null },
});

const { hasModule } = useAuth();
const skeleton = 'card h-24 animate-pulse bg-surface-2';
</script>

<template>
    <Head title="Gudang" />
    <PageHeader title="Gudang" subtitle="Pilih pekerjaan yang mau dilakukan." />

    <WarehouseFlow />

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <!-- Susut bulan ini -->
        <Deferred data="shrinkage">
            <template #fallback><div :class="skeleton" /></template>
            <Link v-if="shrinkage" :href="route('warehouse.reports')" class="card pressable flex items-center gap-4 p-5 hover:ring-2 hover:ring-primary/30">
                <span class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-danger-soft text-danger-ink"><TrendingDown :size="28" aria-hidden="true" /></span>
                <span class="min-w-0 flex-1">
                    <span class="block text-lg text-ink-soft">Susut bulan ini</span>
                    <span class="block font-display text-2xl font-extrabold text-ink">{{ formatRupiah(shrinkage.value) }}</span>
                    <span v-if="shrinkage.production_qty !== '0'" class="block text-base text-ink-soft">+ susut olah/kemas {{ shrinkage.production_qty }} kg</span>
                </span>
                <ChevronRight :size="26" class="text-ink-soft" aria-hidden="true" />
            </Link>
        </Deferred>

        <!-- Riwayat olah -->
        <Link v-if="hasModule('repack') || hasModule('production')" :href="route('warehouse.orders')" class="card pressable flex items-center gap-4 p-5 hover:ring-2 hover:ring-primary/30">
            <span class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-info-soft text-info-ink"><ClipboardList :size="28" aria-hidden="true" /></span>
            <span class="min-w-0 flex-1">
                <span class="block font-display text-xl font-extrabold text-ink">Riwayat Olah & Kemas</span>
                <span class="block text-base text-ink-soft">Bahan terpakai, hasil, susut, dan modal</span>
            </span>
            <ChevronRight :size="26" class="text-ink-soft" aria-hidden="true" />
        </Link>
    </div>

    <!-- Stok per gudang -->
    <section class="mt-8">
        <h2 class="mb-3 text-xl font-extrabold text-ink">Stok barang curah per gudang</h2>
        <Deferred data="bulk">
            <template #fallback><div :class="skeleton" /></template>
            <p v-if="bulk && !bulk.length" class="card p-5 text-lg text-ink-soft">Belum ada barang yang dijual per kg.</p>
            <div v-else-if="bulk" class="card overflow-x-auto">
                <table class="w-full min-w-max text-left text-lg">
                    <thead>
                        <tr class="border-b border-line text-base text-ink-soft">
                            <th scope="col" class="px-4 py-3 font-bold">Barang</th>
                            <th v-for="o in outlets" :key="o.id" scope="col" class="px-4 py-3 text-right font-bold">{{ o.name }}</th>
                            <th v-if="outlets.length > 1" scope="col" class="px-4 py-3 text-right font-bold">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        <tr v-for="row in bulk" :key="row.name">
                            <th scope="row" class="px-4 py-3 font-bold text-ink">{{ row.name }}</th>
                            <td v-for="o in outlets" :key="o.id" class="px-4 py-3 text-right tabular-nums">
                                <span class="block font-bold" :class="row.cells[o.id].negative ? 'text-danger-ink' : 'text-ink'">{{ row.cells[o.id].qty }} {{ row.unit }}</span>
                                <span v-if="row.cells[o.id].packs" class="block text-base text-ink-soft">≈ {{ row.cells[o.id].packs }}</span>
                            </td>
                            <td v-if="outlets.length > 1" class="px-4 py-3 text-right tabular-nums">
                                <span class="block font-extrabold text-ink">{{ row.total.qty }} {{ row.unit }}</span>
                                <span v-if="row.total.packs" class="block text-base text-ink-soft">≈ {{ row.total.packs }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Deferred>
    </section>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <!-- Hampir habis -->
        <section>
            <h2 class="mb-3 flex items-center gap-2 text-xl font-extrabold text-ink"><PackageOpen :size="22" aria-hidden="true" /> Hampir habis (gudang ini)</h2>
            <Deferred data="low">
                <template #fallback><div :class="skeleton" /></template>
                <p v-if="low && !low.length" class="card p-5 text-lg text-ink-soft">Semua stok aman.</p>
                <ul v-else-if="low" class="card divide-y divide-line">
                    <li v-for="item in low" :key="item.name" class="flex min-h-touch items-center justify-between gap-3 px-4 py-3">
                        <span class="min-w-0">
                            <span class="block truncate text-lg font-bold text-ink">{{ item.name }}</span>
                            <span v-if="item.is_packaging" class="block text-base text-ink-soft">Bahan kemas</span>
                        </span>
                        <span class="shrink-0 font-bold tabular-nums" :class="item.out ? 'text-danger-ink' : 'text-warn-ink'">{{ item.qty }} {{ item.unit }}</span>
                    </li>
                </ul>
            </Deferred>
        </section>

        <!-- Hampir kedaluwarsa -->
        <section v-if="hasModule('batch_lot')">
            <h2 class="mb-3 flex items-center gap-2 text-xl font-extrabold text-ink"><CalendarClock :size="22" aria-hidden="true" /> Batch hampir kedaluwarsa</h2>
            <Deferred data="expiring">
                <template #fallback><div :class="skeleton" /></template>
                <p v-if="expiring && !expiring.length" class="card p-5 text-lg text-ink-soft">Tidak ada batch yang kedaluwarsa dalam 30 hari.</p>
                <ul v-else-if="expiring" class="card divide-y divide-line">
                    <li v-for="b in expiring" :key="`${b.product}-${b.batch_no}-${b.outlet}`" class="flex min-h-touch items-center justify-between gap-3 px-4 py-3">
                        <span class="min-w-0">
                            <span class="block truncate text-lg font-bold text-ink">{{ b.product }}</span>
                            <span class="block text-base text-ink-soft">Batch {{ b.batch_no }} · {{ b.outlet }} · sisa {{ b.qty }} {{ b.unit }}</span>
                        </span>
                        <span class="shrink-0 text-right">
                            <span class="block font-bold" :class="b.days_left < 0 ? 'text-danger-ink' : 'text-warn-ink'">{{ b.days_left < 0 ? 'Sudah lewat' : b.days_left === 0 ? 'Hari ini' : `${b.days_left} hari lagi` }}</span>
                            <span class="block text-base text-ink-soft">{{ b.expires_at }}</span>
                        </span>
                    </li>
                </ul>
                <Link :href="route('warehouse.batches')" class="mt-2 inline-flex min-h-12 items-center gap-1 text-lg font-bold text-primary-ink">Lihat semua batch <ChevronRight :size="20" aria-hidden="true" /></Link>
            </Deferred>
        </section>
    </div>

    <!-- HPP per barang -->
    <section class="mt-8">
        <h2 class="mb-1 text-xl font-extrabold text-ink">Modal (HPP) per barang</h2>
        <p class="mb-3 text-base text-ink-soft">Modal rata-rata di gudang ini, sudah termasuk ongkos angkut, kemasan, dan biaya olah.</p>
        <Deferred data="costs">
            <template #fallback><div :class="skeleton" /></template>
            <ul v-if="costs?.length" class="card divide-y divide-line">
                <li v-for="c in costs" :key="c.name" class="flex min-h-touch flex-wrap items-center justify-between gap-x-3 gap-y-1 px-4 py-3">
                    <span class="min-w-0 flex-1 truncate text-lg font-bold text-ink">{{ c.name }}</span>
                    <span class="text-right text-base text-ink-soft tabular-nums">
                        modal <strong class="text-ink">{{ formatRupiah(c.cost) }}</strong> · jual {{ formatRupiah(c.price) }}/{{ c.unit }}
                        <span class="block font-bold" :class="c.margin < 0 ? 'text-danger-ink' : 'text-primary-ink'">untung {{ formatRupiah(c.margin) }}/{{ c.unit }}</span>
                    </span>
                </li>
            </ul>
            <p v-else class="card p-5 text-lg text-ink-soft">Belum ada barang jual dengan stok di gudang ini.</p>
        </Deferred>
    </section>
</template>
