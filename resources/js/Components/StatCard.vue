<script setup>
/** Kotak angka utama + naik/turun dibanding periode sebelumnya. */
import { computed } from 'vue';
import { TrendingDown, TrendingUp } from 'lucide-vue-next';
import { formatRupiah } from '@/composables/useRupiah';

const props = defineProps({
    label: { type: String, required: true },
    value: { type: Number, required: true },
    money: { type: Boolean, default: true },
    change: { type: Number, default: null }, // persen
    hint: { type: String, default: '' },
    tone: { type: String, default: 'default' }, // default | positive | negative
    big: { type: Boolean, default: false },
});

const text = computed(() => (props.money ? formatRupiah(props.value) : props.value.toLocaleString('id-ID')));
const valueTone = computed(() => ({ default: 'text-ink', positive: 'text-primary-ink', negative: 'text-danger-ink' })[props.tone]);
</script>

<template>
    <div class="card flex min-w-0 flex-col gap-1 p-4">
        <p class="text-base text-ink-soft">{{ label }}</p>
        <p class="font-display leading-tight font-extrabold whitespace-nowrap tabular-nums" :class="[valueTone, big ? 'text-3xl xl:text-2xl 2xl:text-3xl' : 'text-2xl']">{{ text }}</p>
        <p v-if="change !== null" class="flex items-center gap-1 text-base font-bold" :class="change >= 0 ? 'text-primary-ink' : 'text-danger-ink'">
            <component :is="change >= 0 ? TrendingUp : TrendingDown" :size="18" aria-hidden="true" />
            {{ change >= 0 ? 'Naik' : 'Turun' }} {{ Math.abs(change).toLocaleString('id-ID') }}%
            <span class="font-normal text-ink-soft">{{ hint }}</span>
        </p>
        <p v-else-if="hint" class="text-base text-ink-soft">{{ hint }}</p>
    </div>
</template>
