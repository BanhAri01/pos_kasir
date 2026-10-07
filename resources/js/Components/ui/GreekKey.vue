<script setup>
/**
 * Garis hias meander (pola kunci Yunani). Hanya hiasan: warna mengikuti `currentColor`,
 * memanjang selebar wadahnya. Pakai secukupnya (pembatas / pinggiran), bukan di tengah isi.
 */
import { useId } from 'vue';

defineProps({
    height: { type: Number, default: 12 },
});

const id = `greek-key-${useId()}`;
</script>

<template>
    <svg class="block w-full" :height="height" aria-hidden="true" focusable="false">
        <defs>
            <pattern :id="id" :width="height" :height="height" patternUnits="userSpaceOnUse">
                <g :transform="`scale(${height / 12})`">
                    <path d="M0 11.25H12M0 .75H12M1.5 11.25V3.25H9.75V9H4.5V5.75H7.25" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="square" />
                </g>
            </pattern>
        </defs>
        <rect width="100%" :height="height" :fill="`url(#${id})`" />
    </svg>
</template>
