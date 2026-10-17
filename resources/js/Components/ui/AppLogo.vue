<script setup>
/**
 * Logo Kasloka: uang kepeng (pis bolong) emas di atas kotak hitam. Kepeng adalah uang kuno Bali
 * yang masih dipakai dalam upacara, lambang rezeki yang berputar; lubang perseginya mudah dikenali
 * walau ikon kecil.
 *
 * variant: 'default' (kotak hitam, tulisan mengikuti tema) | 'inverse' (kotak emas, untuk latar gelap)
 */
import { KEPENG } from './kepeng';

defineProps({
    size: { type: Number, default: 40 },
    wordmark: { type: Boolean, default: true },
    variant: { type: String, default: 'default' },
});
</script>

<template>
    <span class="inline-flex items-center gap-2.5">
        <svg :width="size" :height="size" viewBox="0 0 48 48" aria-hidden="true" class="shrink-0">
            <rect width="48" height="48" rx="12" :fill="variant === 'inverse' ? '#f5b544' : '#111827'" />
            <path :d="KEPENG" :fill="variant === 'inverse' ? '#111827' : '#f5b544'" fill-rule="evenodd" />
        </svg>
        <span
            v-if="wordmark"
            class="font-display leading-none font-extrabold tracking-tight"
            :class="variant === 'inverse' ? 'text-white' : 'text-ink'"
            :style="{ fontSize: `${size * 0.6}px` }"
        >
            Kasloka
        </span>
        <span class="sr-only">Kasloka</span>
    </span>
</template>
