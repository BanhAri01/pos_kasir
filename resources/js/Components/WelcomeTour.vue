<script setup>
/**
 * Tur pengenalan singkat saat pertama kali masuk. Bisa dilewati, dan bisa diulang
 * dari menu Lainnya > "Ulangi Tur Pengenalan".
 *
 * Sengaja berupa kartu bergantian (bukan sorotan yang menunjuk tombol), supaya
 * sederhana dan tetap jelas di semua ukuran layar.
 */
import { computed, ref } from 'vue';
import { CircleHelp, LayoutGrid, MessageCircle, Zap } from 'lucide-vue-next';
import { usePreferences } from '@/composables/usePreferences';
import AppLogo from './ui/AppLogo.vue';
import BigButton from './ui/BigButton.vue';
import BottomSheet from './ui/BottomSheet.vue';

const props = defineProps({
    userName: { type: String, required: true },
});

const { prefs, save } = usePreferences();
const open = ref(!prefs.value.tour_done);
const index = ref(0);

const steps = computed(() => [
    {
        icon: null,
        title: `Selamat datang, ${props.userName}!`,
        text: 'Hermes adalah nama dewa pengantar pesan dalam cerita Yunani kuno. Seperti dia, aplikasi ini membantu jualan Anda cepat, dan kabarnya (struk, pengingat) sampai lewat WhatsApp.',
    },
    {
        icon: LayoutGrid,
        title: 'Pindah halaman lewat menu',
        text: 'Di HP, menu ada di bagian bawah layar. Di laptop, menu ada di sebelah kiri. Pengaturan ada di menu "Lainnya".',
    },
    {
        icon: CircleHelp,
        title: 'Bingung? Ketuk "Bantuan"',
        text: 'Setiap halaman punya tombol Bantuan berisi langkah-langkah singkat.',
    },
    {
        icon: MessageCircle,
        title: 'Admin siap membantu',
        text: 'Kalau masih bingung, hubungi admin lewat WhatsApp dari menu Lainnya. Tidak perlu sungkan.',
    },
]);

const step = computed(() => steps.value[index.value]);
const isLast = computed(() => index.value === steps.value.length - 1);

function finish() {
    open.value = false;
    save({ tour_done: true });
}

function next() {
    if (isLast.value) finish();
    else index.value++;
}
</script>

<template>
    <BottomSheet v-model:open="open" persistent :show-close="false" title="">
        <div class="flex flex-col items-center text-center">
            <AppLogo v-if="!step.icon" :size="64" :wordmark="false" />
            <span v-else class="flex size-16 items-center justify-center rounded-full bg-primary-soft text-primary-ink">
                <component :is="step.icon" :size="34" aria-hidden="true" />
            </span>

            <h2 class="mt-5 text-2xl font-extrabold text-ink">{{ step.title }}</h2>
            <p class="mt-3 text-lg text-ink-soft">{{ step.text }}</p>

            <div class="mt-6 flex gap-2" :aria-label="`Langkah ${index + 1} dari ${steps.length}`">
                <span
                    v-for="(s, i) in steps"
                    :key="i"
                    class="h-2.5 rounded-full transition-all"
                    :class="i === index ? 'w-8 bg-primary' : 'w-2.5 bg-line'"
                />
            </div>
        </div>

        <div class="mt-6 flex flex-col gap-3">
            <BigButton block size="large" @click="next">
                <Zap v-if="isLast" :size="24" aria-hidden="true" />
                {{ isLast ? 'Mulai Pakai' : 'Lanjut' }}
            </BigButton>
            <BigButton v-if="!isLast" variant="ghost" block @click="finish">Lewati tur</BigButton>
        </div>
    </BottomSheet>
</template>
