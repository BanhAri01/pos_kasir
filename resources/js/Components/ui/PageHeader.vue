<script setup>
/**
 * Judul halaman + tombol "Kembali" bertulisan (bukan hanya panah) + tombol Bantuan.
 */
import { Link } from '@inertiajs/vue3';
import { ChevronLeft } from 'lucide-vue-next';
import HelpButton from './HelpButton.vue';

defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    backHref: { type: String, default: null },
    help: { type: String, default: null }, // kunci isi bantuan di help/content.js
});
</script>

<template>
    <header class="mb-6">
        <div class="mb-2 flex min-h-12 items-center justify-between gap-3">
            <Link
                v-if="backHref"
                :href="backHref"
                class="pressable -ml-2 inline-flex min-h-12 items-center gap-1 rounded-xl px-2 text-lg font-bold text-ink-soft hover:bg-ink/5"
            >
                <ChevronLeft :size="26" aria-hidden="true" />
                Kembali
            </Link>
            <span v-else />
            <HelpButton v-if="help" :topic="help" />
        </div>
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-3xl font-extrabold text-ink sm:text-4xl">{{ title }}</h1>
                <p v-if="subtitle" class="mt-1 text-lg text-ink-soft">{{ subtitle }}</p>
            </div>
            <slot name="action" />
        </div>
    </header>
</template>
