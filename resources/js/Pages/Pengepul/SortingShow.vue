<script setup>
/**
 * Rincian satu kali sortir telur: berat telur campur yang diambil, hasil per jenis,
 * berat pecah/susut, serta rincian pembagian modal (hanya tampil bagi yang berhak).
 */
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import MoneyDisplay from '@/Components/ui/MoneyDisplay.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { formatRupiah } from '@/composables/useRupiah';

defineOptions({ layout: AppLayout });

defineProps({
    sorting: { type: Object, required: true },
});
</script>

<template>
    <Head :title="`Sortir ${sorting.number}`" />
    <div class="mx-auto max-w-3xl">
        <PageHeader
            :title="`Sortir ${sorting.number}`"
            :subtitle="sorting.date"
            :back-href="route('egg.sortings.create')"
        />

        <div class="card mb-5 grid gap-4 p-5 sm:grid-cols-2">
            <div>
                <p class="text-lg text-ink-soft">Hasil sortir</p>
                <p class="font-display text-3xl font-extrabold text-ink tabular-nums">
                    {{ sorting.output_qty }} Kg
                </p>
                <p class="mt-1 text-lg text-ink">
                    Dari <strong>{{ sorting.input_qty }} Kg</strong> {{ sorting.mixed || 'Telur Campur' }}
                </p>
                <p
                    v-if="sorting.shrinkage_qty && sorting.shrinkage_qty !== '0'"
                    class="mt-2 text-lg font-bold text-warn-ink"
                >
                    Pecah / susut {{ sorting.shrinkage_qty }} Kg
                </p>
            </div>

            <div v-if="sorting.input_cost !== null && sorting.input_cost !== undefined">
                <MoneyDisplay :amount="sorting.input_cost" size="lg" label="Modal telur campur" />
                <p
                    v-if="sorting.shrinkage_cost !== null && sorting.shrinkage_cost !== undefined && sorting.shrinkage_cost > 0"
                    class="mt-2 text-lg font-bold text-danger-ink tabular-nums"
                >
                    Rugi pecah / susut {{ formatRupiah(sorting.shrinkage_cost) }}
                </p>
            </div>

            <p v-if="sorting.user" class="text-base text-ink-soft sm:col-span-2">
                Dicatat oleh <strong class="text-ink">{{ sorting.user }}</strong>
            </p>
            <p v-if="sorting.note" class="text-base text-ink sm:col-span-2">
                Catatan: {{ sorting.note }}
            </p>
        </div>

        <h2 class="mb-3 text-xl font-extrabold text-ink">Hasil per jenis telur</h2>
        <ul class="card divide-y divide-line">
            <li
                v-for="(item, idx) in sorting.items"
                :key="idx"
                class="flex min-h-touch flex-wrap items-center justify-between gap-3 px-4 py-3"
            >
                <span class="min-w-0">
                    <span class="block text-xl font-extrabold text-ink">{{ item.name }}</span>
                    <span
                        v-if="item.unit_cost !== null && item.unit_cost !== undefined"
                        class="block text-base text-ink-soft tabular-nums"
                    >
                        Modal per Kg: <strong class="text-ink">{{ formatRupiah(item.unit_cost) }}</strong>
                    </span>
                </span>
                <span class="shrink-0 text-right">
                    <span class="block font-display text-xl font-extrabold text-ink tabular-nums">
                        +{{ item.qty }} Kg
                    </span>
                    <span
                        v-if="item.total_cost !== null && item.total_cost !== undefined"
                        class="block text-base text-ink-soft tabular-nums"
                    >
                        Total modal {{ formatRupiah(item.total_cost) }}
                    </span>
                </span>
            </li>
        </ul>
    </div>
</template>
