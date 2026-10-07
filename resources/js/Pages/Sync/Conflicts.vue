<script setup>
/** "Perlu Dicek": hal yang muncul setelah kasir offline tersinkron. */
import { Head, Link, router } from '@inertiajs/vue3';
import { Check, CircleCheck, TriangleAlert } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

defineOptions({ layout: AppLayout });

defineProps({
    conflicts: { type: Array, required: true },
});
</script>

<template>
    <Head title="Perlu Dicek" />
    <div class="mx-auto max-w-3xl">
        <PageHeader
            title="Perlu Dicek"
            subtitle="Catatan dari kasir yang sempat offline. Tidak ada data yang hilang, hanya perlu Anda periksa."
            :back-href="route('dashboard')"
        />

        <ul v-if="conflicts.length" class="flex flex-col gap-3">
            <li v-for="c in conflicts" :key="c.id" class="card p-5">
                <div class="flex items-start gap-3">
                    <TriangleAlert :size="26" class="mt-0.5 shrink-0 text-warn-ink" aria-hidden="true" />
                    <div class="flex-1">
                        <p class="text-lg font-bold text-ink">{{ c.message }}</p>
                        <p class="text-base text-ink-soft">{{ c.at }}<template v-if="c.outlet"> · {{ c.outlet }}</template></p>
                    </div>
                </div>
                <div class="mt-4 grid gap-2 sm:grid-cols-2">
                    <BigButton v-if="c.type === 'stock_negative' && c.product_id" :href="route('stock.history', c.product_id)" variant="secondary">Lihat Stok</BigButton>
                    <BigButton variant="soft" @click="router.post(route('conflicts.resolve', c.id), {}, { preserveScroll: true })">
                        <Check :size="22" aria-hidden="true" /> Sudah Dicek
                    </BigButton>
                </div>
            </li>
        </ul>
        <EmptyState v-else title="Tidak ada yang perlu dicek" message="Semua data dari kasir sudah beres.">
            <template #icon><CircleCheck :size="48" class="text-primary-ink" /></template>
        </EmptyState>
    </div>
</template>
