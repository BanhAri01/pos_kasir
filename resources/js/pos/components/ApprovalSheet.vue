<script setup>
/**
 * Persetujuan atasan: manajer/pemilik memilih namanya lalu mengetik PIN di HP kasir.
 * Memanggil `resolve({ approver_id, approver_pin })` saat selesai.
 */
import { ref, watch } from 'vue';
import { ShieldCheck } from 'lucide-vue-next';
import BottomSheet from '@/Components/ui/BottomSheet.vue';
import PinPad from '@/Components/ui/PinPad.vue';
import { store } from '../store';

const open = defineModel('open', { type: Boolean, default: false });

defineProps({
    reason: { type: String, default: 'Aksi ini butuh persetujuan manajer atau pemilik.' },
    error: { type: String, default: '' },
});

const emit = defineEmits(['approve']);

const approverId = ref(null);
const pin = ref('');

watch(open, (v) => {
    if (v) {
        approverId.value = store.boot.approvers.length === 1 ? store.boot.approvers[0].id : null;
        pin.value = '';
    }
});

function submit() {
    emit('approve', { approver_id: approverId.value, approver_pin: pin.value });
    pin.value = '';
}
</script>

<template>
    <BottomSheet v-model:open="open" title="Butuh Persetujuan">
        <div class="flex flex-col gap-4">
            <p class="flex items-start gap-3 rounded-2xl bg-info-soft p-4 text-lg text-info-ink">
                <ShieldCheck :size="26" class="shrink-0" aria-hidden="true" /> {{ reason }}
            </p>

            <div v-if="!store.boot.approvers.length" class="rounded-2xl bg-warn-soft p-4 text-lg text-warn-ink">
                Belum ada manajer atau pemilik yang punya PIN. Pemilik bisa membuat PIN di menu Karyawan.
            </div>

            <div v-else-if="!approverId" class="flex flex-col gap-2">
                <p class="text-lg font-bold text-ink">Siapa yang menyetujui?</p>
                <button
                    v-for="a in store.boot.approvers"
                    :key="a.id"
                    type="button"
                    class="pressable min-h-touch rounded-2xl border-2 border-line px-4 text-left text-lg font-bold text-ink hover:border-primary"
                    @click="approverId = a.id"
                >
                    {{ a.name }}
                </button>
            </div>

            <template v-else>
                <p class="text-center text-lg font-bold text-ink">
                    {{ store.boot.approvers.find((a) => a.id === approverId)?.name }}, ketik PIN Anda
                </p>
                <PinPad v-model="pin" :error="error" @submit="submit" />
            </template>
        </div>
    </BottomSheet>
</template>
