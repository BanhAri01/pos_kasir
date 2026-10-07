<script setup>
/** Daftar resep kemas ulang & olah. */
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { ClipboardList, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

defineOptions({ layout: AppLayout });

defineProps({
    formulas: { type: Array, required: true },
    kinds: { type: Array, required: true },
});

const removing = ref(null);
</script>

<template>
    <Head title="Resep Kemas & Olah" />
    <div class="mx-auto max-w-3xl">
        <PageHeader title="Resep Kemas & Olah" subtitle="Bahan untuk 1 kali kemas atau olah, dan hasilnya." :back-href="route('warehouse.dashboard')" />

        <div class="mb-5 flex flex-wrap gap-3">
            <BigButton v-for="k in kinds" :key="k.value" :href="route('warehouse.formulas.create', { jenis: k.value })">
                <Plus :size="22" aria-hidden="true" /> Resep {{ k.label }}
            </BigButton>
        </div>

        <EmptyState v-if="!formulas.length" title="Belum ada resep" message="Buat resep supaya bahan & karung kosong berkurang otomatis saat mengemas atau mengolah.">
            <template #icon><ClipboardList :size="48" aria-hidden="true" /></template>
        </EmptyState>

        <ul class="grid gap-3">
            <li v-for="f in formulas" :key="f.id" class="card flex items-start justify-between gap-3 p-4">
                <div class="min-w-0">
                    <p class="text-base font-bold text-ink-soft">{{ kinds.find((k) => k.value === f.kind)?.label }}</p>
                    <p class="text-xl font-extrabold text-ink">{{ f.name }}</p>
                    <p class="mt-1 text-lg text-ink">
                        {{ f.items.join(' + ') }} = <strong>{{ f.output_qty }} {{ f.output_unit }} {{ f.output }}</strong>
                    </p>
                </div>
                <span class="flex shrink-0">
                    <BigButton :href="route('warehouse.formulas.edit', f.id)" variant="ghost" :aria-label="`Ubah ${f.name}`"><Pencil :size="22" /></BigButton>
                    <button type="button" class="pressable flex size-touch items-center justify-center rounded-xl text-danger-ink" :aria-label="`Hapus ${f.name}`" @click="removing = f"><Trash2 :size="22" /></button>
                </span>
            </li>
        </ul>
    </div>

    <ConfirmDialog
        :open="!!removing"
        :title="`Hapus resep ${removing?.name ?? ''}?`"
        message="Riwayat kemas & olah yang sudah tercatat tetap tersimpan."
        confirm-text="Ya, Hapus"
        danger
        @update:open="(v) => !v && (removing = null)"
        @confirm="router.delete(route('warehouse.formulas.destroy', removing.id), { onFinish: () => (removing = null) })"
    />
</template>
