<script setup>
/**
 * Konfirmasi aksi penting. Tombolnya kalimat jelas ("Ya, Hapus" / "Tidak Jadi"),
 * bukan "OK / Cancel". Tombol aman ("Tidak Jadi") selalu ada dan mudah dijangkau jempol.
 */
import { AlertTriangle } from 'lucide-vue-next';
import BigButton from './BigButton.vue';
import BottomSheet from './BottomSheet.vue';

const open = defineModel('open', { type: Boolean, default: false });

defineProps({
    title: { type: String, required: true },
    message: { type: String, default: '' },
    confirmText: { type: String, default: 'Ya, Lanjutkan' },
    cancelText: { type: String, default: 'Tidak Jadi' },
    danger: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
});

const emit = defineEmits(['confirm']);
</script>

<template>
    <BottomSheet v-model:open="open" :show-close="false">
        <div class="flex flex-col items-center text-center">
            <span
                class="flex size-16 items-center justify-center rounded-full"
                :class="danger ? 'bg-danger-soft text-danger-ink' : 'bg-primary-soft text-primary-ink'"
            >
                <AlertTriangle :size="32" aria-hidden="true" />
            </span>
            <h2 class="mt-4 text-2xl font-extrabold text-ink">{{ title }}</h2>
            <p v-if="message" class="mt-2 text-lg text-ink-soft">{{ message }}</p>
        </div>
        <div class="mt-6 flex flex-col gap-3">
            <BigButton :variant="danger ? 'danger' : 'primary'" block :loading="loading" @click="emit('confirm')">
                {{ confirmText }}
            </BigButton>
            <BigButton variant="secondary" block @click="open = false">{{ cancelText }}</BigButton>
        </div>
    </BottomSheet>
</template>
