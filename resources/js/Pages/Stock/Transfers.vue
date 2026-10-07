<script setup>
/** Daftar kiriman stok antar outlet + tombol "Sudah Diterima". */
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { ArrowRight, Check, Plus, Truck, X } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

defineOptions({ layout: AppLayout });

defineProps({
    transfers: { type: Array, required: true },
    canSend: { type: Boolean, default: false },
});

const toCancel = ref(null);

const statusLabel = { sent: 'Dalam perjalanan', received: 'Sudah diterima', canceled: 'Dibatalkan' };
const statusTone = { sent: 'bg-accent-soft text-accent-ink', received: 'bg-primary-soft text-primary-ink', canceled: 'bg-surface-2 text-ink-soft' };
</script>

<template>
    <Head title="Kirim Stok" />

    <div class="mx-auto max-w-3xl">
        <PageHeader title="Kirim Stok" subtitle="Pindahkan barang antar outlet." :back-href="route('stock.index')" help="stock-transfer" />

        <BigButton v-if="canSend" :href="route('stock.transfers.create')" block size="large" class="mb-6">
            <Plus :size="26" aria-hidden="true" /> Kirim Stok Baru
        </BigButton>
        <div v-else class="mb-6 rounded-2xl bg-info-soft p-4 text-lg text-info-ink">
            Kirim stok butuh minimal 2 outlet. Tambah outlet di menu Lainnya, lalu Outlet / Cabang.
        </div>

        <ul v-if="transfers.length" class="flex flex-col gap-3">
            <li v-for="t in transfers" :key="t.id" class="card p-5">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="font-display text-lg font-extrabold text-ink">{{ t.number }}</p>
                    <span class="rounded-full px-3 py-0.5 text-base font-bold" :class="statusTone[t.status]">{{ statusLabel[t.status] }}</span>
                </div>
                <p class="mt-2 flex flex-wrap items-center gap-2 text-lg text-ink">
                    {{ t.from }} <ArrowRight :size="20" aria-label="ke" /> {{ t.to }}
                </p>
                <p class="mt-1 text-base text-ink-soft">{{ t.sent_at }}</p>
                <ul class="mt-2 list-disc pl-6 text-lg text-ink">
                    <li v-for="(line, i) in t.items" :key="i">{{ line }}</li>
                </ul>
                <div v-if="t.status === 'sent'" class="mt-4 grid gap-3 sm:grid-cols-2">
                    <BigButton @click="router.post(route('stock.transfers.receive', t.id), {}, { preserveScroll: true })">
                        <Check :size="22" aria-hidden="true" /> Sudah Diterima
                    </BigButton>
                    <BigButton variant="ghost" class="text-danger-ink" @click="toCancel = t">
                        <X :size="22" aria-hidden="true" /> Batalkan Kiriman
                    </BigButton>
                </div>
            </li>
        </ul>
        <EmptyState v-else title="Belum ada kiriman stok">
            <template #icon><Truck :size="48" /></template>
        </EmptyState>
    </div>

    <ConfirmDialog
        :open="!!toCancel"
        :title="`Batalkan kiriman ${toCancel?.number}?`"
        message="Stok akan dikembalikan ke outlet asal."
        confirm-text="Ya, Batalkan"
        danger
        @update:open="(v) => !v && (toCancel = null)"
        @confirm="router.post(route('stock.transfers.cancel', toCancel.id), {}, { onFinish: () => (toCancel = null) })"
    />
</template>
