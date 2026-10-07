<script setup>
/**
 * Daftar barang di layar kasir. Tampilan menyesuaikan jenis usaha:
 *  - F&B / jasa / laundry : kotak besar bergambar (ketuk untuk menambah)
 *  - Toko (retail)        : daftar ringkas + kolom cari/scan barcode yang selalu siap
 */
import { computed, onMounted, ref } from 'vue';
import { Camera, Package, ScanBarcode, Search } from 'lucide-vue-next';
import { formatRupiah } from '@/composables/useRupiah';
import { addToCart, hasModule, store } from '../store';
import { cameraScanSupported, findByBarcode } from '../lib/barcode';
import { showToast } from '../lib/toast';
import CameraScanSheet from './CameraScanSheet.vue';

const emit = defineEmits(['add']);

const search = ref('');
const category = ref(null);
const searchInput = ref(null);
const cameraOpen = ref(false);
const canCamera = computed(() => hasModule('barcode') && cameraScanSupported());

const layout = computed(() => store.boot.tenant.pos_layout);
const isRetail = computed(() => layout.value === 'retail');

const inCart = computed(() => {
    const map = {};
    for (const line of store.cart) map[line.product_id] = (map[line.product_id] ?? 0) + Number(line.qty);
    return map;
});

const products = computed(() => {
    const term = search.value.trim().toLowerCase();
    return store.boot.products.filter((p) => {
        if (category.value && p.category_id !== category.value) return false;
        // Varian (ukuran x warna) dipilih lewat modelnya; hanya muncul langsung saat dicari.
        if (!term) return !p.parent_id;
        return p.name.toLowerCase().includes(term) || p.barcode === term || p.code?.toLowerCase() === term;
    });
});

const categories = computed(() => store.boot.categories.filter((c) => store.boot.products.some((p) => p.category_id === c.id)));

function choose(product) {
    if (product.sold_out_today) return;
    emit('add', product);
}

/** Hasil scan (alat scanner / kamera): barang atau satuannya (mis. barcode dus) langsung masuk keranjang. */
function addScanned(code) {
    const found = findByBarcode(store.boot.products, code);
    if (!found) {
        showToast(`Barcode ${code} belum terdaftar. Tambahkan barcode ini di menu Barang.`, 'error');
        return false;
    }
    if (found.product.sold_out_today) return false;
    if (found.unitId) addToCart(found.product, { unitId: found.unitId });
    else choose(found.product);
    return true;
}

/** Enter di kolom cari: kalau cocok persis dengan barcode/kode (hasil scan), langsung masuk keranjang. */
function onEnter() {
    const term = search.value.trim();
    if (!term) return;
    if (findByBarcode(store.boot.products, term)) {
        addScanned(term);
        search.value = '';
        return;
    }
    if (products.value.length === 1) {
        choose(products.value[0]);
        search.value = '';
    }
}

const initials = (name) => name.split(' ').slice(0, 2).map((w) => w[0]).join('').toUpperCase();
const tones = ['bg-primary-soft text-primary-ink', 'bg-accent-soft text-accent-ink', 'bg-info-soft text-info-ink', 'bg-danger-soft text-danger-ink'];
const tone = (id) => tones[id % tones.length];

onMounted(() => {
    // Di laptop/tablet toko, kolom cari langsung siap menerima hasil scan.
    if (isRetail.value && window.matchMedia('(min-width: 768px)').matches) searchInput.value?.focus();
});
</script>

