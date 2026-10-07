<script setup>
/**
 * Status internet & pengiriman data, selalu terlihat di bar atas kasir.
 *   Hijau  : tersambung, semua terkirim
 *   Biru   : sedang mengirim
 *   Kuning : offline / ada data menunggu dikirim
 */
import { computed, ref } from 'vue';
import { CloudOff, CloudUpload, RefreshCw, Wifi } from 'lucide-vue-next';
import BigButton from '@/Components/ui/BigButton.vue';
import BottomSheet from '@/Components/ui/BottomSheet.vue';
import { syncNow, syncState } from '../lib/sync';

const open = ref(false);

const view = computed(() => {
    if (!syncState.online) return { tone: 'bg-warn-soft text-warn-ink', icon: CloudOff, label: syncState.pending ? `Offline · ${syncState.pending} menunggu` : 'Offline' };
    if (syncState.syncing) return { tone: 'bg-info-soft text-info-ink', icon: RefreshCw, label: 'Mengirim...', spin: true };
    if (syncState.pending) return { tone: 'bg-warn-soft text-warn-ink', icon: CloudUpload, label: `${syncState.pending} menunggu` };
    return { tone: 'bg-primary-soft text-primary-ink', icon: Wifi, label: 'Tersambung' };
});
</script>

<template>
    <button type="button" class="pressable flex min-h-12 items-center gap-2 rounded-full px-3 text-base font-bold" :class="view.tone" @click="open = true">
        <component :is="view.icon" :size="20" :class="view.spin ? 'animate-spin' : ''" aria-hidden="true" />
        <span class="hidden min-[420px]:inline">{{ view.label }}</span>
        <span class="sr-only min-[420px]:hidden">{{ view.label }}</span>
    </button>

    <BottomSheet v-model:open="open" title="Status Data">
        <div class="flex flex-col gap-4 text-lg">
            <p v-if="!syncState.online" class="rounded-2xl bg-warn-soft p-4 text-warn-ink">
                <strong>Sedang offline.</strong> Tetap bisa jualan seperti biasa. Semua transaksi tersimpan aman di HP ini dan akan terkirim otomatis saat internet menyala.
            </p>
            <p v-else-if="syncState.pending" class="rounded-2xl bg-warn-soft p-4 text-warn-ink">
                Ada <strong>{{ syncState.pending }} data</strong> yang belum terkirim. Biasanya terkirim sendiri dalam beberapa detik.
            </p>
            <p v-else class="rounded-2xl bg-primary-soft p-4 text-primary-ink">Semua transaksi sudah terkirim ke server. Aman.</p>

            <p v-if="syncState.lastError && syncState.online" class="text-base text-ink-soft">Terakhir: {{ syncState.lastError }}</p>

            <BigButton v-if="syncState.pending && syncState.online" block :loading="syncState.syncing" @click="syncNow()">
                <CloudUpload :size="22" aria-hidden="true" /> Kirim Sekarang
            </BigButton>
            <BigButton variant="secondary" block @click="open = false">Tutup</BigButton>
        </div>
    </BottomSheet>
</template>
