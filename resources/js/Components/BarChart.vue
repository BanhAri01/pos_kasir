<script setup>
/**
 * Grafik batang sederhana tanpa pustaka tambahan (ringan untuk HP murah).
 * Ketuk batang untuk melihat angkanya. Bisa dibaca pembaca layar lewat tabel tersembunyi.
 *
 * points: [{ label, value, sub? }]
 */
import { computed, ref } from 'vue';
import { formatRupiah } from '@/composables/useRupiah';

const props = defineProps({
    points: { type: Array, required: true },
    money: { type: Boolean, default: true },
    height: { type: Number, default: 180 },
    label: { type: String, default: 'Grafik' },
    highlightLast: { type: Boolean, default: false },
});

const selected = ref(null);
const max = computed(() => Math.max(1, ...props.points.map((p) => p.value)));
const format = (v) => (props.money ? formatRupiah(v) : String(v));
const showEvery = computed(() => Math.ceil(props.points.length / 12)); // label sumbu X tidak berdempetan
const active = computed(() => (selected.value !== null ? props.points[selected.value] : null));
</script>

<template>
    <figure class="min-w-0">
        <figcaption class="mb-2 flex min-h-8 items-baseline justify-between gap-2">
            <span class="text-base text-ink-soft">{{ label }}</span>
            <span v-if="active" class="text-right text-base font-bold text-ink">{{ active.label }}: {{ format(active.value) }}<template v-if="active.sub"> · {{ active.sub }}</template></span>
        </figcaption>
        <div class="flex items-end gap-[2px] sm:gap-1" :style="{ height: `${height}px` }" aria-hidden="true">
            <button
                v-for="(p, i) in points"
                :key="i"
                type="button"
                tabindex="-1"
                class="group relative flex h-full min-w-0 flex-1 items-end"
                @click="selected = selected === i ? null : i"
                @mouseenter="selected = i"
            >
                <span
                    class="w-full rounded-t-md transition-colors"
                    :class="selected === i ? 'bg-primary-press' : highlightLast && i === points.length - 1 ? 'bg-accent' : 'bg-primary/70 group-hover:bg-primary'"
                    :style="{ height: `${Math.max(p.value ? 3 : 1, (p.value / max) * 100)}%` }"
                />
            </button>
        </div>
        <div class="mt-1 flex gap-[2px] sm:gap-1" aria-hidden="true">
            <span v-for="(p, i) in points" :key="i" class="min-w-0 flex-1 truncate text-center text-xs text-ink-soft">{{ i % showEvery === 0 ? p.label : '' }}</span>
        </div>
        <table class="sr-only">
            <caption>{{ label }}</caption>
            <tr v-for="(p, i) in points" :key="i"><th>{{ p.label }}</th><td>{{ format(p.value) }}</td></tr>
        </table>
    </figure>
</template>
