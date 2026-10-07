<script setup>
/**
 * Cari barang lalu ketuk untuk memilih. Alat scan barcode (USB/Bluetooth) juga bisa:
 * hasil scan diketik otomatis ke kolom ini lalu Enter, barang yang cocok langsung terpilih.
 */
import { onBeforeUnmount, ref, watch } from 'vue';
import { Package, Search } from 'lucide-vue-next';

const props = defineProps({
    stockOnly: { type: Boolean, default: true },
    placeholder: { type: String, default: 'Ketik nama barang atau scan barcode' },
    excludeIds: { type: Array, default: () => [] },
});

const emit = defineEmits(['select']);

const query = ref('');
const results = ref([]);
const loading = ref(false);
const searched = ref(false);
let timer = null;
let controller = null;

async function search(term) {
    controller?.abort();
    controller = new AbortController();
    loading.value = true;
    try {
        const params = new URLSearchParams({ q: term, stock_only: props.stockOnly ? '1' : '0' });
        const response = await fetch(`${route('products.search')}?${params}`, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        });
        const json = await response.json();
        results.value = json.data ?? [];
        searched.value = true;
    } catch (e) {
        if (e.name !== 'AbortError') results.value = [];
    } finally {
        loading.value = false;
    }
}

watch(query, (term) => {
    clearTimeout(timer);
    if (term.trim().length < 2) {
        results.value = [];
        searched.value = false;
        return;
    }
    timer = setTimeout(() => search(term.trim()), 250);
});

function choose(product) {
    emit('select', product);
    query.value = '';
    results.value = [];
    searched.value = false;
}

/** Enter: kalau barcode/kode cocok persis, langsung pilih (untuk alat scan). */
async function onEnter() {
    const term = query.value.trim();
    if (!term) return;
    clearTimeout(timer);
    await search(term);
    const exact = results.value.find((p) => p.barcode === term || p.code === term);
    if (exact) choose(exact);
    else if (results.value.length === 1) choose(results.value[0]);
}

onBeforeUnmount(() => {
    clearTimeout(timer);
    controller?.abort();
});
</script>

<template>
    <div>
        <label class="relative block">
            <span class="sr-only">Cari barang</span>
            <Search :size="24" class="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-ink-soft" aria-hidden="true" />
            <input
                v-model="query"
                type="search"
                :placeholder="placeholder"
                autocomplete="off"
                enterkeyhint="search"
                class="min-h-touch w-full rounded-2xl border-2 border-line bg-surface pr-4 pl-13 text-lg text-ink placeholder:text-ink-soft/60 focus:border-focus focus:ring-4 focus:ring-focus/20 focus:outline-none"
                @keydown.enter.prevent="onEnter"
            />
        </label>

        <ul v-if="results.length" class="mt-2 flex flex-col gap-2" role="listbox">
            <li v-for="product in results" :key="product.id">
                <button
                    type="button"
                    role="option"
                    class="pressable flex w-full items-center gap-3 rounded-2xl border-2 border-line bg-surface p-3 text-left hover:border-primary disabled:opacity-50"
                    :disabled="excludeIds.includes(product.id)"
                    @click="choose(product)"
                >
                    <img v-if="product.image_url" :src="product.image_url" alt="" class="size-12 rounded-xl object-cover" />
                    <span v-else class="flex size-12 items-center justify-center rounded-xl bg-surface-2 text-ink-soft">
                        <Package :size="24" aria-hidden="true" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-lg font-bold text-ink">{{ product.name }}</span>
                        <span v-if="product.stock !== null" class="block text-base text-ink-soft">Stok: {{ product.stock }} {{ product.unit ?? '' }}</span>
                    </span>
                    <span v-if="excludeIds.includes(product.id)" class="text-base font-bold text-ink-soft">Sudah dipilih</span>
                </button>
            </li>
        </ul>
        <p v-else-if="searched && !loading" class="mt-3 text-lg text-ink-soft">Barang tidak ditemukan. Coba kata lain.</p>
    </div>
</template>
