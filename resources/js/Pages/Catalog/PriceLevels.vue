<script setup>
/** Tipe harga (Grosir, Tukang, Kontraktor). Harga per barang diisi di formulir barang. */
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Tags, Trash2 } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import BigInput from '@/Components/ui/BigInput.vue';
import BottomSheet from '@/Components/ui/BottomSheet.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

defineOptions({ layout: AppLayout });

defineProps({ levels: { type: Array, required: true } });

const sheet = ref(false);
const editing = ref(null);
const removing = ref(null);
const form = useForm({ name: '' });

function open(level = null) {
    editing.value = level;
    form.name = level?.name ?? '';
    form.clearErrors();
    sheet.value = true;
}

function save() {
    const options = { preserveScroll: true, onSuccess: () => (sheet.value = false) };
    editing.value ? form.put(route('price-levels.update', editing.value.id), options) : form.post(route('price-levels.store'), options);
}
</script>

<template>
    <Head title="Tipe Harga" />
    <div class="mx-auto max-w-2xl">
        <PageHeader title="Tipe Harga" subtitle="Harga khusus untuk kelompok pelanggan, mis. Grosir atau Tukang." :back-href="route('products.index')">
            <template #action>
                <BigButton @click="open()"><Plus :size="24" aria-hidden="true" /> Tambah</BigButton>
            </template>
        </PageHeader>

        <div class="mb-5 rounded-2xl bg-info-soft p-4 text-lg text-info-ink">
            <strong>Cara pakai:</strong> buat tipe harga di sini → isi harganya di formulir barang (bagian “Harga Grosir”) → pilih tipe harga di data pelanggan. Kasir otomatis memakai harga itu saat pelanggan dipilih.
        </div>

        <EmptyState v-if="!levels.length" title="Belum ada tipe harga">
            <template #icon><Tags :size="48" aria-hidden="true" /></template>
        </EmptyState>

        <ul v-else class="card divide-y divide-line">
            <li v-for="l in levels" :key="l.id" class="flex min-h-touch items-center justify-between gap-3 px-4 py-2">
                <span>
                    <span class="block text-lg font-bold text-ink">{{ l.name }}</span>
                    <span class="block text-base text-ink-soft">{{ l.customers_count }} pelanggan</span>
                </span>
                <span class="flex">
                    <button type="button" class="pressable flex size-touch items-center justify-center rounded-xl text-ink-soft" :aria-label="`Ubah ${l.name}`" @click="open(l)"><Pencil :size="22" /></button>
                    <button type="button" class="pressable flex size-touch items-center justify-center rounded-xl text-danger-ink" :aria-label="`Hapus ${l.name}`" @click="removing = l"><Trash2 :size="22" /></button>
                </span>
            </li>
        </ul>
    </div>

    <BottomSheet v-model:open="sheet" :title="editing ? 'Ubah Tipe Harga' : 'Tipe Harga Baru'">
        <form class="flex flex-col gap-4" @submit.prevent="save">
            <BigInput v-model="form.name" label="Nama tipe harga" placeholder="Contoh: Grosir, Tukang, Kontraktor" :error="form.errors.name" />
            <BigButton type="submit" block size="large" :loading="form.processing">Simpan</BigButton>
        </form>
    </BottomSheet>

    <ConfirmDialog
        :open="!!removing"
        :title="`Hapus ${removing?.name ?? ''}?`"
        message="Pelanggan dengan tipe harga ini kembali memakai harga biasa."
        confirm-text="Ya, Hapus"
        danger
        @update:open="(v) => !v && (removing = null)"
        @confirm="router.delete(route('price-levels.destroy', removing.id), { preserveScroll: true, onFinish: () => (removing = null) })"
    />
</template>
