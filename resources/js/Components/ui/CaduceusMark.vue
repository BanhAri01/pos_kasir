<script setup>
/** Tanda caduceus tanpa kotak (warna = currentColor). Untuk hiasan besar samar di latar. */
import { CADUCEUS_WING } from './caduceus';
</script>

<template>
    <svg viewBox="0 0 48 48" aria-hidden="true" focusable="false">
        <g fill="currentColor">
            <path :d="CADUCEUS_WING" />
            <path transform="translate(48 0) scale(-1 1)" :d="CADUCEUS_WING" />
            <circle cx="24" cy="8.4" r="2.4" />
        </g>
        <g fill="none" stroke="currentColor" stroke-linecap="round">
            <path d="M24 11v31.5" stroke-width="2.5" />
            <path d="M20.4 20c-2.9 1.8-1.1 4.5 3.6 5.9s6.6 4.3 3.7 6.2c-1.6 1.1-3.3 1.9-3.6 3.6" stroke-width="2.2" />
            <path d="M27.6 20c2.9 1.8 1.1 4.5-3.6 5.9s-6.6 4.3-3.7 6.2c1.6 1.1 3.3 1.9 3.6 3.6" stroke-width="2.2" />
        </g>
    </svg>
</template>
