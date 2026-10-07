<script setup>
/** Layar TV antrean: nomor yang sedang dipanggil besar-besar, berbunyi saat nomor berganti. */
import { computed, watch } from 'vue';
import { Head, usePoll } from '@inertiajs/vue3';
import AppLogo from '@/Components/ui/AppLogo.vue';
import { useFeedback } from '@/composables/useFeedback';

const props = defineProps({
    tickets: { type: Array, required: true },
    current: { type: Array, required: true },
    waitingCount: { type: Number, required: true },
    businessName: { type: String, required: true },
});

usePoll(4000);

const { success } = useFeedback();
const main = computed(() => props.current[0] ?? null);
const next = computed(() => props.tickets.filter((t) => t.status === 'waiting').slice(0, 6));

watch(
    () => main.value?.id,
    (now, before) => {
        if (now && now !== before) success();
    },
);
</script>

<template>
    <Head title="Antrean" />
    <div class="flex min-h-dvh flex-col bg-[#062a1f] p-6 text-white md:p-10">
        <header class="mb-6 flex items-center justify-between gap-4">
            <p class="font-display text-3xl font-extrabold md:text-5xl">{{ businessName }}</p>
            <AppLogo :size="48" variant="inverse" class="hidden md:flex" />
        </header>

        <main class="grid flex-1 gap-6 lg:grid-cols-[2fr_1fr]">
            <section class="flex flex-col items-center justify-center rounded-[2rem] bg-brand p-8 text-center">
                <p class="text-2xl font-bold opacity-90 md:text-4xl">Nomor antrean</p>
                <p v-if="main" class="font-display text-[8rem] leading-none font-extrabold tabular-nums md:text-[14rem]">{{ main.number }}</p>
                <p v-else class="mt-4 text-3xl font-bold opacity-90 md:text-5xl">Silakan tunggu dipanggil</p>
                <p v-if="main?.customer_name" class="text-3xl font-bold md:text-5xl">{{ main.customer_name }}</p>
                <p v-if="main?.staff" class="mt-2 text-2xl opacity-90 md:text-3xl">dilayani {{ main.staff }}</p>
            </section>
            <section class="rounded-[2rem] bg-white/10 p-6">
                <p class="mb-4 text-2xl font-bold md:text-3xl">Berikutnya ({{ waitingCount }})</p>
                <ul class="grid grid-cols-3 gap-3 lg:grid-cols-2">
                    <li v-for="t in next" :key="t.id" class="rounded-2xl bg-white/10 p-4 text-center font-display text-4xl font-extrabold tabular-nums md:text-5xl">{{ t.number }}</li>
                </ul>
                <p v-if="!next.length" class="text-xl opacity-80">Tidak ada yang menunggu.</p>
            </section>
        </main>
    </div>
</template>
