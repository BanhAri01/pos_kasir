<script setup>
/** Atur printer struk untuk HP ini. */
import { reactive, watch } from 'vue';
import { Printer } from 'lucide-vue-next';
import BigButton from '@/Components/ui/BigButton.vue';
import BottomSheet from '@/Components/ui/BottomSheet.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import ToggleSwitch from '@/Components/ui/ToggleSwitch.vue';
import { bluetoothSupported, loadPrinterSettings, printTestPage, savePrinterSettings } from '../lib/printer';
import { showToast } from '../lib/toast';

const open = defineModel('open', { type: Boolean, default: false });

const settings = reactive(loadPrinterSettings());

const methods = [
    { value: 'none', title: 'Tidak pakai printer', text: 'Struk dikirim lewat WhatsApp saja.' },
    { value: 'rawbt', title: 'Printer Bluetooth (lewat RawBT)', text: 'Paling cocok untuk printer kecil yang banyak dijual. Pasang aplikasi gratis "RawBT" dari Play Store, lalu sambungkan printer di aplikasi itu.' },
    { value: 'bluetooth', title: 'Bluetooth langsung', text: 'Untuk printer yang mendukung Bluetooth BLE. Hanya di Chrome Android / laptop.' },
    { value: 'browser', title: 'Printer biasa / laptop', text: 'Memakai jendela cetak browser. Bisa juga untuk simpan PDF.' },
];

watch(settings, () => savePrinterSettings({ ...settings }), { deep: true });

async function test() {
    try {
        await printTestPage(settings);
        showToast('Tes cetak dikirim ke printer.', 'info');
    } catch (e) {
        showToast(e.message || 'Printer tidak tersambung. Pastikan printer menyala dan Bluetooth aktif.', 'error');
    }
}
</script>

<template>
    <BottomSheet v-model:open="open" title="Printer Struk">
        <div class="flex flex-col gap-5">
            <div class="flex flex-col gap-2" role="radiogroup" aria-label="Cara cetak">
                <button
                    v-for="m in methods"
                    :key="m.value"
                    type="button"
                    role="radio"
                    :aria-checked="settings.method === m.value"
                    :disabled="m.value === 'bluetooth' && !bluetoothSupported()"
                    class="pressable rounded-2xl border-2 p-4 text-left disabled:opacity-50"
                    :class="settings.method === m.value ? 'border-primary bg-primary-soft' : 'border-line'"
                    @click="settings.method = m.value"
                >
                    <span class="block text-lg font-extrabold text-ink">{{ m.title }}</span>
                    <span class="block text-base text-ink-soft">{{ m.text }}</span>
                    <span v-if="m.value === 'bluetooth' && !bluetoothSupported()" class="block text-base font-bold text-warn-ink">Tidak didukung di browser ini.</span>
                </button>
            </div>

            <template v-if="settings.method !== 'none'">
                <div>
                    <p class="mb-2 text-lg font-bold text-ink">Lebar kertas</p>
                    <SegmentedControl v-model="settings.paper" label="Lebar kertas" :options="[{ value: '58', label: '58 mm (kecil)' }, { value: '80', label: '80 mm (besar)' }]" />
                </div>
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                    <span class="min-w-48 flex-1 text-lg text-ink">Cetak otomatis setelah bayar</span>
                    <ToggleSwitch v-model="settings.autoPrint" label="Cetak otomatis" class="ml-auto" />
                </div>
                <BigButton variant="secondary" block @click="test"><Printer :size="22" aria-hidden="true" /> Tes Cetak</BigButton>
            </template>

            <BigButton block @click="open = false">Selesai</BigButton>
        </div>
    </BottomSheet>
</template>
