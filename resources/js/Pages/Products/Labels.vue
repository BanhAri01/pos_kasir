<script setup>
/**
 * Cetak label barcode:
 *   1. Pilih barang / varian dan jumlah label (bisa "sebanyak stok")
 *   2. Pilih kertas: printer label thermal (1 label per lembar) atau kertas stiker A4 (3 x 8)
 *   3. Ketuk Cetak -> menu cetak HP/laptop terbuka
 */
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Minus, Plus, Printer, Search, X } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import BarcodeSvg from '@/Components/BarcodeSvg.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import ToggleSwitch from '@/Components/ui/ToggleSwitch.vue';
import { formatRupiah } from '@/composables/useRupiah';

defineOptions({ layout: AppLayout });

const props = defineProps({
    products: { type: Array, required: true },
    shopName: { type: String, required: true },
    preselect: { type: Array, default: () => [] },
});

const LAYOUTS = {
    thermal: { label: 'Printer label (50 x 30 mm)', page: '50mm 30mm', perPage: 1 },
    a4: { label: 'Stiker A4 (3 x 8)', page: 'A4', perPage: 24 },
};

const layout = ref('thermal');
const showPrice = ref(true);
const showShop = ref(false);
const query = ref('');
const picked = ref(props.products.filter((p) => props.preselect.includes(p.id)).map((p) => ({ id: p.id, qty: Math.max(1, p.stock) })));

const productById = (id) => props.products.find((p) => p.id === id);
const matches = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return [];
    return props.products
        .filter((p) => !picked.value.some((x) => x.id === p.id) && (p.name.toLowerCase().includes(q) || p.barcode === q || p.code?.toLowerCase() === q))
        .slice(0, 10);
});

function add(product) {
    picked.value.push({ id: product.id, qty: 1 });
    query.value = '';
}

/** Isi label: barcode, atau SKU kalau belum ada barcode. */
const codeOf = (p) => p.barcode || p.code || null;
const missing = computed(() => picked.value.map((x) => productById(x.id)).filter((p) => !codeOf(p)));

const labels = computed(() =>
    picked.value.flatMap((x) => {
        const p = productById(x.id);
        return codeOf(p) ? Array.from({ length: Math.max(0, Math.min(500, x.qty)) }, () => p) : [];
    }),
);
const pages = computed(() => {
    const per = LAYOUTS[layout.value].perPage;
    const out = [];
    for (let i = 0; i < labels.value.length; i += per) out.push(labels.value.slice(i, i + per));
    return out;
});

function generate() {
    router.post(route('labels.generate'), { product_ids: missing.value.map((p) => p.id) }, { preserveScroll: true, preserveState: true });
}

function printLabels() {
    window.print();
}

const printCss = computed(() => `@page { size: ${LAYOUTS[layout.value].page}; margin: 0; }`);
</script>

