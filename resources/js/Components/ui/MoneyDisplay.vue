<script setup>
/**
 * Menampilkan uang Rupiah dengan angka sejajar (tabular) supaya mudah dibandingkan.
 *
 * size: sm (teks biasa) | md | lg | xl (total belanja: minimal 36px & tebal)
 * tone: default | positive (uang masuk) | negative (uang keluar)
 */
import { computed } from 'vue';
import { formatRupiah } from '@/composables/useRupiah';

const props = defineProps({
    amount: { type: Number, required: true },
    size: { type: String, default: 'md' },
    tone: { type: String, default: 'default' },
    label: { type: String, default: '' },
});

const text = computed(() => formatRupiah(props.amount));

const sizeClass = computed(
    () =>
        ({
            sm: 'text-lg font-bold',
            md: 'text-2xl font-extrabold',
            lg: 'text-3xl font-extrabold',
            xl: 'text-[2.25rem] leading-tight font-extrabold sm:text-5xl', // 2.25rem x 18px = 40px
        })[props.size],
);

const toneClass = computed(
    () => ({ default: 'text-ink', positive: 'text-primary-ink', negative: 'text-danger-ink' })[props.tone],
);
</script>

<template>
    <div>
        <p v-if="label" class="text-lg font-bold text-ink-soft">{{ label }}</p>
        <p class="font-display tabular-nums" :class="[sizeClass, toneClass]">{{ text }}</p>
    </div>
</template>
