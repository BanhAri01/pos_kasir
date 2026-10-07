<script setup>
/** Rincian satu kali olah / kemas / bongkar: bahan rencana vs terpakai, hasil, susut, biaya, dan modal hasil. */
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import MoneyDisplay from '@/Components/ui/MoneyDisplay.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { formatRupiah } from '@/composables/useRupiah';

defineOptions({ layout: AppLayout });

defineProps({ order: { type: Object, required: true } });

const roleLabel = { material: 'Bahan', packaging: 'Bahan kemas', output: 'Hasil' };
</script>

<template>
    <Head :title="order.number" />
    <div class="mx-auto max-w-3xl">
        <PageHeader :title="`${order.kind_label} ${order.number}`" :subtitle="order.date" :back-href="route('warehouse.orders')" />

        <div class="card mb-5 grid gap-4 p-5 sm:grid-cols-2">
            <div>
                <p class="text-lg text-ink-soft">Hasil</p>
                <p class="font-display text-3xl font-extrabold text-ink">{{ order.output_qty }} {{ order.unit }}</p>
                <p class="text-lg text-ink">{{ order.output }}</p>
                <p v-if="order.planned_output !== order.output_qty" class="text-base text-ink-soft">Rencana {{ order.planned_output }} {{ order.unit }}</p>
            </div>
            <div>
                <MoneyDisplay :amount="order.unit_cost" size="lg" :label="`Modal per ${order.unit || 'satuan'}`" />
                <p v-if="order.shrinkage !== '0'" class="mt-2 text-lg font-bold text-warn-ink">Susut {{ order.shrinkage }} {{ order.shrinkage_unit }}</p>
            </div>
            <p v-if="order.formula" class="text-base text-ink-soft sm:col-span-2">Resep: {{ order.formula }}<template v-if="order.user"> · dicatat oleh {{ order.user }}</template></p>
            <p v-if="order.batch_no" class="text-base text-ink sm:col-span-2">Batch <strong>{{ order.batch_no }}</strong><template v-if="order.expires_at"> · kedaluwarsa {{ order.expires_at }}</template></p>
            <p v-if="order.note" class="text-base text-ink sm:col-span-2">Catatan: {{ order.note }}</p>
        </div>

        <h2 class="mb-3 text-xl font-extrabold text-ink">Barang</h2>
        <ul class="card mb-5 divide-y divide-line">
            <li v-for="(i, idx) in order.items" :key="idx" class="flex min-h-touch items-center justify-between gap-3 px-4 py-3">
                <span class="min-w-0">
                    <span class="block text-base font-bold" :class="i.role === 'output' ? 'text-primary-ink' : 'text-ink-soft'">{{ roleLabel[i.role] }}</span>
                    <span class="block text-lg font-bold text-ink">{{ i.name }}</span>
                    <span v-if="i.planned !== i.actual" class="block text-base text-ink-soft">rencana {{ i.planned }} {{ i.unit }}</span>
                </span>
                <span class="shrink-0 text-right">
                    <span class="block font-display text-xl font-extrabold text-ink tabular-nums">{{ i.role === 'output' ? '+' : '−' }}{{ i.actual }} {{ i.unit }}</span>
                    <span class="block text-base text-ink-soft tabular-nums">{{ formatRupiah(i.total_cost) }}</span>
                </span>
            </li>
        </ul>

        <template v-if="Object.keys(order.costs).length">
            <h2 class="mb-3 text-xl font-extrabold text-ink">Biaya</h2>
            <ul class="card divide-y divide-line">
                <li v-for="(amount, label) in order.costs" :key="label" class="flex min-h-touch items-center justify-between gap-3 px-4 py-3 text-lg">
                    <span class="text-ink">{{ label }}</span>
                    <span class="font-bold text-ink tabular-nums">{{ formatRupiah(amount) }}</span>
                </li>
            </ul>
        </template>
    </div>
</template>