<template>
    <div class="flex flex-col gap-3">
        <label class="relative block">
            <span class="sr-only">Cari barang</span>
            <component :is="isRetail ? ScanBarcode : Search" :size="24" class="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-ink-soft" aria-hidden="true" />
            <input
                ref="searchInput"
                v-model="search"
                type="search"
                enterkeyhint="search"
                :placeholder="isRetail ? 'Cari nama barang atau scan barcode' : 'Cari menu'"
                class="min-h-touch w-full rounded-2xl border-2 border-line bg-surface pr-4 pl-13 text-lg text-ink placeholder:text-ink-soft/60 focus:border-focus focus:ring-4 focus:ring-focus/20 focus:outline-none"
                @keydown.enter.prevent="onEnter"
            />
        </label>
        <button
            v-if="canCamera"
            type="button"
            class="pressable flex min-h-touch items-center justify-center gap-2 rounded-2xl border-2 border-line bg-surface text-lg font-bold text-ink"
            @click="cameraOpen = true"
        >
            <Camera :size="24" aria-hidden="true" /> Scan pakai Kamera
        </button>
        <CameraScanSheet v-if="canCamera" v-model:open="cameraOpen" @detected="addScanned" />

        <div v-if="categories.length > 1" class="flex flex-wrap gap-2" role="group" aria-label="Kategori">
            <button
                type="button"
                class="pressable min-h-12 rounded-full border-2 px-4 text-base font-bold"
                :class="!category ? 'border-primary bg-primary text-on-primary' : 'border-line bg-surface text-ink'"
                @click="category = null"
            >
                Semua
            </button>
            <button
                v-for="c in categories"
                :key="c.id"
                type="button"
                class="pressable min-h-12 rounded-full border-2 px-4 text-base font-bold"
                :class="category === c.id ? 'border-primary bg-primary text-on-primary' : 'border-line bg-surface text-ink'"
                @click="category = c.id"
            >
                {{ c.name }}
            </button>
        </div>

        <!-- Toko: daftar ringkas -->
        <ul v-if="isRetail" class="card divide-y divide-line overflow-hidden">
            <li v-for="p in products" :key="p.id">
                <button
                    type="button"
                    class="flex min-h-touch-lg w-full items-center gap-3 px-4 py-2 text-left hover:bg-surface-2 active:bg-primary-soft disabled:opacity-50"
                    :disabled="p.sold_out_today"
                    @click="choose(p)"
                >
                    <img v-if="p.image_url" :src="p.image_url" alt="" loading="lazy" class="size-12 shrink-0 rounded-xl object-cover" />
                    <span v-else class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-surface-2 text-ink-soft">
                        <Package :size="22" aria-hidden="true" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="line-clamp-2 text-lg leading-snug font-bold text-ink">{{ p.name }}</span>
                        <span v-if="p.track_stock" class="text-base" :class="p.is_out_of_stock || p.stock_raw <= 0 ? 'font-bold text-danger-ink' : 'text-ink-soft'">
                            {{ p.stock_raw <= 0 ? 'Stok habis' : `Stok ${p.stock}` }}
                        </span>
                        <span v-if="p.sold_out_today" class="text-base font-bold text-danger-ink">Habis hari ini</span>
                    </span>
                    <span class="text-right">
                        <span class="block font-display text-lg font-extrabold text-ink tabular-nums">{{ formatRupiah(p.price) }}</span>
                        <span v-if="inCart[p.id]" class="mt-1 inline-block rounded-full bg-primary px-2.5 text-base font-bold text-on-primary">× {{ String(inCart[p.id]).replace('.', ',') }}</span>
                    </span>
                </button>
            </li>
        </ul>

        <!-- F&B / jasa: kotak bergambar -->
        <ul v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
            <li v-for="p in products" :key="p.id">
                <button
                    type="button"
                    class="card pressable relative flex h-full w-full flex-col overflow-hidden text-left hover:ring-2 hover:ring-primary/40 disabled:opacity-50"
                    :disabled="p.sold_out_today"
                    @click="choose(p)"
                >
                    <img v-if="p.image_url" :src="p.image_url" alt="" loading="lazy" class="aspect-[4/3] w-full object-cover" />
                    <span v-else class="flex aspect-[2/1] w-full items-center justify-center font-display text-3xl font-extrabold" :class="tone(p.id)">
                        {{ initials(p.name) }}
                    </span>
                    <span class="flex flex-1 flex-col p-3">
                        <span class="line-clamp-2 text-lg leading-snug font-bold text-ink">{{ p.name }}</span>
                        <span class="mt-auto pt-1 font-display text-lg font-extrabold text-primary-ink tabular-nums">
                            {{ p.pricing_mode === 'open_price' ? 'Harga bebas' : formatRupiah(p.price) }}<span v-if="p.pricing_mode === 'per_weight'" class="text-base">/{{ p.unit }}</span>
                        </span>
                    </span>
                    <span v-if="inCart[p.id]" class="absolute top-2 right-2 flex min-w-10 items-center justify-center rounded-full bg-primary px-2 py-1 text-lg font-extrabold text-on-primary shadow">
                        {{ String(inCart[p.id]).replace('.', ',') }}
                    </span>
                    <span v-if="p.sold_out_today" class="absolute inset-x-2 top-2 rounded-full bg-danger px-2 py-1 text-center text-base font-bold text-white">Habis hari ini</span>
                </button>
            </li>
        </ul>

        <p v-if="!products.length" class="py-10 text-center text-lg text-ink-soft">
            {{ search ? 'Barang tidak ditemukan. Coba kata lain.' : 'Belum ada barang. Tambahkan barang di menu Barang.' }}
        </p>
    </div>
</template>
