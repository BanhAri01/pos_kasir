<script setup>
/**
 * Pilihan & Tambahan: mis. "Ukuran" (Kecil +0, Besar +5.000), "Gula" (Normal, Sedikit, Tanpa),
 * "Topping" (boleh pilih banyak). Lalu pilih menu mana yang memakainya.
 */
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus, SlidersHorizontal, Trash2, X } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import BigInput from '@/Components/ui/BigInput.vue';
import BottomSheet from '@/Components/ui/BottomSheet.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import ToggleSwitch from '@/Components/ui/ToggleSwitch.vue';
import { formatRupiah } from '@/composables/useRupiah';

defineOptions({ layout: AppLayout });

const props = defineProps({
    groups: { type: Array, required: true },
    products: { type: Array, required: true },
});

const sheet = ref(false);
const editing = ref(null);
const removing = ref(null);
const productSearch = ref('');
const blank = () => ({ name: '', selection: 'single', is_required: false, max_select: null, options: [{ id: null, name: '', price_delta: 0, is_active: true }], product_ids: [] });
const form = useForm(blank());

const EXAMPLES = [
    { name: 'Ukuran', selection: 'single', is_required: true, options: [['Reguler', 0], ['Besar', 5000]] },
    { name: 'Gula', selection: 'single', is_required: false, options: [['Normal', 0], ['Sedikit', 0], ['Tanpa gula', 0]] },
    { name: 'Topping', selection: 'multiple', is_required: false, options: [['Boba', 4000], ['Keju', 5000]] },
];

function openAdd(example = null) {
    editing.value = null;
    Object.assign(form, blank());
    if (example) {
        Object.assign(form, { name: example.name, selection: example.selection, is_required: example.is_required, options: example.options.map(([name, price]) => ({ id: null, name, price_delta: price, is_active: true })) });
    }
    form.clearErrors();
    sheet.value = true;
}

function openEdit(group) {
    editing.value = group;
    Object.assign(form, { name: group.name, selection: group.selection, is_required: group.is_required, max_select: group.max_select, options: group.options.map((o) => ({ ...o })), product_ids: [...group.product_ids] });
    form.clearErrors();
    sheet.value = true;
}

function toggleProduct(id) {
    form.product_ids = form.product_ids.includes(id) ? form.product_ids.filter((x) => x !== id) : [...form.product_ids, id];
}

function save() {
    const options = { preserveScroll: true, onSuccess: () => (sheet.value = false) };
    editing.value ? form.put(route('modifiers.update', editing.value.id), options) : form.post(route('modifiers.store'), options);
}

const filteredProducts = () => props.products.filter((p) => p.name.toLowerCase().includes(productSearch.value.toLowerCase()));
</script>

