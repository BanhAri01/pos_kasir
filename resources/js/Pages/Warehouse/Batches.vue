<script setup>
/** Batch yang masih ada di gudang aktif, urut sesuai yang keluar duluan (kedaluwarsa terdekat). */
import { ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { CalendarClock, Search } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    groups: { type: Array, required: true },
    q: { type: String, default: '' },
    warningDays: { type: Number, required: true },
});

const search = ref(props.q);
let timer = null;
watch(search, (value) => {
    clearTimeout(timer);
    timer = setTimeout(() => router.get(route('warehouse.batches'), { q: value || undefined }, { preserveState: true, replace: true }), 350);
});

function expiryClass(days) {
    if (days === null) return 'text-ink-soft';
    if (days < 0) return 'text-danger-ink';
    return days <= props.warningDays ? 'text-warn-ink' : 'text-ink-soft';
}

function expiryText(b) {
    if (!b.expires_at) return 'Tanpa tanggal kedaluwarsa';
    if (b.days_left < 0) return `Sudah lewat (${b.expires_at})`;
    if (b.days_left === 0) return `Kedaluwarsa hari ini`;
    return `Kedaluwarsa ${b.expires_at} (${b.days_left} hari lagi)`;
}
</script>

<template>
    <Head title="Batch & Kedaluwarsa" />
    <div class="mx-auto max-w-3xl">
        <PageHeader title="Batch & Kedaluwarsa" subtitle="Yang paling atas keluar duluan saat dijual atau dipakai." :back-href="route('warehouse.dashboard')" />

        <label class="relative mb-4 block">
            <span class="sr-only">Cari barang atau nomor batch</span>
            <Search :size="24" class="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-ink-soft" aria-hidden="true" />
            <input v-model="search" type="search" placeholder="Cari barang atau nomor batch" class="min-h-touch w-full rounded-2xl border-2 border-line bg-surface pr-4 pl-13 text-lg text-ink focus:border-focus focus:outline-none" />
        </label>

        <EmptyState v-if="!groups.length" title="Belum ada batch" message="Nyalakan 'Catat nomor batch' di form barang, lalu isi nomor batch saat terima barang.">
            <template #icon><CalendarClock :size="48" aria-hidden="true" /></template>
        </EmptyState>

        <section v-for="g in groups" :key="g.product" class="card mb-4 overflow-hidden">
            <div class="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
                <h2 class="min-w-0 truncate text-xl font-extrabold text-ink">{{ g.product }}</h2>
                <span class="shrink-0 text-lg font-bold text-ink tabular-nums">{{ g.total }} {{ g.unit }}</span>
            </div>
            <ul class="divide-y divide-line">
                <li v-for="(b, i) in g.batches" :key="b.id" class="flex items-start justify-between gap-3 px-4 py-3">
                    <span class="min-w-0">
                        <span class="block text-lg font-bold text-ink">
                            {{ b.batch_no }}
                            <span v-if="i === 0" class="ml-1 rounded-lg bg-primary-soft px-2 text-base text-primary-ink">keluar duluan</span>
                        </span>
                        <span class="block text-base" :class="expiryClass(b.days_left)">{{ expiryText(b) }}</span>
                        <span class="block text-base text-ink-soft">
                            Masuk {{ b.received_on }}<template v-if="b.moisture"> · kadar air {{ b.moisture }}%</template><template v-if="b.quality_note"> · {{ b.quality_note }}</template>
                        </span>
                    </span>
                    <span class="shrink-0 font-display text-xl font-extrabold text-ink tabular-nums">{{ b.qty }} {{ g.unit }}</span>
                </li>
            </ul>
        </section>
    </div>
</template>
