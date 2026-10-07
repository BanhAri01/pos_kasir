<script setup>
/**
 * Papan angka besar untuk mengetik PIN (seperti di ATM / HP).
 * Bulatan di atas menunjukkan berapa angka yang sudah diketik.
 * Keyboard fisik (laptop) juga bisa dipakai: angka, Backspace, Enter.
 */
import { onBeforeUnmount, onMounted } from 'vue';
import { Delete } from 'lucide-vue-next';

const pin = defineModel({ type: String, default: '' });

const props = defineProps({
    maxLength: { type: Number, default: 6 },
    minLength: { type: Number, default: 4 },
    error: { type: String, default: '' },
    loading: { type: Boolean, default: false },
});

const emit = defineEmits(['submit']);

function press(digit) {
    if (pin.value.length < props.maxLength) pin.value += digit;
}

function backspace() {
    pin.value = pin.value.slice(0, -1);
}

function submit() {
    if (pin.value.length >= props.minLength && !props.loading) emit('submit');
}

function onKey(event) {
    if (/^\d$/.test(event.key)) press(event.key);
    else if (event.key === 'Backspace') backspace();
    else if (event.key === 'Enter') submit();
}

onMounted(() => window.addEventListener('keydown', onKey));
onBeforeUnmount(() => window.removeEventListener('keydown', onKey));

const keyClass =
    'pressable min-h-touch-lg rounded-2xl bg-surface font-display text-3xl font-extrabold text-ink shadow-card hover:bg-surface-2';
</script>

<template>
    <div class="mx-auto w-full max-w-sm">
        <div class="mb-4 flex justify-center gap-3" aria-live="polite" :aria-label="`${pin.length} angka sudah diketik`">
            <span
                v-for="i in maxLength"
                :key="i"
                class="size-5 rounded-full border-2 transition-colors"
                :class="i <= pin.length ? 'border-primary bg-primary' : 'border-ink-soft bg-transparent'"
            />
        </div>
        <p v-if="error" role="alert" class="mb-4 animate-pop-in text-center text-lg font-bold text-danger-ink">{{ error }}</p>

        <div class="grid grid-cols-3 gap-3">
            <button v-for="n in [1, 2, 3, 4, 5, 6, 7, 8, 9]" :key="n" type="button" :class="keyClass" @click="press(String(n))">
                {{ n }}
            </button>
            <button
                type="button"
                class="pressable flex min-h-touch-lg flex-col items-center justify-center rounded-2xl text-base font-bold text-ink hover:bg-ink/5"
                @click="backspace"
            >
                <Delete :size="28" aria-hidden="true" />
                Hapus
            </button>
            <button type="button" :class="keyClass" @click="press('0')">0</button>
            <button
                type="button"
                class="pressable min-h-touch-lg rounded-2xl bg-primary text-xl font-extrabold text-on-primary disabled:opacity-50"
                :disabled="pin.length < minLength || loading"
                @click="submit"
            >
                Masuk
            </button>
        </div>
    </div>
</template>