<template>
    <Head title="Pilihan & Tambahan" />
    <PageHeader title="Pilihan & Tambahan" subtitle="Ukuran, level gula, topping, dan pilihan lain saat memesan." :back-href="route('products.index')">
        <template #action>
            <BigButton @click="openAdd()"><Plus :size="24" aria-hidden="true" /> Buat Pilihan</BigButton>
        </template>
    </PageHeader>

    <EmptyState v-if="!groups.length" title="Belum ada pilihan" message="Mulai dari contoh di bawah, lalu sesuaikan.">
        <template #icon><SlidersHorizontal :size="48" aria-hidden="true" /></template>
        <div class="flex flex-wrap justify-center gap-2">
            <BigButton v-for="ex in EXAMPLES" :key="ex.name" variant="soft" @click="openAdd(ex)">{{ ex.name }}</BigButton>
        </div>
    </EmptyState>

    <ul class="grid gap-3 md:grid-cols-2">
        <li v-for="g in groups" :key="g.id">
            <button type="button" class="card pressable flex w-full min-w-0 flex-col gap-2 p-4 text-left hover:ring-2 hover:ring-primary/30" @click="openEdit(g)">
                <span class="flex w-full items-start justify-between gap-2">
                    <span class="font-display text-xl font-extrabold text-ink">{{ g.name }}</span>
                    <span class="shrink-0 rounded-full bg-surface-2 px-3 py-1 text-sm font-bold text-ink">{{ g.selection === 'single' ? 'Pilih satu' : 'Boleh banyak' }}{{ g.is_required ? ' · wajib' : '' }}</span>
                </span>
                <span class="text-base text-ink">{{ g.options.map((o) => (o.price_delta ? `${o.name} (+${formatRupiah(o.price_delta)})` : o.name)).join(', ') }}</span>
                <span class="text-base text-ink-soft">{{ g.product_names.length ? `Dipakai di: ${g.product_names.slice(0, 4).join(', ')}${g.product_names.length > 4 ? ` +${g.product_names.length - 4} lagi` : ''}` : 'Belum dipakai di menu mana pun' }}</span>
            </button>
        </li>
    </ul>

    <BottomSheet v-model:open="sheet" :title="editing ? `Ubah ${editing.name}` : 'Buat Pilihan'">
        <form class="flex flex-col gap-5" @submit.prevent="save">
            <BigInput v-model="form.name" label="Nama pilihan" placeholder="Contoh: Ukuran, Gula, Topping" :error="form.errors.name" />
            <SegmentedControl v-model="form.selection" label="Cara memilih" :options="[{ value: 'single', label: 'Pilih satu' }, { value: 'multiple', label: 'Boleh banyak' }]" />
            <div class="flex items-center justify-between gap-3">
                <span class="text-lg font-bold text-ink">Wajib dipilih kasir</span>
                <ToggleSwitch v-model="form.is_required" label="Wajib dipilih" />
            </div>

            <div>
                <p class="mb-2 text-lg font-bold text-ink">Daftar pilihan</p>
                <p v-if="form.errors.options" class="mb-2 text-base font-bold text-danger-ink">{{ form.errors.options }}</p>
                <ul class="flex flex-col gap-3">
                    <li v-for="(o, i) in form.options" :key="i" class="grid grid-cols-[minmax(0,1fr)_minmax(0,9rem)_auto] items-end gap-2">
                        <BigInput v-model="o.name" :label="i === 0 ? 'Nama' : `Pilihan ${i + 1}`" placeholder="Contoh: Besar" :error="form.errors[`options.${i}.name`]" />
                        <MoneyInput v-model="o.price_delta" label="Tambah harga" />
                        <button type="button" class="pressable mb-1 flex size-touch items-center justify-center rounded-xl text-danger-ink disabled:opacity-30" :disabled="form.options.length === 1" :aria-label="`Hapus pilihan ${o.name}`" @click="form.options.splice(i, 1)">
                            <X :size="24" />
                        </button>
                    </li>
                </ul>
                <BigButton variant="ghost" class="mt-2" @click="form.options.push({ id: null, name: '', price_delta: 0, is_active: true })"><Plus :size="22" aria-hidden="true" /> Tambah Pilihan</BigButton>
            </div>

            <div>
                <p class="mb-2 text-lg font-bold text-ink">Dipakai di menu ({{ form.product_ids.length }})</p>
                <input v-model="productSearch" type="search" placeholder="Cari menu" class="mb-3 min-h-touch w-full rounded-2xl border-2 border-line bg-surface px-4 text-lg text-ink focus:border-focus focus:outline-none" />
                <div class="flex max-h-64 flex-wrap gap-2 overflow-y-auto">
                    <button
                        v-for="p in filteredProducts()"
                        :key="p.id"
                        type="button"
                        class="pressable min-h-12 rounded-xl border-2 px-3 text-base font-bold"
                        :class="form.product_ids.includes(p.id) ? 'border-primary bg-primary-soft text-primary-ink' : 'border-line text-ink'"
                        :aria-pressed="form.product_ids.includes(p.id)"
                        @click="toggleProduct(p.id)"
                    >
                        {{ p.name }}
                    </button>
                </div>
            </div>

            <BigButton type="submit" block size="large" :loading="form.processing">Simpan</BigButton>
            <BigButton v-if="editing" variant="ghost" block class="text-danger-ink" @click="removing = editing"><Trash2 :size="22" aria-hidden="true" /> Hapus pilihan ini</BigButton>
        </form>
    </BottomSheet>

    <ConfirmDialog
        :open="!!removing"
        :title="`Hapus ${removing?.name ?? ''}?`"
        message="Pilihan ini hilang dari semua menu. Transaksi lama tetap tersimpan."
        confirm-text="Ya, Hapus"
        danger
        @update:open="(v) => !v && (removing = null)"
        @confirm="router.delete(route('modifiers.destroy', removing.id), { preserveScroll: true, onSuccess: () => { removing = null; sheet = false; } })"
    />
</template>
