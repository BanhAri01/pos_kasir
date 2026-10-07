<script setup>
/** Riwayat stok satu barang: setiap masuk/keluar tercatat dengan waktu & siapa. */
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    product: { type: Object, required: true },
    movements: { type: Array, required: true },
    next_url: { type: String, default: null },
    locations: { type: Array, default: () => [] },
});

function setLocation(event) {
    const value = event.target.value;
    router.put(route('warehouse.locations.assign', props.product.id), { location_id: value === '' ? null : Number(value) }, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Riwayat ${product.name}`" />

    <div class="mx-auto max-w-3xl">
        <PageHeader :title="product.name" subtitle="Riwayat stok di outlet ini" :back-href="route('stock.index')" />

        <div class="card mb-6 flex items-center justify-between p-5">
            <p class="text-lg text-ink-soft">Stok sekarang</p>
            <p class="text-right font-display text-3xl font-extrabold text-ink">
                {{ product.stock }} {{ product.unit }}
                <span v-if="product.pack_equivalent !== null" class="block font-sans text-lg font-bold text-ink-soft">≈ {{ product.pack_equivalent }} {{ product.pack_name }}</span>
            </p>
        </div>

        <label v-if="locations.length" class="card mb-6 block p-5">
            <span class="mb-2 block text-lg font-bold text-ink">Disimpan di blok / rak</span>
            <select :value="product.location_id ?? ''" class="min-h-touch w-full rounded-2xl border-2 border-line bg-surface px-4 text-lg text-ink" @change="setLocation">
                <option value="">Belum ditentukan</option>
                <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
            </select>
        </label>

        <ul v-if="movements.length" class="card divide-y divide-line overflow-hidden">
            <li v-for="m in movements" :key="m.id" class="flex items-center gap-3 px-4 py-3">
                <span
                    class="flex size-11 shrink-0 items-center justify-center rounded-full"
                    :class="m.is_in ? 'bg-primary-soft text-primary-ink' : 'bg-danger-soft text-danger-ink'"
                >
                    <component :is="m.is_in ? ArrowDown : ArrowUp" :size="22" aria-hidden="true" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-lg font-bold text-ink">{{ m.label }}</p>
                    <p class="text-base text-ink-soft">
                        {{ m.at }}<template v-if="m.user"> · {{ m.user }}</template>
                    </p>
                    <p v-if="m.note" class="text-base text-ink-soft">{{ m.note }}</p>
                </div>
                <div class="text-right">
                    <p class="font-display text-xl font-extrabold tabular-nums" :class="m.is_in ? 'text-primary-ink' : 'text-danger-ink'">
                        {{ m.is_in ? '+' : '' }}{{ m.qty_change }}
                    </p>
                    <p class="text-base text-ink-soft">sisa {{ m.qty_after }}</p>
                </div>
            </li>
        </ul>
        <EmptyState v-else title="Belum ada riwayat" message="Riwayat muncul setelah ada stok masuk, keluar, atau penjualan." />

        <div v-if="next_url" class="mt-4 text-center">
            <Link :href="next_url" preserve-scroll class="inline-flex min-h-touch items-center rounded-2xl px-6 text-lg font-bold text-primary-ink hover:bg-primary-soft">
                Lihat riwayat lebih lama
            </Link>
        </div>
    </div>
</template>
