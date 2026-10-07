<script setup>
/**
 * Layar dapur / barista. Pesanan baru muncul sendiri (cek tiap 5 detik) dan berbunyi.
 * Satu ketukan besar = lanjut ke tahap berikutnya: Baru → Dibuat → Siap → Diantar.
 */
import { computed, ref, watch } from 'vue';
import { Head, router, usePoll } from '@inertiajs/vue3';
import { ChefHat, Clock, Maximize } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import { useFeedback } from '@/composables/useFeedback';

defineOptions({ layout: AppLayout });

const props = defineProps({ tickets: { type: Array, required: true } });

usePoll(5000, { only: ['tickets'] });

const { success } = useFeedback();
const filter = ref('active');
const busy = ref(null);

const STEPS = {
    new: { label: 'Baru', action: 'Mulai Buat', tone: 'border-accent bg-accent-soft' },
    preparing: { label: 'Sedang dibuat', action: 'Sudah Siap', tone: 'border-primary bg-surface' },
    ready: { label: 'Siap diantar', action: 'Sudah Diantar', tone: 'border-primary bg-primary-soft' },
    served: { label: 'Sudah diantar', action: null, tone: 'border-line bg-surface-2 opacity-70' },
};

const visible = computed(() => props.tickets.filter((t) => (filter.value === 'active' ? t.status !== 'served' : t.status === 'served')));
const counts = computed(() => ({
    new: props.tickets.filter((t) => t.status === 'new').length,
    active: props.tickets.filter((t) => t.status !== 'served').length,
}));

// Bunyi saat ada pesanan baru masuk.
watch(
    () => counts.value.new,
    (now, before) => {
        if (now > before) success();
    },
);

function advance(ticket) {
    busy.value = ticket.id;
    router.post(route('kitchen.advance', ticket.id), {}, { preserveScroll: true, only: ['tickets'], onFinish: () => (busy.value = null) });
}

function fullscreen() {
    document.documentElement.requestFullscreen?.();
}
</script>

<template>
    <Head title="Layar Dapur" />
    <PageHeader title="Layar Dapur" :subtitle="`${counts.active} pesanan belum selesai`">
        <template #action>
            <button type="button" class="pressable hidden min-h-touch items-center gap-2 rounded-2xl border-2 border-line px-4 text-lg font-bold text-ink md:flex" @click="fullscreen">
                <Maximize :size="22" aria-hidden="true" /> Layar Penuh
            </button>
        </template>
    </PageHeader>

    <SegmentedControl
        v-model="filter"
        label="Tampilkan"
        class="mb-5 max-w-md"
        :options="[
            { value: 'active', label: `Belum selesai (${counts.active})` },
            { value: 'served', label: 'Sudah diantar' },
        ]"
    />

    <EmptyState v-if="!visible.length" title="Belum ada pesanan" message="Pesanan dari kasir akan muncul di sini secara otomatis.">
        <template #icon><ChefHat :size="48" aria-hidden="true" /></template>
    </EmptyState>

    <ul class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
        <li v-for="t in visible" :key="t.id" class="flex min-w-0 flex-col rounded-3xl border-4 p-4" :class="STEPS[t.status].tone">
            <div class="mb-3 flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="truncate font-display text-2xl font-extrabold text-ink">{{ t.label }}</p>
                    <p class="text-base font-bold text-ink-soft">{{ STEPS[t.status].label }}</p>
                </div>
                <span class="flex shrink-0 items-center gap-1 rounded-full px-3 py-1 text-base font-bold" :class="t.minutes >= 15 && t.status !== 'served' ? 'bg-danger text-white' : 'bg-surface-2 text-ink'">
                    <Clock :size="18" aria-hidden="true" /> {{ t.minutes }} mnt
                </span>
            </div>
            <ul class="mb-4 flex-1 space-y-2">
                <li v-for="(item, i) in t.items" :key="i" class="text-xl leading-snug text-ink">
                    <span class="font-extrabold">{{ item.qty }}×</span> {{ item.name }}
                    <span v-if="item.modifiers?.length" class="block text-lg text-ink-soft">{{ item.modifiers.join(', ') }}</span>
                    <span v-if="item.note" class="block text-lg font-bold text-danger-ink">“{{ item.note }}”</span>
                </li>
            </ul>
            <p v-if="t.note" class="mb-3 rounded-xl bg-surface p-2 text-lg font-bold text-danger-ink">Catatan: {{ t.note }}</p>
            <button
                v-if="STEPS[t.status].action"
                type="button"
                class="pressable min-h-touch-lg w-full rounded-2xl bg-primary px-4 text-xl font-extrabold text-on-primary disabled:opacity-60"
                :disabled="busy === t.id"
                @click="advance(t)"
            >
                {{ STEPS[t.status].action }}
            </button>
        </li>
    </ul>
</template>
