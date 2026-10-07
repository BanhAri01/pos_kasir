<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { MapPin, Pencil, Plus, Trash2, Users } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    outlets: { type: Array, required: true },
});

const toDelete = ref(null);
const deleting = ref(false);

function confirmDelete() {
    deleting.value = true;
    router.delete(route('outlets.destroy', toDelete.value.id), {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = false;
            toDelete.value = null;
        },
    });
}
</script>

<template>
    <Head title="Outlet" />
    <PageHeader title="Outlet / Cabang" subtitle="Tempat usaha Anda berjualan." :back-href="route('more')" help="outlets">
        <template #action>
            <BigButton :href="route('outlets.create')" class="hidden md:inline-flex">
                <Plus :size="24" aria-hidden="true" />
                Tambah Outlet
            </BigButton>
        </template>
    </PageHeader>

    <ul class="grid gap-4 md:grid-cols-2">
        <li v-for="outlet in outlets" :key="outlet.id" class="card flex flex-col p-5">
            <p class="font-display text-xl font-extrabold text-ink">
                {{ outlet.name }}
                <span v-if="outlet.is_warehouse" class="ml-1 rounded-lg bg-accent-soft px-2 py-0.5 align-middle text-base font-bold text-accent-ink">Gudang</span>
            </p>
            <p v-if="outlet.address" class="mt-2 flex items-start gap-2 text-lg text-ink-soft">
                <MapPin :size="22" class="mt-1 shrink-0" aria-hidden="true" />
                {{ outlet.address }}
            </p>
            <p class="mt-1 flex items-center gap-2 text-lg text-ink-soft">
                <Users :size="22" class="shrink-0" aria-hidden="true" />
                {{ outlet.staff_count }} orang bertugas di sini
            </p>

            <div class="mt-auto grid grid-cols-2 gap-3 pt-4">
                <BigButton :href="route('outlets.edit', outlet.id)" variant="secondary">
                    <Pencil :size="22" aria-hidden="true" />
                    Ubah
                </BigButton>
                <BigButton v-if="props.outlets.length > 1" variant="ghost" class="text-danger-ink" @click="toDelete = outlet">
                    <Trash2 :size="22" aria-hidden="true" />
                    Hapus
                </BigButton>
            </div>
        </li>
    </ul>

    <!-- Di HP tombol tambah ada di bawah daftar, dekat jempol -->
    <BigButton :href="route('outlets.create')" block size="large" class="mt-6 md:hidden">
        <Plus :size="28" aria-hidden="true" />
        Tambah Outlet
    </BigButton>

    <ConfirmDialog
        :open="!!toDelete"
        :title="`Hapus outlet ${toDelete?.name}?`"
        message="Outlet ini tidak akan tampil lagi. Anda masih bisa membatalkan sesaat setelah menghapus."
        confirm-text="Ya, Hapus"
        danger
        :loading="deleting"
        @update:open="(v) => !v && (toDelete = null)"
        @confirm="confirmDelete"
    />
</template>
