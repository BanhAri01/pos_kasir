<script setup>
/**
 * Scan barcode pakai kamera HP (Chrome Android). iPhone belum mendukung fitur ini di browser,
 * jadi tombolnya disembunyikan di sana; alat scanner USB/Bluetooth tetap bisa dipakai.
 */
import { onBeforeUnmount, ref, watch } from 'vue';
import BottomSheet from '@/Components/ui/BottomSheet.vue';

const open = defineModel('open', { type: Boolean, default: false });
const emit = defineEmits(['detected']);

const video = ref(null);
const error = ref('');
let stream = null;
let timer = null;

async function start() {
    error.value = '';
    try {
        stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
        video.value.srcObject = stream;
        await video.value.play();
        const detector = new window.BarcodeDetector({ formats: ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128', 'code_39', 'qr_code'] });
        timer = setInterval(async () => {
            const codes = await detector.detect(video.value).catch(() => []);
            if (codes.length) {
                emit('detected', codes[0].rawValue);
                open.value = false;
            }
        }, 300);
    } catch {
        error.value = 'Kamera tidak bisa dibuka. Izinkan akses kamera di pengaturan browser, lalu coba lagi.';
    }
}

function stop() {
    clearInterval(timer);
    stream?.getTracks().forEach((t) => t.stop());
    stream = null;
}

watch(open, (v) => (v ? setTimeout(start, 50) : stop()));
onBeforeUnmount(stop);
</script>

<template>
    <BottomSheet v-model:open="open" title="Scan Barcode">
        <p v-if="error" class="rounded-2xl bg-danger-soft p-4 text-lg font-bold text-danger-ink">{{ error }}</p>
        <div v-else class="overflow-hidden rounded-2xl bg-black">
            <video ref="video" class="aspect-[4/3] w-full object-cover" playsinline muted />
        </div>
        <p class="mt-3 text-center text-lg text-ink-soft">Arahkan kamera ke barcode barang.</p>
    </BottomSheet>
</template>