<template>
    <Head title="Cetak Label Barcode" />
    <component :is="'style'">{{ printCss }}</component>

    <div class="mx-auto max-w-3xl print:hidden">
        <PageHeader title="Cetak Label Barcode" subtitle="Tempel di barang supaya kasir tinggal scan." :back-href="route('products.index')" />

        <section class="card mb-5 p-5">
            <h2 class="mb-3 text-xl font-extrabold text-ink"><span class="text-ink-soft">1.</span> Pilih barang</h2>
            <div class="relative mb-4">
                <label class="flex min-h-touch items-center gap-3 rounded-2xl border-2 border-line bg-surface px-4 focus-within:border-focus">
                    <Search :size="22" class="shrink-0 text-ink-soft" aria-hidden="true" />
                    <span class="sr-only">Cari barang</span>
                    <input v-model="query" type="search" placeholder="Ketik nama, SKU, atau scan barcode" class="min-w-0 flex-1 bg-transparent text-lg text-ink focus:outline-none" />
                </label>
                <ul v-if="matches.length" class="absolute inset-x-0 top-full z-10 mt-1 overflow-hidden rounded-2xl border-2 border-line bg-surface shadow-float">
                    <li v-for="p in matches" :key="p.id">
                        <button type="button" class="flex min-h-touch w-full items-center justify-between gap-3 px-4 text-left text-lg hover:bg-surface-2" @click="add(p)">
                            <span class="min-w-0 truncate font-bold text-ink">{{ p.name }}</span>
                            <span class="shrink-0 text-base text-ink-soft">stok {{ p.stock }}</span>
                        </button>
                    </li>
                </ul>
            </div>

            <p v-if="!picked.length" class="text-lg text-ink-soft">Belum ada barang. Cari lalu ketuk nama barangnya.</p>
            <ul class="divide-y divide-line">
                <li v-for="(x, i) in picked" :key="x.id" class="flex flex-wrap items-center gap-3 py-3">
                    <span class="min-w-0 flex-1">
                        <span class="block text-lg font-bold text-ink">{{ productById(x.id).name }}</span>
                        <span class="block text-base" :class="codeOf(productById(x.id)) ? 'text-ink-soft' : 'font-bold text-danger-ink'">{{ codeOf(productById(x.id)) ?? 'Belum ada barcode' }}</span>
                    </span>
                    <span class="flex items-center gap-2">
                        <button type="button" class="pressable flex size-12 items-center justify-center rounded-xl bg-surface-2" :aria-label="`Kurangi label ${productById(x.id).name}`" @click="x.qty = Math.max(1, x.qty - 1)"><Minus :size="22" /></button>
                        <input v-model.number="x.qty" type="number" min="1" inputmode="numeric" :aria-label="`Jumlah label ${productById(x.id).name}`" class="h-12 w-20 rounded-xl border-2 border-line bg-surface text-center text-xl font-extrabold text-ink" />
                        <button type="button" class="pressable flex size-12 items-center justify-center rounded-xl bg-surface-2" :aria-label="`Tambah label ${productById(x.id).name}`" @click="x.qty++"><Plus :size="22" /></button>
                        <button v-if="productById(x.id).stock > 0" type="button" class="pressable min-h-12 rounded-xl px-2 text-base font-bold text-primary-ink" @click="x.qty = productById(x.id).stock">= stok</button>
                        <button type="button" class="pressable flex size-12 items-center justify-center rounded-xl text-danger-ink" :aria-label="`Hapus ${productById(x.id).name}`" @click="picked.splice(i, 1)"><X :size="22" /></button>
                    </span>
                </li>
            </ul>
            <div v-if="missing.length" class="mt-3 flex flex-wrap items-center gap-3 rounded-2xl bg-warn-soft p-4">
                <p class="min-w-48 flex-1 text-base font-bold text-warn-ink">{{ missing.length }} barang belum punya barcode, jadi labelnya belum bisa dicetak.</p>
                <BigButton variant="secondary" @click="generate">Buat Barcode Otomatis</BigButton>
            </div>
        </section>

        <section class="card mb-5 flex flex-col gap-4 p-5">
            <h2 class="text-xl font-extrabold text-ink"><span class="text-ink-soft">2.</span> Pilih kertas</h2>
            <SegmentedControl v-model="layout" label="Jenis kertas" :options="Object.entries(LAYOUTS).map(([value, l]) => ({ value, label: l.label }))" />
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                <span class="min-w-48 flex-1 text-lg text-ink">Tampilkan harga</span>
                <ToggleSwitch v-model="showPrice" label="Tampilkan harga" class="ml-auto" />
            </div>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                <span class="min-w-48 flex-1 text-lg text-ink">Tampilkan nama toko</span>
                <ToggleSwitch v-model="showShop" label="Tampilkan nama toko" class="ml-auto" />
            </div>
        </section>

        <BigButton block size="large" :disabled="!labels.length" @click="printLabels">
            <Printer :size="24" aria-hidden="true" /> Cetak {{ labels.length }} Label
        </BigButton>
        <p class="mt-3 text-center text-base text-ink-soft">Di menu cetak, pilih printer label Anda dan atur skala 100%.</p>

        <h2 v-if="labels.length" class="mt-8 mb-3 text-xl font-extrabold text-ink">Contoh hasil</h2>
    </div>

    <!-- Lembar label: tampil sebagai pratinjau, dan satu-satunya yang ikut tercetak -->
    <div v-if="labels.length" class="label-print mx-auto flex max-w-3xl flex-col items-center gap-4 print:block print:max-w-none print:gap-0">
        <div v-for="(page, p) in pages" :key="p" :class="layout === 'a4' ? 'sheet-a4' : 'sheet-thermal'">
            <div v-for="(item, i) in page" :key="i" class="label">
                <p v-if="showShop" class="label-shop">{{ shopName }}</p>
                <p class="label-name">{{ item.name }}</p>
                <div class="label-bars"><BarcodeSvg :value="codeOf(item)" /></div>
                <p class="label-code">{{ codeOf(item) }}</p>
                <p v-if="showPrice" class="label-price">{{ formatRupiah(item.price) }}</p>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Ukuran label nyata (mm) supaya pas di printer label maupun stiker A4. Warna selalu hitam di atas putih. */
.label {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    background: #fff;
    color: #000;
    font-family: Arial, Helvetica, sans-serif;
    text-align: center;
    padding: 1.5mm 2mm;
    box-sizing: border-box;
}
.label-shop { font-size: 6.5pt; font-weight: 700; line-height: 1.1; }
.label-name { font-size: 7.5pt; font-weight: 700; line-height: 1.15; max-height: 2.3em; overflow: hidden; }
.label-bars { width: 100%; height: 10mm; margin: 0.8mm 0; }
.label-code { font-size: 7pt; letter-spacing: 0.5px; line-height: 1; }
.label-price { font-size: 10pt; font-weight: 800; line-height: 1.2; }

.sheet-thermal { width: 50mm; height: 30mm; box-shadow: 0 0 0 1px #ccc; }
.sheet-thermal .label { width: 50mm; height: 30mm; }

.sheet-a4 {
    width: 210mm;
    height: 297mm;
    display: grid;
    grid-template-columns: repeat(3, 70mm);
    grid-template-rows: repeat(8, 37.125mm);
    box-shadow: 0 0 0 1px #ccc;
    background: #fff;
}
.sheet-a4 .label { width: 70mm; height: 37.125mm; }
.sheet-a4 .label-bars { height: 13mm; }

@media screen and (max-width: 800px) {
    .sheet-a4 { zoom: 0.42; }
}

@media print {
    .sheet-thermal, .sheet-a4 { box-shadow: none; break-after: page; }
}
</style>

<style>
/* Saat mencetak, hanya lembar label yang tampil (menu & tombol disembunyikan). */
@media print {
    body * { visibility: hidden; }
    .label-print, .label-print * { visibility: visible; }
    .label-print { position: absolute; inset: 0 auto auto 0; }
}
</style>
