<script setup>
import { CircleAlert, CircleCheck, Info, X } from 'lucide-vue-next';
import { toast } from '../lib/toast';

const styles = {
    success: { box: 'bg-primary text-on-primary', icon: CircleCheck },
    error: { box: 'bg-danger text-white', icon: CircleAlert },
    info: { box: 'bg-ink text-surface', icon: Info },
};
</script>

<template>
    <Teleport to="body">
        <div
            v-if="toast.message"
            class="pointer-events-none fixed inset-x-0 top-0 z-60 p-3 pt-[calc(0.75rem+env(safe-area-inset-top))] sm:left-auto sm:w-md"
            role="status"
            aria-live="assertive"
        >
            <div :key="toast.id" class="pointer-events-auto flex animate-pop-in items-start gap-3 rounded-2xl p-4 text-lg font-bold shadow-float" :class="styles[toast.kind].box">
                <component :is="styles[toast.kind].icon" :size="28" class="mt-0.5 shrink-0" aria-hidden="true" />
                <p class="flex-1">{{ toast.message }}</p>
                <button type="button" class="flex size-11 shrink-0 items-center justify-center rounded-xl hover:bg-white/15" aria-label="Tutup pesan" @click="toast.message = null">
                    <X :size="24" aria-hidden="true" />
                </button>
            </div>
        </div>
    </Teleport>
</template>
