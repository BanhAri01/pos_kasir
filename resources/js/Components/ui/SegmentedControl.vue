<script setup>
/**
 * Pilihan berdampingan (seperti di pengaturan iPhone), tapi besar dan bertulisan.
 * Contoh: Ikuti HP | Terang | Gelap
 *
 * options: [{ value, label, icon? }]
 */
defineProps({
    label: { type: String, required: true },
    options: { type: Array, required: true },
});

const model = defineModel({ type: [String, Number, Boolean], default: null });
</script>

<template>
    <div role="radiogroup" :aria-label="label" class="grid auto-cols-[minmax(0,1fr)] grid-flow-col gap-1.5 rounded-2xl bg-surface-2 p-1.5">
        <button
            v-for="option in options"
            :key="String(option.value)"
            type="button"
            role="radio"
            :aria-checked="model === option.value"
            class="pressable flex min-h-touch min-w-0 flex-col items-center justify-center gap-1 rounded-xl px-1.5 py-2 text-center text-base leading-tight font-bold wrap-break-word transition-colors"
            :class="model === option.value ? 'bg-surface text-primary-ink shadow-card' : 'text-ink-soft hover:text-ink'"
            @click="model = option.value"
        >
            <component :is="option.icon" v-if="option.icon" :size="24" aria-hidden="true" />
            {{ option.label }}
        </button>
    </div>
</template>
