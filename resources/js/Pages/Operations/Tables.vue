<script setup>
/** Daftar meja: tambah satu-satu atau sekaligus (Meja 1 s/d 10), ubah nama/area, nonaktifkan. */
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Armchair, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import BigInput from '@/Components/ui/BigInput.vue';
import BottomSheet from '@/Components/ui/BottomSheet.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import ToggleSwitch from '@/Components/ui/ToggleSwitch.vue';

defineOptions({ layout: AppLayout });

defineProps({ tables: { type: Array, required: true } });

const sheet = ref(false);
const mode = ref('many');
const editing = ref(null);
const removing = ref(null);
const form = useForm({ name: '', area: '', capacity: null, count: 10, is_active: true });

function openAdd() {
    editing.value = null;
    form.reset();
    mode.value = 'many';
    sheet.value = true;
}

function openEdit(table) {
    editing.value = table;
    Object.assign(form, { name: table.name, area: table.area ?? '', capacity: table.capacity, is_active: table.is_active, count: null });
    sheet.value = true;
}

function save() {
    const options = { preserveScroll: true, onSuccess: () => (sheet.value = false) };
    if (editing.value) {
        form.transform((d) => ({ name: d.name, area: d.area, capacity: d.capacity, is_active: d.is_active })).put(route('tables.update', editing.value.id), options);
    } else {
        form.transform((d) => (mode.value === 'many' ? { count: d.count, area: d.area, capacity: d.capacity } : { name: d.name, area: d.area, capacity: d.capacity })).post(route('tables.store'), options);
    }
}

function toggle(table, value) {
    router.put(route('tables.update', table.id), { name: table.name, area: table.area, capacity: table.capacity, is_active: value }, { preserveScroll: true });
}
</script>

<template>
    <Head title="Meja" />
    <PageHeader title="Meja" subtitle="Meja yang bisa dipilih kasir untuk makan di tempat.">
        <template #action>
            <BigButton @click="openAdd"><Plus :size="24" aria-hidden="true" /> Tambah Meja</BigButton>
        </template>
    </PageHeader>

    <EmptyState v-if="!tables.length" title="Belum ada meja" message="Tambahkan meja supaya pesanan bisa disimpan per meja dan dibayar belakangan.">
        <template #icon><Armchair :size="48" aria-hidden="true" /></template>
        <BigButton @click="openAdd">Buat Meja Sekarang</BigButton>
    </EmptyState>

    <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
        <li v-for="t in tables" :key="t.id" class="card flex min-w-0 flex-col gap-2 p-4" :class="t.is_active ? '' : 'opacity-60'">
            <p class="truncate font-display text-xl font-extrabold text-ink">{{ t.name }}</p>
            <p class="truncate text-base text-ink-soft">{{ t.area || 'Tanpa area' }}<template v-if="t.capacity"> · {{ t.capacity }} kursi</template></p>
            <div class="mt-auto flex items-center justify-between gap-2">
                <ToggleSwitch :model-value="t.is_active" :label="`${t.name} aktif`" @update:model-value="(v) => toggle(t, v)" />
                <span class="flex">
                    <button type="button" class="pressable flex size-touch items-center justify-center rounded-xl text-ink-soft" :aria-label="`Ubah ${t.name}`" @click="openEdit(t)"><Pencil :size="22" /></button>
                    <button type="button" class="pressable flex size-touch items-center justify-center rounded-xl text-danger-ink" :aria-label="`Hapus ${t.name}`" @click="removing = t"><Trash2 :size="22" /></button>
                </span>
            </div>
        </li>
    </ul>

    <BottomSheet v-model:open="sheet" :title="editing ? `Ubah ${editing.name}` : 'Tambah Meja'">
        <form class="flex flex-col gap-4" @submit.prevent="save">
            <SegmentedControl v-if="!editing" v-model="mode" label="Cara menambah" :options="[{ value: 'many', label: 'Sekaligus banyak' }, { value: 'one', label: 'Satu meja' }]" />
            <BigInput v-if="!editing && mode === 'many'" v-model="form.count" label="Jumlah meja" type="number" inputmode="numeric" hint="Nama dibuat otomatis: Meja 1, Meja 2, dst." :error="form.errors.count" />
            <BigInput v-else v-model="form.name" label="Nama meja" placeholder="Contoh: Meja 5, Lesehan A" :error="form.errors.name" />
            <BigInput v-model="form.area" label="Area" optional placeholder="Contoh: Lantai 2, Teras" :error="form.errors.area" />
            <BigInput v-model="form.capacity" label="Jumlah kursi" type="number" inputmode="numeric" optional :error="form.errors.capacity" />
            <BigButton type="submit" block size="large" :loading="form.processing">Simpan</BigButton>
        </form>
    </BottomSheet>

    <ConfirmDialog
        :open="!!removing"
        :title="`Hapus ${removing?.name ?? ''}?`"
        message="Transaksi lama di meja ini tetap tersimpan."
        confirm-text="Ya, Hapus"
        danger
        @update:open="(v) => !v && (removing = null)"
        @confirm="router.delete(route('tables.destroy', removing.id), { preserveScroll: true, onFinish: () => (removing = null) })"
    />
</template>
