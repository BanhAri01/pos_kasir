<script setup>
/** Riwayat olah, kemas ulang, dan bongkar karung di gudang aktif. */
import { Head, Link } from '@inertiajs/vue3';
import { ChevronRight, ClipboardList } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

defineOptions({ layout: AppLayout });

defineProps({ orders: { type: Object, required: true } });

const tone = { production: 'bg-info-soft text-info-ink', repack: 'bg-primary-soft text-primary-ink', unpack: 'bg-accent-soft text-accent-ink' };
</script>

<template>
    <Head title="Riwayat Olah & Kemas" />
    <div class="mx-auto max-w-3xl">
        <PageHeader title="Riwayat Olah & Kemas" subtitle="Untuk gudang yang sedang dipilih." :back-href="route('warehouse.dashboard')" />

        <EmptyState v-if="!orders.data.length" title="Belum ada catatan" message="Catatan muncul setelah Anda mengemas atau mengolah barang.">
            <template #icon><ClipboardList :size="48" aria-hidden="true" /></template>
        </EmptyState>

        <ul v-else class="card divide-y divide-line overflow-hidden">
            <li v-for="o in orders.data" :key="o.uuid">
                <Link :href="route('warehouse.orders.show', o.uuid)" class="flex min-h-touch-lg items-center gap-3 px-4 py-3 hover:bg-surface-2">
                    <span class="min-w-0 flex-1">
                        <span class="mb-1 inline-block rounded-lg px-2 text-base font-bold" :class="tone[o.kind]">{{ o.kind_label }}</span>
                        <span class="block truncate text-lg font-bold text-ink">{{ o.output_qty }} {{ o.unit }} {{ o.output }}</span>
                        <span class="block text-base text-ink-soft">{{ o.number }} · {{ o.date }}<template v-if="o.shrinkage"> · susut {{ o.shrinkage }}</template></span>
                    </span>
                    <ChevronRight :size="24" class="shrink-0 text-ink-soft" aria-hidden="true" />
                </Link>
            </li>
        </ul>

        <div v-if="orders.next_page_url" class="mt-4 text-center">
            <Link :href="orders.next_page_url" preserve-scroll class="inline-flex min-h-touch items-center rounded-2xl px-6 text-lg font-bold text-primary-ink hover:bg-primary-soft">Lihat yang lebih lama</Link>
        </div>
    </div>
</template>
