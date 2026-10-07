<script setup>
/** Laporan barang titipan: yang terjual per pemilik barang, bagian toko, dan yang harus dibayar ke pemilik. */
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import MoneyDisplay from '@/Components/ui/MoneyDisplay.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import { formatRupiah } from '@/composables/useRupiah';

defineOptions({ layout: AppLayout });

defineProps({
    period: { type: String, required: true },
    periods: { type: Array, required: true },
    consignors: { type: Array, required: true },
    total_payable: { type: Number, required: true },
});

const go = (period) => router.get(route('reports.consignment'), { period }, { preserveScroll: true, replace: true });
</script>

<template>
    <Head title="Barang Titipan" />
    <div class="mx-auto max-w-3xl">
        <PageHeader title="Barang Titipan" subtitle="Hasil jual barang titipan dan bagi hasilnya." :back-href="route('reports.index')" />
        <SegmentedControl :model-value="period" label="Periode" :options="periods" class="mb-6" @update:model-value="go" />

        <div class="card mb-6 p-5">
            <MoneyDisplay :amount="total_payable" size="xl" label="Harus dibayar ke pemilik barang" />
        </div>

        <EmptyState v-if="!consignors.length" title="Belum ada barang titipan yang terjual" message="Tandai barang titipan di form barang (bagian Barang titipan)." />

        <section v-for="c in consignors" :key="c.name" class="card mb-4 overflow-hidden">
            <div class="border-b border-line px-4 py-3">
                <p class="text-xl font-extrabold text-ink">{{ c.name }}</p>
                <p class="text-base text-ink-soft">Terjual {{ formatRupiah(c.revenue) }} · bagian toko {{ formatRupiah(c.shop_share) }}</p>
                <p class="text-lg font-bold text-primary-ink">Bayar ke pemilik: {{ formatRupiah(c.payable) }}</p>
            </div>
            <ul class="divide-y divide-line">
                <li v-for="i in c.items" :key="i.name" class="flex min-h-touch items-center justify-between gap-3 px-4 py-3">
                    <span class="min-w-0">
                        <span class="block truncate text-lg font-bold text-ink">{{ i.name }}</span>
                        <span class="block text-base text-ink-soft">{{ i.qty }} terjual · {{ formatRupiah(i.revenue) }}</span>
                    </span>
                    <span class="shrink-0 font-bold text-ink tabular-nums">{{ formatRupiah(i.payable) }}</span>
                </li>
            </ul>
        </section>
    </div>
</template>
