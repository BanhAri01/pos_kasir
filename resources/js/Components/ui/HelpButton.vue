<script setup>
/**
 * Tombol "Bantuan" di setiap halaman: penjelasan singkat bernomor, link video,
 * dan tombol Hubungi Admin via WhatsApp.
 */
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { CircleHelp, MessageCircle, PlayCircle } from 'lucide-vue-next';
import { helpContent } from '@/help/content';
import BottomSheet from './BottomSheet.vue';

const props = defineProps({
    topic: { type: String, required: true },
});

const page = usePage();
const open = ref(false);
const help = computed(() => helpContent[props.topic]);
const whatsappUrl = computed(
    () =>
        `https://wa.me/${page.props.adminWhatsapp}?text=${encodeURIComponent(`Halo admin Hermes POS, saya butuh bantuan soal: ${help.value?.title ?? ''}`)}`,
);
</script>

<template>
    <template v-if="help">
        <button
            type="button"
            class="pressable flex min-h-12 items-center gap-2 rounded-full bg-info-soft px-4 text-base font-bold text-info-ink"
            @click="open = true"
        >
            <CircleHelp :size="24" aria-hidden="true" />
            Bantuan
        </button>

        <BottomSheet v-model:open="open" :title="help.title">
            <p class="text-lg text-ink-soft">{{ help.intro }}</p>
            <ol class="mt-5 flex flex-col gap-4">
                <li v-for="(step, i) in help.steps" :key="i" class="flex gap-3">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary-soft font-display text-lg font-extrabold text-primary-ink">
                        {{ i + 1 }}
                    </span>
                    <span class="pt-1 text-lg text-ink">{{ step }}</span>
                </li>
            </ol>

            <div class="mt-6 flex flex-col gap-3">
                <a
                    v-if="help.video"
                    :href="help.video"
                    target="_blank"
                    rel="noopener"
                    class="pressable flex min-h-touch items-center justify-center gap-2 rounded-2xl bg-surface-2 px-4 text-lg font-bold text-ink"
                >
                    <PlayCircle :size="26" aria-hidden="true" />
                    Tonton Video Panduan
                </a>
                <a
                    :href="whatsappUrl"
                    target="_blank"
                    rel="noopener"
                    class="pressable flex min-h-touch items-center justify-center gap-2 rounded-2xl bg-primary-soft px-4 text-lg font-bold text-primary-ink"
                >
                    <MessageCircle :size="26" aria-hidden="true" />
                    Masih bingung? Tanya Admin via WhatsApp
                </a>
            </div>
        </BottomSheet>
    </template>
</template>
