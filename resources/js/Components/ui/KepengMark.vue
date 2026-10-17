<script setup>
/** Tanda kepeng tanpa kotak (warna = currentColor). Untuk hiasan besar samar di latar. */
import { KEPENG } from './kepeng';
</script>

<template>
    <svg viewBox="9 9 30 30" aria-hidden="true" focusable="false">
        <path :d="KEPENG" fill="currentColor" fill-rule="evenodd" />
    </svg>
</template>
