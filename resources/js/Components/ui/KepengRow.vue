<script setup>
/**
 * Garis hias deretan uang kepeng. Hanya hiasan: warna mengikuti `currentColor`,
 * memanjang selebar wadahnya. Pakai secukupnya (pembatas / pinggiran), bukan di tengah isi.
 */
import { useId } from 'vue';

defineProps({
    height: { type: Number, default: 12 },
});

const id = `kepeng-row-${useId()}`;
</script>

<template>
    <svg class="block w-full" :height="height" aria-hidden="true" focusable="false">
        <defs>
            <pattern :id="id" :width="height * 1.75" :height="height" patternUnits="userSpaceOnUse">
                <g :transform="`scale(${height / 12})`">
                    <path d="M1.5 6a4.5 4.5 0 1 0 9 0a4.5 4.5 0 1 0-9 0ZM4.6 4.6h2.8v2.8H4.6Z" fill="currentColor" fill-rule="evenodd" />
                    <path d="M12 6h7" stroke="currentColor" stroke-width="1" />
                </g>
            </pattern>
        </defs>
        <rect width="100%" :height="height" :fill="`url(#${id})`" />
    </svg>
</template>
