<script setup>
/**
 * Panel yang muncul dari bawah layar di HP (seperti aplikasi iPhone modern),
 * dan menjadi kotak di tengah layar pada tablet/laptop.
 *
 * Selalu ada tombol "Tutup" yang terlihat; tidak perlu menggeser (swipe) untuk menutup.
 * Mengetuk area gelap di luar panel juga menutup (kecuali `persistent`).
 */
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { X } from 'lucide-vue-next';

const open = defineModel('open', { type: Boolean, default: false });

const props = defineProps({
    title: { type: String, default: '' },
    persistent: { type: Boolean, default: false },
    showClose: { type: Boolean, default: true },
});

const panel = ref(null);

function close() {
    if (!props.persistent) open.value = false;
}

function onKey(event) {
    if (event.key === 'Escape') close();
}

watch(open, async (value) => {
    // Kunci gulir halaman di belakang panel.
    document.documentElement.style.overflow = value ? 'hidden' : '';
    if (value) {
        window.addEventListener('keydown', onKey);
        await nextTick();
        panel.value?.focus();
    } else {
        window.removeEventListener('keydown', onKey);
    }
});

onBeforeUnmount(() => {
    document.documentElement.style.overflow = '';
    window.removeEventListener('keydown', onKey);
});
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-6">
            <div class="absolute inset-0 animate-fade-in bg-black/55" aria-hidden="true" @click="close" />

            <div
                ref="panel"
                role="dialog"
                aria-modal="true"
                :aria-label="title || undefined"
                tabindex="-1"
                class="relative flex max-h-[92dvh] w-full animate-sheet-up flex-col rounded-t-3xl bg-surface shadow-float outline-none sm:max-w-lg sm:animate-pop-in sm:rounded-3xl"
            >
                <!-- Garis kecil di atas: penanda panel (hiasan) -->
                <div class="flex justify-center pt-3 sm:hidden" aria-hidden="true">
                    <span class="h-1.5 w-12 rounded-full bg-line" />
                </div>

                <header v-if="title || showClose" class="flex items-start gap-3 px-6 pt-4 sm:pt-6">
                    <h2 class="flex-1 text-2xl font-extrabold text-ink">{{ title }}</h2>
                    <button
                        v-if="showClose && !persistent"
                        type="button"
                        class="pressable -mr-2 flex min-h-12 items-center gap-1 rounded-xl px-3 text-base font-bold text-ink-soft hover:bg-ink/5"
                        @click="close"
                    >
                        <X :size="22" aria-hidden="true" />
                        Tutup
                    </button>
                </header>

                <div class="overflow-y-auto overscroll-contain px-6 pt-4 pb-[calc(1.5rem+env(safe-area-inset-bottom))]">
                    <slot />
                </div>
            </div>
        </div>
    </Teleport>
</template>
