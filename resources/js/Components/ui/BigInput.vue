<script setup>
/**
 * Kolom isian besar. Label selalu terlihat DI ATAS kolom (bukan placeholder),
 * pesan kesalahan tampil merah di bawahnya, dan contoh isian di `hint`.
 * Ukuran huruf >= 16px sehingga iPhone tidak memperbesar layar saat mengetik.
 */
import { ref, useId } from 'vue';
import { Eye, EyeOff } from 'lucide-vue-next';

const model = defineModel({ type: [String, Number], default: '' });

const props = defineProps({
    label: { type: String, required: true },
    type: { type: String, default: 'text' },
    hint: { type: String, default: '' },
    error: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    inputmode: { type: String, default: undefined },
    autocomplete: { type: String, default: 'off' },
    optional: { type: Boolean, default: false },
    maxlength: { type: [Number, String], default: undefined },
    prefix: { type: String, default: '' }, // contoh: "Rp"
});

const id = useId();
const showPassword = ref(false);
const inputType = () => (props.type === 'password' && showPassword.value ? 'text' : props.type);
</script>

<template>
    <div class="min-w-0">
        <label :for="id" class="mb-2 block text-lg font-bold text-ink">
            {{ label }}
            <span v-if="optional" class="font-semibold text-ink-soft">(boleh dikosongkan)</span>
        </label>
        <div
            class="relative flex min-h-touch items-stretch overflow-hidden rounded-2xl border-2 bg-surface transition-colors focus-within:border-focus focus-within:ring-4 focus-within:ring-focus/20"
            :class="error ? 'border-danger' : 'border-line'"
        >
            <span v-if="prefix" class="flex items-center bg-surface-2 px-4 text-lg font-bold text-ink-soft">{{ prefix }}</span>
            <input
                :id="id"
                v-model="model"
                :type="inputType()"
                :inputmode="inputmode"
                :placeholder="placeholder"
                :autocomplete="autocomplete"
                :maxlength="maxlength"
                :aria-invalid="!!error"
                :aria-describedby="error ? `${id}-error` : hint ? `${id}-hint` : undefined"
                class="min-w-0 flex-1 bg-transparent px-4 text-lg text-ink placeholder:text-ink-soft/60 focus:outline-none"
            />
            <button
                v-if="type === 'password'"
                type="button"
                class="m-1 flex items-center gap-1 rounded-xl px-3 font-bold text-ink-soft hover:bg-ink/5"
                @click="showPassword = !showPassword"
            >
                <component :is="showPassword ? EyeOff : Eye" :size="22" aria-hidden="true" />
                {{ showPassword ? 'Tutup' : 'Lihat' }}
            </button>
        </div>
        <p v-if="hint && !error" :id="`${id}-hint`" class="mt-2 text-base text-ink-soft">{{ hint }}</p>
        <p v-if="error" :id="`${id}-error`" role="alert" class="mt-2 text-base font-bold text-danger-ink">
            {{ error }}
        </p>
    </div>
</template>
