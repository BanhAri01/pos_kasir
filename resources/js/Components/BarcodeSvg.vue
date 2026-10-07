<script setup>
/** Gambar barcode Code 128 (SVG, tajam di printer thermal maupun kertas A4). */
import { computed } from 'vue';
import { code128Bars } from '@/lib/code128';

const props = defineProps({
    value: { type: String, required: true },
    height: { type: Number, default: 40 },
});

const barcode = computed(() => {
    try {
        return code128Bars(props.value);
    } catch {
        return null;
    }
});
</script>

<template>
    <svg
        v-if="barcode"
        :viewBox="`0 0 ${barcode.width} ${height}`"
        preserveAspectRatio="none"
        class="block w-full"
        :style="{ height: '100%' }"
        role="img"
        :aria-label="`Barcode ${value}`"
    >
        <rect :width="barcode.width" :height="height" fill="#fff" />
        <rect v-for="(bar, i) in barcode.bars" :key="i" :x="bar.x" :width="bar.w" :height="height" fill="#000" />
    </svg>
    <span v-else class="text-xs">Barcode tidak valid</span>
</template>
