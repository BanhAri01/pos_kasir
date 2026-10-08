<script setup>
/**
 * Tombol besar (tinggi minimal 56px). Selalu pakai teks, ikon hanya pelengkap.
 *
 * variant: primary (abu gelap, aksi utama) | secondary (bergaris) | danger (merah) | ghost | soft
 * Kalau diberi `href`, tombol menjadi tautan Inertia.
 */
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    variant: { type: String, default: 'primary' },
    type: { type: String, default: 'button' },
    href: { type: String, default: null },
    method: { type: String, default: null },
    block: { type: Boolean, default: false },
    size: { type: String, default: 'normal' }, // normal | large
    loading: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    external: { type: Boolean, default: false },
});

const classes = computed(() => [
    'pressable inline-flex items-center justify-center gap-3 rounded-2xl px-6 font-bold',
    'disabled:cursor-not-allowed disabled:opacity-60',
    props.size === 'large' ? 'min-h-touch-lg text-xl' : 'min-h-touch text-lg',
    props.block ? 'w-full' : '',
    {
        primary: 'bg-primary text-on-primary shadow-[0_4px_14px_rgb(17_24_39/0.28)] hover:bg-primary-hover active:bg-primary-press',
        secondary: 'border-2 border-line bg-surface text-ink hover:border-ink-soft',
        danger: 'bg-danger text-white hover:bg-danger-hover',
        ghost: 'bg-transparent text-ink hover:bg-ink/5',
        soft: 'bg-primary-soft text-primary-ink hover:brightness-95',
    }[props.variant],
]);
</script>

<template>
    <a v-if="href && external" :href="href" target="_blank" rel="noopener" :class="classes">
        <slot />
    </a>
    <Link v-else-if="href" :href="href" :method="method" :as="method ? 'button' : 'a'" :class="classes">
        <slot />
    </Link>
    <button v-else :type="type" :class="classes" :disabled="disabled || loading" :aria-busy="loading">
        <svg v-if="loading" class="size-6 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" opacity=".25" />
            <path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="4" />
        </svg>
        <slot />
    </button>
</template>
