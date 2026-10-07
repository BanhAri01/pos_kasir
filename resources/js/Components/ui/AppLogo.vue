<script setup>
/**
 * Logo Hermes: tongkat bersayap (caduceus) yang disederhanakan, emas di atas hitam,
 * dengan tulisan HERMES bergaya prasasti Yunani. Caduceus adalah tongkat Hermes,
 * dewa pengantar pesan dan pelindung para pedagang.
 *
 * variant: 'default' (kotak hitam, tulisan mengikuti tema) | 'inverse' (kotak emas, untuk latar gelap)
 */
import { CADUCEUS_WING } from './caduceus';

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
            <g :fill="variant === 'inverse' ? '#111827' : '#f5b544'">
                <path :d="CADUCEUS_WING" />
                <path transform="translate(48 0) scale(-1 1)" :d="CADUCEUS_WING" />
                <circle cx="24" cy="8.4" r="2.4" />
            </g>
            <g fill="none" :stroke="variant === 'inverse' ? '#111827' : '#f5b544'" stroke-linecap="round">
                <path d="M24 11v31.5" stroke-width="2.5" />
                <path d="M20.4 20c-2.9 1.8-1.1 4.5 3.6 5.9s6.6 4.3 3.7 6.2c-1.6 1.1-3.3 1.9-3.6 3.6" stroke-width="2.2" />
                <path d="M27.6 20c2.9 1.8 1.1 4.5-3.6 5.9s-6.6 4.3-3.7 6.2c1.6 1.1 3.3 1.9 3.6 3.6" stroke-width="2.2" />
            </g>
        </svg>
        <span
            v-if="wordmark"
            class="font-greek leading-none font-bold tracking-[0.12em]"
            :class="variant === 'inverse' ? 'text-white' : 'text-ink'"
            :style="{ fontSize: `${size * 0.52}px` }"
        >
            HERMES
        </span>
        <span class="sr-only">Hermes POS</span>
    </span>
</template>
