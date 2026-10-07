<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { MessageCircle, Pencil, Plus, Trash2, Truck } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import BigInput from '@/Components/ui/BigInput.vue';
import BottomSheet from '@/Components/ui/BottomSheet.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { formatRupiah } from '@/composables/useRupiah';
import { useAuth } from '@/composables/useAuth';

defineOptions({ layout: AppLayout });

defineProps({ suppliers: { type: Array, required: true } });

const { hasModule } = useAuth();

const sheet = ref(false);
const editing = ref(null);
const removing = ref(null);
const form = useForm({ name: '', phone: '', address: '', notes: '', pack_weight: '' });

function open(supplier = null) {
    editing.value = supplier;
    Object.assign(form, { name: supplier?.name ?? '', phone: supplier?.phone ?? '', address: supplier?.address ?? '', notes: supplier?.notes ?? '', pack_weight: supplier?.pack_weight ?? '' });
    form.clearErrors();
    sheet.value = true;
}

function save() {
    const options = { preserveScroll: true, onSuccess: () => (sheet.value = false) };
    editing.value ? form.put(route('suppliers.update', editing.value.id), options) : form.post(route('suppliers.store'), options);
}
</script>

<template>
    <Head title="Pemasok" />
    <div class="mx-auto max-w-3xl">
        <PageHeader title="Pemasok" subtitle="Tempat Anda membeli barang dagangan." :back-href="route('purchases.index')">
            <template #action>
                <BigButton @click="open()"><Plus :size="24" aria-hidden="true" /> Tambah Pemasok</BigButton>
            </template>
        </PageHeader>

        <EmptyState v-if="!suppliers.length" title="Belum ada pemasok" message="Tambahkan pemasok supaya belanja & utang ke pemasok tercatat rapi.">
            <template #icon><Truck :size="48" aria-hidden="true" /></template>
        </EmptyState>

        <ul class="grid gap-3 md:grid-cols-2">
            <li v-for="s in suppliers" :key="s.id" class="card flex min-w-0 flex-col gap-2 p-4">
                <div class="flex items-start justify-between gap-2">
                    <p class="min-w-0 truncate text-xl font-extrabold text-ink">{{ s.name }}</p>
                    <span class="flex shrink-0">
                        <button type="button" class="pressable flex size-touch items-center justify-center rounded-xl text-ink-soft" :aria-label="`Ubah ${s.name}`" @click="open(s)"><Pencil :size="22" /></button>
                        <button type="button" class="pressable flex size-touch items-center justify-center rounded-xl text-danger-ink" :aria-label="`Hapus ${s.name}`" @click="removing = s"><Trash2 :size="22" /></button>
                    </span>
                </div>
                <p v-if="s.address" class="text-base text-ink-soft">{{ s.address }}</p>
                <p v-if="s.debt > 0" class="text-lg font-bold text-danger-ink">Utang: {{ formatRupiah(s.debt) }}</p>
                <a v-if="s.phone_raw" :href="`https://wa.me/${s.phone_raw}`" target="_blank" rel="noopener" class="inline-flex items-center gap-2 text-lg font-bold text-primary-ink">
                    <MessageCircle :size="20" aria-hidden="true" /> {{ s.phone }}
                </a>
            </li>
        </ul>
    </div>

    <BottomSheet v-model:open="sheet" :title="editing ? 'Ubah Pemasok' : 'Pemasok Baru'">
        <form class="flex flex-col gap-4" @submit.prevent="save">
            <BigInput v-model="form.name" label="Nama pemasok" placeholder="Contoh: Toko Sumber Rejeki" :error="form.errors.name" />
            <BigInput v-model="form.phone" label="No HP / WhatsApp" inputmode="tel" optional :error="form.errors.phone" />
            <BigInput v-model="form.address" label="Alamat" optional :error="form.errors.address" />
            <BigInput v-model="form.notes" label="Catatan" optional placeholder="Contoh: kirim tiap Senin" :error="form.errors.notes" />
            <BigInput
                v-if="hasModule('weighed_receiving')"
                v-model="form.pack_weight"
                label="Berat 1 karung menurut nota (kg)"
                inputmode="decimal"
                optional
                placeholder="Contoh: 50"
                hint="Otomatis terisi saat terima barang dari pemasok ini. Tetap bisa diubah."
                :error="form.errors.pack_weight"
            />
            <BigButton type="submit" block size="large" :loading="form.processing">Simpan</BigButton>
        </form>
    </BottomSheet>

    <ConfirmDialog
        :open="!!removing"
        :title="`Hapus ${removing?.name ?? ''}?`"
        message="Riwayat belanja ke pemasok ini tetap tersimpan."
        confirm-text="Ya, Hapus"
        danger
        @update:open="(v) => !v && (removing = null)"
        @confirm="router.delete(route('suppliers.destroy', removing.id), { preserveScroll: true, onFinish: () => (removing = null) })"
    />
</template>
