<script setup>
/**
 * Status internet yang selalu terlihat, dengan kalimat sederhana.
 * Muncul saat internet mati, dan sebentar saat internet menyala lagi.
 *
 * `message` bisa diganti per halaman. Halaman kasir (Fase 5) akan memakai:
 * "Sedang offline - transaksi tetap tersimpan aman".
 */
import { ref, watch } from 'vue';
import { Wifi, WifiOff } from 'lucide-vue-next';
import { useOnline } from '@/composables/useOnline';

defineProps({
    message: {
        type: String,
        default: 'Internet sedang mati. Perubahan belum bisa disimpan, tunggu sampai internet menyala lagi.',
    },
});

const online = useOnline();
const justReconnected = ref(false);
let timer = null;

watch(online, (value, previous) => {
    if (value && previous === false) {
        justReconnected.value = true;
        clearTimeout(timer);
        timer = setTimeout(() => (justReconnected.value = false), 4000);
    }
});
</script>

<template>
    <div v-if="!online" role="status" class="flex items-center gap-3 bg-warn-soft px-4 py-3 text-lg font-bold text-warn-ink">
        <WifiOff :size="26" class="shrink-0" aria-hidden="true" />
        <p>{{ message }}</p>
    </div>
    <div v-else-if="justReconnected" role="status" class="flex items-center gap-3 bg-primary-soft px-4 py-3 text-lg font-bold text-primary-ink">
        <Wifi :size="26" class="shrink-0" aria-hidden="true" />
        <p>Internet sudah menyala lagi.</p>
    </div>
</template>
