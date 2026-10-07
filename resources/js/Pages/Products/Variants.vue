<script setup>
/**
 * Atur ukuran & warna satu model.
 *   1. Pilih ukuran (tombol cepat S-XXL / 28-36, atau ketik sendiri)
 *   2. Pilih warna (ketik nama warna)
 *   3. Semua kombinasi otomatis muncul di tabel: SKU, barcode, harga, stok awal, batas menipis.
 */
import { computed, ref, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Plus, X } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import ToggleSwitch from '@/Components/ui/ToggleSwitch.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    product: { type: Object, required: true },
    variants: { type: Array, required: true },
});

const PRESETS = {
    Ukuran: [
        ['S', 'M', 'L', 'XL', 'XXL'],
        ['28', '29', '30', '31', '32', '33', '34', '36'],
        ['All size'],
    ],
    Warna: [['Hitam', 'Putih', 'Navy', 'Abu-abu', 'Merah', 'Krem']],
};

const initial = props.product.options.length ? props.product.options : [{ name: 'Ukuran', values: [] }, { name: 'Warna', values: [] }];
const options = ref(initial.map((o) => ({ name: o.name, values: [...o.values], draft: '' })));
const bulkPrice = ref(null);

const form = useForm({ options: [], rows: [], auto_barcode: true });

const keyOf = (values) => options.value.map((o) => `${o.name}=${values[o.name] ?? ''}`).join('|').toLowerCase();
const existingByKey = Object.fromEntries(props.variants.map((v) => [keyOf(v.values), v]));

/** Semua kombinasi dari pilihan yang terisi. */
const combinations = computed(() => {
    const dims = options.value.filter((o) => o.name.trim() && o.values.length);
    if (!dims.length) return [];
    return dims.reduce((acc, dim) => acc.flatMap((partial) => dim.values.map((v) => ({ ...partial, [dim.name.trim()]: v }))), [{}]);
});

// Baris tabel mengikuti kombinasi; isian yang sudah diketik tetap dipertahankan.
const rows = ref([]);
watch(
    combinations,
    (combos) => {
        const previous = Object.fromEntries(rows.value.map((r) => [keyOf(r.values), r]));
        rows.value = combos.map((values) => {
            const key = keyOf(values);
            const existing = existingByKey[key];
            return previous[key] ?? {
                values,
                id: existing?.id ?? null,
                code: existing?.code ?? '',
                barcode: existing?.barcode ?? '',
                price: existing?.price ?? props.product.price,
                min_stock: existing?.min_stock ?? '1',
                initial_stock: '',
                stock: existing?.stock ?? null,
            };
        });
    },
    { immediate: true, deep: true },
);

const removed = computed(() => props.variants.filter((v) => v.is_active && !rows.value.some((r) => r.id === v.id)));

function addValue(option, value = option.draft) {
    for (const v of String(value).split(',').map((s) => s.trim()).filter(Boolean)) {
        if (!option.values.some((x) => x.toLowerCase() === v.toLowerCase())) option.values.push(v);
    }
    option.draft = '';
}

function applyBulkPrice() {
    if (bulkPrice.value === null) return;
    rows.value.forEach((r) => (r.price = bulkPrice.value));
}

function save() {
    form.transform(() => ({
        options: options.value.filter((o) => o.name.trim() && o.values.length).map((o) => ({ name: o.name.trim(), values: o.values })),
        rows: rows.value.map((r) => ({ values: r.values, code: r.code, barcode: r.barcode, price: Number(r.price) || 0, min_stock: r.min_stock, initial_stock: r.id ? null : r.initial_stock })),
        auto_barcode: form.auto_barcode,
    })).put(route('products.variants.update', props.product.id));
}

const label = (values) => Object.values(values).join(' / ');
const cell = 'min-h-12 w-full rounded-xl border-2 border-line bg-surface px-3 text-lg text-ink focus:border-focus focus:outline-none';
</script>

