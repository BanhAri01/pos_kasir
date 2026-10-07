<script setup>
/**
 * Pilih / ambil foto barang. Foto dikecilkan dulu di HP (maks. 1000px, JPEG)
 * sebelum diunggah, supaya cepat dan hemat kuota internet.
 */
import { onBeforeUnmount, ref } from 'vue';
import { Camera, ImageOff, Trash2 } from 'lucide-vue-next';

const props = defineProps({
    currentUrl: { type: String, default: null },
    error: { type: String, default: '' },
});

const emit = defineEmits(['change', 'remove']);

const input = ref(null);
const preview = ref(props.currentUrl);
const working = ref(false);
let objectUrl = null;

async function compress(file) {
    const bitmap = await createImageBitmap(file).catch(() => null);
    if (!bitmap) return file;

    const scale = Math.min(1, 1000 / Math.max(bitmap.width, bitmap.height));
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(bitmap.width * scale);
    canvas.height = Math.round(bitmap.height * scale);
    canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);

    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.8));
    return blob ? new File([blob], 'foto.jpg', { type: 'image/jpeg' }) : file;
}

async function onSelect(event) {
    const file = event.target.files?.[0];
    if (!file) return;

    working.value = true;
    const small = await compress(file);
    working.value = false;

    if (objectUrl) URL.revokeObjectURL(objectUrl);
    objectUrl = URL.createObjectURL(small);
    preview.value = objectUrl;
    emit('change', small);
    event.target.value = '';
}

function remove() {
    preview.value = null;
    emit('remove');
}

onBeforeUnmount(() => objectUrl && URL.revokeObjectURL(objectUrl));
</script>

<template>
    <div>
        <span class="mb-2 block text-lg font-bold text-ink">
            Foto <span class="font-semibold text-ink-soft">(boleh dikosongkan)</span>
        </span>
        <div class="flex items-center gap-4">
            <div class="flex size-28 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-surface-2 text-ink-soft">
                <img v-if="preview" :src="preview" alt="Foto barang" class="size-full object-cover" />
                <ImageOff v-else :size="36" aria-hidden="true" />
            </div>
            <div class="flex flex-1 flex-col gap-2">
                <button
                    type="button"
                    class="pressable flex min-h-touch items-center justify-center gap-2 rounded-2xl border-2 border-line px-4 text-lg font-bold text-ink hover:border-ink-soft"
                    :disabled="working"
                    @click="input.click()"
                >
                    <Camera :size="24" aria-hidden="true" />
                    {{ working ? 'Mengecilkan foto...' : preview ? 'Ganti Foto' : 'Ambil / Pilih Foto' }}
                </button>
                <button
                    v-if="preview"
                    type="button"
                    class="pressable flex min-h-12 items-center justify-center gap-2 rounded-2xl text-base font-bold text-danger-ink hover:bg-danger-soft"
                    @click="remove"
                >
                    <Trash2 :size="20" aria-hidden="true" />
                    Hapus Foto
                </button>
            </div>
        </div>
        <input ref="input" type="file" accept="image/*" class="hidden" @change="onSelect" />
        <p v-if="error" role="alert" class="mt-2 text-base font-bold text-danger-ink">{{ error }}</p>
    </div>
</template>
