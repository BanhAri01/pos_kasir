<script setup>
/**
 * Peringatan saat nomor WhatsApp (Fonnte) terputus.
 * Pemilik hanya melihat nomor yang ia pakai; admin melihat semua nomor beserta tautan ke Fonnte.
 */
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { ExternalLink, MessageCircleWarning } from 'lucide-vue-next';

const page = usePage();
const notices = computed(() => page.props.waNotices ?? []);
</script>

<template>
    <div v-if="notices.length" role="alert" class="bg-danger-soft px-4 py-3 text-danger-ink">
        <div v-for="(n, i) in notices" :key="i" class="mx-auto flex max-w-5xl flex-wrap items-center gap-3 py-1">
            <MessageCircleWarning :size="24" class="shrink-0" aria-hidden="true" />
            <p class="min-w-0 flex-1 text-lg font-bold">{{ n.text }}</p>
            <a v-if="n.fix" href="https://fonnte.com" target="_blank" rel="noopener" class="pressable flex min-h-touch items-center gap-1 rounded-xl px-3 text-base font-extrabold underline">
                Buka Fonnte <ExternalLink :size="16" aria-hidden="true" />
            </a>
        </div>
    </div>
</template>
