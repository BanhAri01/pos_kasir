<script setup>
/** Kategori: tambah, ganti nama, hapus. Semua di satu halaman. */
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Check, Pencil, Plus, Tags, Trash2, X } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import BigInput from '@/Components/ui/BigInput.vue';
import CatalogTabs from '@/Components/CatalogTabs.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

defineOptions({ layout: AppLayout });

defineProps({
    categories: { type: Array, required: true },
});

const form = useForm({ name: '' });
const editing = ref(null);
const editName = ref('');
const toDelete = ref(null);

function add() {
    form.post(route('categories.store'), { preserveScroll: true, onSuccess: () => form.reset() });
}

function startEdit(category) {
    editing.value = category.id;
    editName.value = category.name;
}

function saveEdit(category) {
    router.put(route('categories.update', category.id), { name: editName.value }, {
        preserveScroll: true,
        onSuccess: () => (editing.value = null),
    });
}

function remove() {
    router.delete(route('categories.destroy', toDelete.value.id), {
        preserveScroll: true,
        onFinish: () => (toDelete.value = null),
    });
}
</script>

<template>
    <Head title="Kategori" />
    <PageHeader title="Kategori" subtitle="Kelompokkan barang supaya mudah dicari." help="categories" />
    <CatalogTabs />

    <form class="card mb-6 flex flex-col gap-3 p-5 sm:flex-row sm:items-end" @submit.prevent="add">
        <BigInput v-model="form.name" label="Kategori baru" placeholder="Contoh: Minuman Dingin" :error="form.errors.name" class="flex-1" />
        <BigButton type="submit" :loading="form.processing" class="sm:mb-0">
            <Plus :size="24" aria-hidden="true" /> Tambah
        </BigButton>
    </form>

    <ul v-if="categories.length" class="grid gap-3 md:grid-cols-2">
        <li v-for="category in categories" :key="category.id" class="card flex flex-wrap items-center gap-3 p-4">
            <template v-if="editing === category.id">
                <input
                    v-model="editName"
                    :aria-label="`Nama baru untuk ${category.name}`"
                    class="min-h-touch min-w-0 flex-1 rounded-2xl border-2 border-focus bg-surface px-4 text-lg text-ink focus:outline-none"
                    @keydown.enter.prevent="saveEdit(category)"
                />
                <BigButton @click="saveEdit(category)"><Check :size="22" aria-hidden="true" /> Simpan</BigButton>
                <BigButton variant="ghost" @click="editing = null"><X :size="22" aria-hidden="true" /> Batal</BigButton>
            </template>
            <template v-else>
                <div class="min-w-40 flex-1">
                    <p class="font-display text-lg font-extrabold text-ink">{{ category.name }}</p>
                    <p class="text-base text-ink-soft">{{ category.products_count }} barang</p>
                </div>
                <BigButton variant="secondary" @click="startEdit(category)"><Pencil :size="20" aria-hidden="true" /> Ganti Nama</BigButton>
                <BigButton variant="ghost" class="text-danger-ink" :aria-label="`Hapus kategori ${category.name}`" @click="toDelete = category">
                    <Trash2 :size="22" aria-hidden="true" />
                </BigButton>
            </template>
        </li>
    </ul>

    <EmptyState v-else title="Belum ada kategori" message="Kategori membantu barang tersusun rapi di layar kasir.">
        <template #icon><Tags :size="48" /></template>
    </EmptyState>

    <ConfirmDialog
        :open="!!toDelete"
        :title="`Hapus kategori ${toDelete?.name}?`"
        message="Barang di kategori ini tidak ikut terhapus. Barangnya akan menjadi tanpa kategori."
        confirm-text="Ya, Hapus"
        danger
        @update:open="(v) => !v && (toDelete = null)"
        @confirm="remove"
    />
</template>
