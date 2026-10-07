<script setup>
/**
 * Jumlah barang dengan tombol besar − dan + (tanpa harus mengetik).
 * Tetap bisa diketik langsung; koma boleh dipakai untuk desimal (2,5 kg).
 */
import { computed } from 'vue';
import { Minus, Plus } from 'lucide-vue-next';

const model = defineModel({ type: [String, Number], default: '' });

const props = defineProps({
    label: { type: String, default: 'Jumlah' },
    unit: { type: String, default: '' },
    decimal: { type: Boolean, default: false },
    min: { type: Number, default: 0 },
    step: { type: Number, default: 1 },
    error: { type: String, default: '' },
    compact: { type: Boolean, default: false },
});

const numeric = computed(() => parseFloat(String(model.value).replace(',', '.')) || 0);

function format(n) {
    const rounded = Math.round(n * 1000) / 1000;
    return String(rounded).replace('.', ',');
}

function change(delta) {
    model.value = format(Math.max(props.min, numeric.value + delta));
}

function onInput(event) {
    const pattern = props.decimal ? /[^\d,.]/g : /\D/g;
    model.value = event.target.value.replace(pattern, '');
}

const btn = 'pressable flex shrink-0 items-center justify-center rounded-xl bg-surface-2 text-ink hover:bg-line disabled:opacity-40';
</script>

<template>
    <div>
        <span v-if="!compact" class="mb-2 block text-lg font-bold text-ink">{{ label }}</span>
        <div class="flex items-center gap-2">
            <button type="button" :class="[btn, compact ? 'size-12' : 'size-14']" :disabled="numeric <= min" :aria-label="`Kurangi ${label}`" @click="change(-step)">
                <Minus :size="26" aria-hidden="true" />
            </button>
            <div
                class="flex min-w-0 flex-1 items-center rounded-xl border-2 bg-surface focus-within:border-focus"
                :class="[error ? 'border-danger' : 'border-line', compact ? 'h-12' : 'h-14']"
            >
                <input
                    :value="model"
                    type="text"
                    :inputmode="decimal ? 'decimal' : 'numeric'"
                    :aria-label="label"
                    class="w-full min-w-0 bg-transparent px-2 text-center font-display text-xl font-extrabold text-ink tabular-nums focus:outline-none"
                    @input="onInput"
                />
                <span v-if="unit" class="pr-3 text-base font-bold text-ink-soft">{{ unit }}</span>
            </div>
            <button type="button" :class="[btn, compact ? 'size-12' : 'size-14']" :aria-label="`Tambah ${label}`" @click="change(step)">
                <Plus :size="26" aria-hidden="true" />
            </button>
        </div>
        <p v-if="error" role="alert" class="mt-2 text-base font-bold text-danger-ink">{{ error }}</p>
    </div>
</template>
