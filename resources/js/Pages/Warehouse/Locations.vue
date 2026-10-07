<script setup>
/**
 * Blok / rak penyimpanan di gudang yang sedang dipilih.
 * Barang ditempatkan ke blok lewat halaman riwayat stok barangnya.
 */
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { MapPin, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import BigInput from '@/Components/ui/BigInput.vue';
import BottomSheet from '@/Components/ui/BottomSheet.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

defineOptions({ layout: AppLayout });

defineProps({ locations: { type: Array, required: true } });

const sheet = ref(false);
const editing = ref(null);
const removing = ref(null);
const form = useForm({ name: '', note: '' });

function open(location = null) {
    editing.value = location;
    Object.assign(form, { name: location?.name ?? '', note: location?.note ?? '' });
    form.clearErrors();
    sheet.value = true;
}

function save() {
    const options = { preserveScroll: true, onSuccess: () => (sheet.value = false) };
    editing.value ? form.put(route('warehouse.locations.update', editing.value.id), options) : form.post(route('warehouse.locations.store'), options);
}
</script>

<template>
    <Head title="Blok Gudang" />
    <div class="mx-auto max-w-3xl">
        <PageHeader title="Blok Gudang" subtitle="Tempat menyimpan barang di gudang ini. Contoh: Blok A, Rak 2." :back-href="route('stock.index')">
            <template #action>
                <BigButton @click="open()"><Plus :size="24" aria-hidden="true" /> Tambah Blok</BigButton>
            </template>
        </PageHeader>

        <EmptyState v-if="!locations.length" title="Belum ada blok" message="Tambahkan blok supaya mudah mencari barang di gudang.">
            <template #icon><MapPin :size="48" aria-hidden="true" /></template>
        </EmptyState>

        <ul class="grid gap-3 md:grid-cols-2">
            <li v-for="l in locations" :key="l.id" class="card flex min-w-0 items-start justify-between gap-2 p-4">
                <div class="min-w-0">
                    <p class="truncate text-xl font-extrabold text-ink">{{ l.name }}</p>
                    <p class="text-base text-ink-soft">{{ l.product_count }} barang disimpan di sini</p>
                    <p v-if="l.note" class="text-base text-ink-soft">{{ l.note }}</p>
                </div>
                <span class="flex shrink-0">
                    <button type="button" class="pressable flex size-touch items-center justify-center rounded-xl text-ink-soft" :aria-label="`Ubah ${l.name}`" @click="open(l)"><Pencil :size="22" /></button>
                    <button type="button" class="pressable flex size-touch items-center justify-center rounded-xl text-danger-ink" :aria-label="`Hapus ${l.name}`" @click="removing = l"><Trash2 :size="22" /></button>
                </span>
            </li>
        </ul>

        <p v-if="locations.length" class="mt-4 text-base text-ink-soft">Untuk menaruh barang ke blok: buka Stok, ketuk barangnya, lalu pilih bloknya.</p>
    </div>

    <BottomSheet v-model:open="sheet" :title="editing ? 'Ubah Blok' : 'Blok Baru'">
        <form class="flex flex-col gap-4" @submit.prevent="save">
            <BigInput v-model="form.name" label="Nama blok" placeholder="Contoh: Blok A" :error="form.errors.name" />
            <BigInput v-model="form.note" label="Catatan" optional placeholder="Contoh: dekat pintu masuk" :error="form.errors.note" />
            <BigButton type="submit" block size="large" :loading="form.processing">Simpan</BigButton>
        </form>
    </BottomSheet>

    <ConfirmDialog
        :open="!!removing"
        :title="`Hapus ${removing?.name ?? ''}?`"
        message="Barang dan stoknya tetap ada. Bloknya saja yang jadi belum ditentukan."
        confirm-text="Ya, Hapus"
        danger
        @update:open="(v) => !v && (removing = null)"
        @confirm="router.delete(route('warehouse.locations.destroy', removing.id), { preserveScroll: true, onFinish: () => (removing = null) })"
    />
</template>
