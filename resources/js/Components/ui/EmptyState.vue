<script setup>
/** Tampilan saat data masih kosong: jelaskan dan beri satu tombol untuk mulai. */
defineProps({
    title: { type: String, required: true },
    message: { type: String, default: '' },
});
</script>

<template>
    <div class="rounded-3xl border-2 border-dashed border-line bg-surface p-8 text-center">
        <div v-if="$slots.icon" class="mb-4 flex justify-center text-ink-soft">
            <slot name="icon" />
        </div>
        <h3 class="text-xl font-extrabold text-ink">{{ title }}</h3>
        <p v-if="message" class="mt-2 text-lg text-ink-soft">{{ message }}</p>
        <div v-if="$slots.default" class="mt-6">
            <slot />
        </div>
    </div>
</template>
