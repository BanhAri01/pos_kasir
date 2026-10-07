<script setup>
/**
 * Sakelar besar dengan tulisan "Nyala" / "Mati" supaya status jelas
 * tanpa harus menebak dari warna.
 */
const model = defineModel({ type: Boolean, default: false });

defineProps({
    label: { type: String, required: true }, // untuk pembaca layar
    disabled: { type: Boolean, default: false },
});
</script>

<template>
    <button
        type="button"
        role="switch"
        :aria-checked="model"
        :aria-label="label"
        :disabled="disabled"
        class="flex min-h-touch shrink-0 items-center gap-3 rounded-full px-1 disabled:opacity-60"
        @click="model = !model"
    >
        <span class="w-12 text-right text-base font-extrabold" :class="model ? 'text-primary-ink' : 'text-ink-soft'">
            {{ model ? 'Nyala' : 'Mati' }}
        </span>
        <span
            class="relative inline-flex h-10 w-18 items-center rounded-full transition-colors duration-200"
            :class="model ? 'bg-primary' : 'bg-ink-soft/40'"
        >
            <span
                class="inline-block size-8 rounded-full bg-white shadow-md transition-transform duration-200"
                :class="model ? 'translate-x-9' : 'translate-x-1'"
            />
        </span>
    </button>
</template>
