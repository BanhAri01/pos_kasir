<script setup>
/**
 * Promo & diskon musiman. Selama tanggalnya, kasir otomatis memakai harga promo (juga saat offline).
 * Kalau satu barang kena beberapa promo, dipakai potongan yang paling besar.
 */
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Tags, Trash2 } from 'lucide-vue-next';
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
    promotions: { type: Array, required: true },
    categories: { type: Array, required: true },
    products: { type: Array, required: true },
    today: { type: String, required: true },
});

const sheet = ref(false);
const editing = ref(null);
const removing = ref(null);
const productQuery = ref('');

const blank = () => ({ name: '', type: 'percent', value: '', scope: 'all', category_ids: [], product_ids: [], starts_on: props.today, ends_on: props.today, is_active: true });
const form = useForm(blank());

function open(promo = null) {
    editing.value = promo;
    Object.assign(form, promo ? { ...promo, value: promo.type === 'percent' ? String(promo.value) : promo.value, category_ids: [...promo.category_ids], product_ids: [...promo.product_ids] } : blank());
    form.clearErrors();
    productQuery.value = '';
    sheet.value = true;
}

function toggle(list, id) {
    const i = list.indexOf(id);
    i === -1 ? list.push(id) : list.splice(i, 1);
}

function save() {
    const options = { preserveScroll: true, onSuccess: () => (sheet.value = false) };
    editing.value ? form.put(route('promotions.update', editing.value.id), options) : form.post(route('promotions.store'), options);
}

const statusLabel = { running: 'Sedang berlaku', upcoming: 'Akan datang', ended: 'Sudah selesai', off: 'Dimatikan' };
const statusClass = { running: 'bg-primary-soft text-primary-ink', upcoming: 'bg-info-soft text-info-ink', ended: 'bg-surface-2 text-ink-soft', off: 'bg-surface-2 text-ink-soft' };
const chip = (active) => ['pressable min-h-12 rounded-2xl border-2 px-4 text-base font-bold', active ? 'border-primary bg-primary-soft text-primary-ink' : 'border-line text-ink'];
</script>

<template>
    <Head title="Promo & Diskon" />
    <div class="mx-auto max-w-3xl">
        <PageHeader title="Promo & Diskon" subtitle="Harga promo otomatis dipakai di kasir selama tanggalnya." :back-href="route('products.index')">
            <template #action>
                <BigButton @click="open()"><Plus :size="24" aria-hidden="true" /> Promo Baru</BigButton>
            </template>
        </PageHeader>

        <EmptyState v-if="!promotions.length" title="Belum ada promo" message="Contoh: Diskon Lebaran 20% untuk semua kaos, 1-10 April.">
            <template #icon><Tags :size="48" aria-hidden="true" /></template>
        </EmptyState>

        <ul class="grid gap-3">
            <li v-for="p in promotions" :key="p.id" class="card flex items-start justify-between gap-3 p-4">
                <div class="min-w-0">
                    <span class="mb-1 inline-block rounded-lg px-2 text-base font-bold" :class="statusClass[p.status]">{{ statusLabel[p.status] }}</span>
                    <p class="text-xl font-extrabold text-ink">{{ p.name }}</p>
                    <p class="text-lg font-bold text-primary-ink">Potong {{ p.type === 'percent' ? `${p.value}%` : formatRupiah(p.value) }}</p>
                    <p class="text-base text-ink-soft">{{ p.targets }} · {{ p.period }}</p>
                </div>
                <span class="flex shrink-0">
                    <button type="button" class="pressable flex size-touch items-center justify-center rounded-xl text-ink-soft" :aria-label="`Ubah ${p.name}`" @click="open(p)"><Pencil :size="22" /></button>
                    <button type="button" class="pressable flex size-touch items-center justify-center rounded-xl text-danger-ink" :aria-label="`Hapus ${p.name}`" @click="removing = p"><Trash2 :size="22" /></button>
                </span>
            </li>
        </ul>
    </div>

    <BottomSheet v-model:open="sheet" :title="editing ? 'Ubah Promo' : 'Promo Baru'">
        <form class="flex flex-col gap-4" @submit.prevent="save">
            <BigInput v-model="form.name" label="Nama promo" placeholder="Contoh: Diskon Lebaran" :error="form.errors.name" />
            <SegmentedControl v-model="form.type" label="Jenis potongan" :options="[{ value: 'percent', label: 'Persen (%)' }, { value: 'amount', label: 'Rupiah (Rp)' }]" />
            <BigInput v-if="form.type === 'percent'" v-model="form.value" label="Potongan (%)" inputmode="decimal" placeholder="Contoh: 20" :error="form.errors.value" />
            <MoneyInput v-else v-model="form.value" label="Potongan per barang" :error="form.errors.value" />

            <div>
                <span class="mb-2 block text-lg font-bold text-ink">Berlaku untuk</span>
                <SegmentedControl v-model="form.scope" label="Berlaku untuk" :options="[{ value: 'all', label: 'Semua' }, { value: 'categories', label: 'Kategori' }, { value: 'products', label: 'Barang' }]" />
            </div>
            <div v-if="form.scope === 'categories'" class="flex flex-wrap gap-2">
                <button v-for="c in categories" :key="c.id" type="button" :class="chip(form.category_ids.includes(c.id))" :aria-pressed="form.category_ids.includes(c.id)" @click="toggle(form.category_ids, c.id)">{{ c.name }}</button>
                <p v-if="form.errors.category_ids" role="alert" class="w-full text-base font-bold text-danger-ink">{{ form.errors.category_ids }}</p>
            </div>
            <div v-if="form.scope === 'products'" class="flex flex-col gap-2">
                <input v-model="productQuery" type="search" placeholder="Cari barang" aria-label="Cari barang" class="min-h-touch rounded-2xl border-2 border-line bg-surface px-4 text-lg text-ink" />
                <div class="flex max-h-64 flex-wrap gap-2 overflow-y-auto">
                    <button
                        v-for="p in products.filter((p) => form.product_ids.includes(p.id) || (productQuery && p.name.toLowerCase().includes(productQuery.toLowerCase())))"
                        :key="p.id"
                        type="button"
                        :class="chip(form.product_ids.includes(p.id))"
                        :aria-pressed="form.product_ids.includes(p.id)"
                        @click="toggle(form.product_ids, p.id)"
                    >
                        {{ p.name }}
                    </button>
                </div>
                <p class="text-base text-ink-soft">Model baju yang dipilih: semua ukuran & warnanya ikut promo.</p>
                <p v-if="form.errors.product_ids" role="alert" class="text-base font-bold text-danger-ink">{{ form.errors.product_ids }}</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <BigInput v-model="form.starts_on" label="Mulai" type="date" :error="form.errors.starts_on" />
                <BigInput v-model="form.ends_on" label="Selesai" type="date" :error="form.errors.ends_on" />
            </div>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                <span class="min-w-48 flex-1 text-lg text-ink">Promo aktif</span>
                <ToggleSwitch v-model="form.is_active" label="Promo aktif" class="ml-auto" />
            </div>
            <BigButton type="submit" block size="large" :loading="form.processing">Simpan Promo</BigButton>
        </form>
    </BottomSheet>

    <ConfirmDialog
        :open="!!removing"
        :title="`Hapus promo ${removing?.name ?? ''}?`"
        message="Penjualan yang sudah memakai promo ini tidak berubah."
        confirm-text="Ya, Hapus"
        danger
        @update:open="(v) => !v && (removing = null)"
        @confirm="router.delete(route('promotions.destroy', removing.id), { preserveScroll: true, onFinish: () => (removing = null) })"
    />
</template>
