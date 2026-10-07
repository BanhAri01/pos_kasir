<script setup>
/**
 * Pesan besar setelah aksi: sukses (hijau + suara "ting" + getar), gagal (merah),
 * info (biru), dan "sudah dihapus" dengan tombol Batalkan selama 8 detik.
 * HP: di atas layar. Laptop: di pojok kanan atas.
 */
import { computed, ref, watch, onBeforeUnmount } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { CircleAlert, CircleCheck, Info, Undo2, X } from 'lucide-vue-next';
import { useFeedback } from '@/composables/useFeedback';

const page = usePage();
const feedback = useFeedback();
const flash = computed(() => page.props.flash ?? {});
const visible = ref(null); // { kind, message, url? }
let timer = null;

const styles = {
    success: { box: 'bg-primary text-on-primary', icon: CircleCheck },
    error: { box: 'bg-danger text-white', icon: CircleAlert },
    info: { box: 'bg-info-soft text-info-ink ring-2 ring-info-ink/30', icon: Info },
    undo: { box: 'bg-ink text-surface', icon: Info },
};

function show(kind, message, url = null) {
    clearTimeout(timer);
    visible.value = { kind, message, url };

    if (kind === 'success') feedback.success();
    if (kind === 'error') feedback.error();

    // Pesan gagal tetap tampil sampai ditutup, supaya sempat dibaca.
    if (kind !== 'error') {
        timer = setTimeout(() => (visible.value = null), kind === 'undo' ? 8000 : 5000);
    }
}

watch(
    flash,
    (f) => {
        if (f.undo) show('undo', f.undo.message, f.undo.url);
        else if (f.error) show('error', f.error);
        else if (f.success) show('success', f.success);
        else if (f.info) show('info', f.info);
    },
    { immediate: true, deep: true },
);

function undo() {
    const url = visible.value.url;
    visible.value = null;
    router.post(url, {}, { preserveScroll: true });
}

onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <Teleport to="body">
        <div
            v-if="visible"
            class="pointer-events-none fixed inset-x-0 top-0 z-60 p-3 pt-[calc(0.75rem+env(safe-area-inset-top))] sm:left-auto sm:w-md"
            role="status"
            aria-live="assertive"
        >
            <div
                :key="visible.message"
                class="pointer-events-auto flex animate-pop-in items-start gap-3 rounded-2xl p-4 text-lg font-bold shadow-float"
                :class="styles[visible.kind].box"
            >
                <component :is="styles[visible.kind].icon" :size="30" class="mt-0.5 shrink-0" aria-hidden="true" />
                <p class="flex-1">{{ visible.message }}</p>
                <button
                    v-if="visible.kind === 'undo'"
                    type="button"
                    class="pressable flex min-h-12 items-center gap-2 rounded-xl bg-accent px-4 font-extrabold text-black"
                    @click="undo"
                >
                    <Undo2 :size="22" aria-hidden="true" />
                    Batalkan
                </button>
                <button
                    v-else
                    type="button"
                    class="flex size-12 shrink-0 items-center justify-center rounded-xl hover:bg-white/15"
                    aria-label="Tutup pesan"
                    @click="visible = null"
                >
                    <X :size="26" aria-hidden="true" />
                </button>
            </div>
        </div>
    </Teleport>
</template>
