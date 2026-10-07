<script setup>
/**
 * Kolom uang Rupiah: titik ribuan muncul otomatis saat mengetik (15000 -> 15.000),
 * keyboard angka di HP, nilai yang dikirim tetap bilangan bulat (rupiah).
 */
import { computed, useId } from 'vue';
import { formatNumber, parseRupiah } from '@/composables/useRupiah';

const model = defineModel({ type: [Number, null], default: null });

defineProps({
    label: { type: String, required: true },
    hint: { type: String, default: '' },
    error: { type: String, default: '' },
    placeholder: { type: String, default: '0' },
    optional: { type: Boolean, default: false },
});

const id = useId();

const display = computed({
    get: () => (model.value === null || model.value === '' ? '' : formatNumber(model.value)),
    set: (text) => {
        const digits = String(text).replace(/\D/g, '');
        model.value = digits === '' ? null : parseRupiah(digits);
    },
});
</script>

<template>
    <div class="min-w-0">
        <label :for="id" class="mb-2 block text-lg font-bold text-ink">
            {{ label }}
            <span v-if="optional" class="font-semibold text-ink-soft">(boleh dikosongkan)</span>
        </label>
        <div
            class="flex min-h-touch items-stretch overflow-hidden rounded-2xl border-2 bg-surface transition-colors focus-within:border-focus focus-within:ring-4 focus-within:ring-focus/20"
            :class="error ? 'border-danger' : 'border-line'"
        >
            <span class="flex items-center bg-surface-2 px-4 text-lg font-bold text-ink-soft">Rp</span>
            <input
                :id="id"
                v-model="display"
                type="text"
                inputmode="numeric"
                autocomplete="off"
                :placeholder="placeholder"
                :aria-invalid="!!error"
                class="min-w-0 flex-1 bg-transparent px-4 font-display text-xl font-bold text-ink tabular-nums placeholder:text-ink-soft/50 focus:outline-none"
            />
        </div>
        <p v-if="hint && !error" class="mt-2 text-base text-ink-soft">{{ hint }}</p>
        <p v-if="error" role="alert" class="mt-2 text-base font-bold text-danger-ink">{{ error }}</p>
    </div>
</template>