<template>
    <Head :title="`Varian ${product.name}`" />
    <div class="mx-auto max-w-5xl">
        <PageHeader :title="product.name" subtitle="Ukuran & warna. Setiap kombinasi punya stok, harga, dan barcode sendiri." :back-href="route('products.edit', product.id)" />

        <form class="flex flex-col gap-5" @submit.prevent="save">
            <!-- Pilihan -->
            <section v-for="(option, i) in options" :key="i" class="card flex flex-col gap-3 p-5">
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-xl font-extrabold text-ink"><span class="text-ink-soft">{{ i + 1 }}.</span></h2>
                    <input v-model="option.name" :aria-label="`Nama pilihan ${i + 1}`" class="min-h-12 min-w-0 flex-1 rounded-xl border-2 border-line bg-surface px-3 text-xl font-extrabold text-ink focus:border-focus focus:outline-none" :placeholder="i === 0 ? 'Ukuran' : 'Warna'" />
                </div>

                <div v-if="PRESETS[option.name]" class="flex flex-wrap gap-2">
                    <button v-for="(preset, p) in PRESETS[option.name]" :key="p" type="button" class="pressable min-h-12 rounded-xl bg-surface-2 px-3 text-base font-bold text-ink" @click="addValue(option, preset.join(','))">
                        + {{ preset.join(' ') }}
                    </button>
                </div>

                <div class="flex flex-wrap gap-2">
                    <span v-for="(v, j) in option.values" :key="v" class="flex min-h-12 items-center gap-1 rounded-xl border-2 border-primary bg-primary-soft pl-3 text-lg font-extrabold text-primary-ink">
                        {{ v }}
                        <button type="button" class="pressable flex size-11 items-center justify-center rounded-xl" :aria-label="`Hapus ${v}`" @click="option.values.splice(j, 1)"><X :size="20" /></button>
                    </span>
                    <span v-if="!option.values.length" class="text-lg text-ink-soft">Belum ada. Ketuk tombol cepat atau ketik di bawah.</span>
                </div>

                <div class="flex gap-2">
                    <input v-model="option.draft" :aria-label="`Tambah ${option.name || 'nilai'}`" :placeholder="i === 0 ? 'Ketik ukuran, mis. 3XL' : 'Ketik warna, mis. Hijau Botol'" :class="cell" @keydown.enter.prevent="addValue(option)" />
                    <BigButton type="button" variant="secondary" :disabled="!option.draft.trim()" @click="addValue(option)"><Plus :size="22" aria-hidden="true" /> Tambah</BigButton>
                </div>
            </section>
            <p v-if="form.errors.options" role="alert" class="text-lg font-bold text-danger-ink">{{ form.errors.options }}</p>

            <!-- Tabel varian -->
            <section class="card flex flex-col gap-4 p-5">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <h2 class="text-xl font-extrabold text-ink">{{ rows.length }} varian</h2>
                    <div class="flex items-end gap-2">
                        <MoneyInput v-model="bulkPrice" label="Samakan harga semua" optional />
                        <BigButton type="button" variant="secondary" :disabled="bulkPrice === null" @click="applyBulkPrice">Terapkan</BigButton>
                    </div>
                </div>
                <p v-if="form.errors.rows" role="alert" class="text-lg font-bold text-danger-ink">{{ form.errors.rows }}</p>

                <div v-if="rows.length" class="overflow-x-auto">
                    <table class="w-full min-w-max text-left">
                        <thead>
                            <tr class="border-b border-line text-base text-ink-soft">
                                <th scope="col" class="px-2 py-2 font-bold">Varian</th>
                                <th scope="col" class="px-2 py-2 font-bold">Harga</th>
                                <th scope="col" class="px-2 py-2 font-bold">Stok</th>
                                <th scope="col" class="px-2 py-2 font-bold">Tanda menipis</th>
                                <th scope="col" class="px-2 py-2 font-bold">SKU</th>
                                <th scope="col" class="px-2 py-2 font-bold">Barcode</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            <tr v-for="(r, i) in rows" :key="keyOf(r.values)">
                                <th scope="row" class="px-2 py-2 text-lg font-extrabold whitespace-nowrap text-ink">
                                    {{ label(r.values) }}
                                    <span v-if="!r.id" class="block text-base font-bold text-primary-ink">baru</span>
                                </th>
                                <td class="w-40 px-2 py-2">
                                    <input v-model.number="r.price" type="number" min="0" inputmode="numeric" :aria-label="`Harga ${label(r.values)}`" :class="cell" />
                                    <span v-if="form.errors[`rows.${i}.price`]" role="alert" class="text-base font-bold text-danger-ink">{{ form.errors[`rows.${i}.price`] }}</span>
                                </td>
                                <td class="w-28 px-2 py-2">
                                    <input v-if="!r.id" v-model="r.initial_stock" inputmode="decimal" placeholder="0" :aria-label="`Stok awal ${label(r.values)}`" :class="cell" />
                                    <span v-else class="text-lg font-bold text-ink tabular-nums">{{ r.stock }}</span>
                                </td>
                                <td class="w-28 px-2 py-2"><input v-model="r.min_stock" inputmode="decimal" :aria-label="`Tanda menipis ${label(r.values)}`" :class="cell" /></td>
                                <td class="w-44 px-2 py-2"><input v-model="r.code" placeholder="otomatis" :aria-label="`SKU ${label(r.values)}`" :class="cell" /></td>
                                <td class="w-48 px-2 py-2"><input v-model="r.barcode" inputmode="numeric" placeholder="otomatis / scan" :aria-label="`Barcode ${label(r.values)}`" :class="cell" /></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="text-lg text-ink-soft">Isi ukuran dan/atau warna di atas, kombinasinya muncul di sini.</p>

                <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                    <span class="min-w-48 flex-1">
                        <span class="block text-lg text-ink">Buat barcode otomatis</span>
                        <span class="block text-base text-ink-soft">Untuk varian yang barcodenya dikosongkan, supaya labelnya bisa dicetak.</span>
                    </span>
                    <ToggleSwitch v-model="form.auto_barcode" label="Buat barcode otomatis" class="ml-auto" />
                </div>

                <p v-if="removed.length" class="rounded-2xl bg-warn-soft px-4 py-3 text-base font-bold text-warn-ink">
                    {{ removed.length }} varian lama tidak ada di tabel dan akan disembunyikan dari kasir. Riwayat penjualannya tetap tersimpan.
                </p>
            </section>

            <BigButton type="submit" block size="large" :loading="form.processing" :disabled="!rows.length">Simpan {{ rows.length }} Varian</BigButton>
        </form>
    </div>
</template>
