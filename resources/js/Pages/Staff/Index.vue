<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { KeyRound, Pencil, Plus, Store, Trash2 } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

defineOptions({ layout: AppLayout });

defineProps({
    staff: { type: Array, required: true },
});

const toDelete = ref(null);
const deleting = ref(false);

function initials(name) {
    return name.split(' ').slice(0, 2).map((w) => w[0]).join('').toUpperCase();
}

function confirmDelete() {
    deleting.value = true;
    router.delete(route('staff.destroy', toDelete.value.id), {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = false;
            toDelete.value = null;
        },
    });
}
</script>

<template>
    <Head title="Karyawan" />
    <PageHeader title="Karyawan" subtitle="Orang-orang yang bekerja di usaha Anda." :back-href="route('more')" help="staff">
        <template #action>
            <BigButton :href="route('staff.create')" class="hidden md:inline-flex">
                <Plus :size="24" aria-hidden="true" />
                Tambah Karyawan
            </BigButton>
        </template>
    </PageHeader>

    <ul class="grid gap-4 md:grid-cols-2">
        <li v-for="person in staff" :key="person.id" class="card flex flex-col p-5">
            <div class="flex items-center gap-4">
                <span class="flex size-14 shrink-0 items-center justify-center rounded-full bg-primary-soft font-display text-xl font-extrabold text-primary-ink">
                    {{ initials(person.name) }}
                </span>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="font-display text-xl font-extrabold text-ink">{{ person.name }}</p>
                        <span v-if="person.is_self" class="rounded-full bg-info-soft px-3 text-base font-bold text-info-ink">Anda</span>
                    </div>
                    <p class="text-lg text-ink-soft">
                        {{ person.role_label }}<span v-if="person.job_title"> · {{ person.job_title }}</span>
                    </p>
                </div>
            </div>

            <p class="mt-3 flex items-start gap-2 text-lg text-ink-soft">
                <Store :size="22" class="mt-1 shrink-0" aria-hidden="true" />
                {{ person.outlet_names.join(', ') }}
            </p>
            <p v-if="!person.has_pin" class="mt-2 flex items-center gap-2 rounded-xl bg-warn-soft px-3 py-2 text-lg font-bold text-warn-ink">
                <KeyRound :size="22" aria-hidden="true" />
                Belum punya PIN
            </p>

            <div v-if="person.can.update || person.can.delete" class="mt-auto grid grid-cols-2 gap-3 pt-4">
                <BigButton v-if="person.can.update" :href="route('staff.edit', person.id)" variant="secondary">
                    <Pencil :size="22" aria-hidden="true" />
                    Ubah
                </BigButton>
                <BigButton v-if="person.can.delete" variant="ghost" class="text-danger-ink" @click="toDelete = person">
                    <Trash2 :size="22" aria-hidden="true" />
                    Hapus
                </BigButton>
            </div>
        </li>
    </ul>

    <BigButton :href="route('staff.create')" block size="large" class="mt-6 md:hidden">
        <Plus :size="28" aria-hidden="true" />
        Tambah Karyawan
    </BigButton>

    <ConfirmDialog
        :open="!!toDelete"
        :title="`Hapus ${toDelete?.name}?`"
        message="Karyawan ini tidak bisa masuk lagi. Riwayat kerjanya tetap tersimpan."
        confirm-text="Ya, Hapus"
        danger
        :loading="deleting"
        @update:open="(v) => !v && (toDelete = null)"
        @confirm="confirmDelete"
    />
</template>
