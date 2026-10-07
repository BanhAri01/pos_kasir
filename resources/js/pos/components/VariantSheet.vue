<script setup>
/**
 * Pilih varian di kasir: ketuk model, lalu pilih ukuran & warna dengan tombol besar.
 * Setiap kombinasi adalah barang sendiri (stok & harga sendiri), jadi yang masuk keranjang adalah variannya.
 */
import { computed, ref, watch } from 'vue';
import BigButton from '@/Components/ui/BigButton.vue';
import BottomSheet from '@/Components/ui/BottomSheet.vue';
import { formatRupiah } from '@/composables/useRupiah';
import { addToCart, store } from '../store';
import { showToast } from '../lib/toast';

const open = defineModel('open', { type: Boolean, default: false });
const props = defineProps({ model: { type: Object, default: null } });

const picked = ref({});

const options = computed(() => props.model?.variant_options ?? []);
const variants = computed(() => (props.model ? store.boot.products.filter((p) => p.parent_id === props.model.id) : []));

watch(open, (value) => {
    if (value) picked.value = {};
});

/** Varian yang cocok dengan pilihan sejauh ini (tanpa melihat dimensi `except`). */
function matching(except = null) {
    return variants.value.filter((v) => Object.entries(picked.value).every(([dim, val]) => dim === except || v.variant_values?.[dim] === val));
}

function variantFor(dim, value) {
    return matching(dim).filter((v) => v.variant_values?.[dim] === value);
}

const chosen = computed(() => (options.value.every((o) => picked.value[o.name]) ? matching()[0] ?? null : null));

function pick(dim, value) {
    picked.value = { ...picked.value, [dim]: picked.value[dim] === value ? undefined : value };
    if (picked.value[dim] === undefined) delete picked.value[dim];
}

function add() {
    if (!chosen.value) return;
    addToCart(chosen.value);
    showToast(`${chosen.value.name} masuk keranjang.`);
    open.value = false;
}

function stockLabel(list) {
    if (!list.length) return 'tidak ada';
    const stock = list.reduce((s, v) => s + (v.stock_raw ?? 0), 0);
    return stock <= 0 ? 'habis' : `sisa ${String(stock).replace('.', ',')}`;
}

const chip = (active, empty) => [
    'pressable flex min-h-touch min-w-20 flex-col items-center justify-center rounded-2xl border-2 px-4 py-2 text-lg font-extrabold',
    active ? 'border-primary bg-primary-soft text-primary-ink' : empty ? 'border-line text-ink-soft opacity-50' : 'border-line text-ink',
];
</script>

<template>
    <BottomSheet v-model:open="open" :title="model?.name ?? ''">
        <div v-if="model" class="flex flex-col gap-5">
            <div v-for="o in options" :key="o.name">
                <p class="mb-2 text-lg font-bold text-ink">Pilih {{ o.name.toLowerCase() }}</p>
                <div class="flex flex-wrap gap-2" role="group" :aria-label="o.name">
                    <button
                        v-for="value in o.values"
                        :key="value"
                        type="button"
                        :class="chip(picked[o.name] === value, !variantFor(o.name, value).length)"
                        :disabled="!variantFor(o.name, value).length"
                        :aria-pressed="picked[o.name] === value"
                        @click="pick(o.name, value)"
                    >
                        <span>{{ value }}</span>
                        <span class="text-base font-semibold" :class="picked[o.name] === value ? 'text-primary-ink' : 'text-ink-soft'">{{ stockLabel(variantFor(o.name, value)) }}</span>
                    </button>
                </div>
            </div>

            <div v-if="chosen" class="rounded-2xl bg-surface-2 p-4">
                <p class="text-lg font-bold text-ink">{{ chosen.name }}</p>
                <p class="text-lg text-ink-soft">
                    {{ formatRupiah(chosen.price) }}
                    <template v-if="chosen.stock !== null"> · stok {{ chosen.stock }}</template>
                    <template v-if="chosen.code"> · {{ chosen.code }}</template>
                </p>
            </div>
            <p v-else class="text-lg text-ink-soft">Pilih {{ options.map((o) => o.name.toLowerCase()).join(' dan ') }} dulu.</p>

            <BigButton block size="large" :disabled="!chosen" @click="add">Tambah ke Keranjang</BigButton>
        </div>
    </BottomSheet>
</template>
