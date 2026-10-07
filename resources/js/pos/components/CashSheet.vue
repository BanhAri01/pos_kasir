<script setup>
/** Catat uang masuk / keluar laci di luar penjualan (beli es batu, tambah uang kembalian). */
import { computed, ref, watch } from 'vue';
import BigButton from '@/Components/ui/BigButton.vue';
import BottomSheet from '@/Components/ui/BottomSheet.vue';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import { addCashMovement } from '../store';
import { showToast } from '../lib/toast';

const open = defineModel('open', { type: Boolean, default: false });

const type = ref('out');
const amount = ref(null);
const reason = ref('');
const saving = ref(false);

const quickReasons = computed(() =>
    type.value === 'out' ? ['Beli es batu', 'Beli gas', 'Bayar parkir', 'Beli bahan'] : ['Tambah uang kembalian', 'Setoran pemilik'],
);

watch(open, (v) => {
    if (v) {
        type.value = 'out';
        amount.value = null;
        reason.value = '';
    }
});

async function save() {
    if (!amount.value) return showToast('Isi jumlah uangnya dulu.', 'error');
    if (!reason.value.trim()) return showToast('Tulis untuk apa uang ini.', 'error');
    saving.value = true;
    try {
        const res = await addCashMovement(type.value, amount.value, reason.value.trim());
        showToast(res.message);
        open.value = false;
    } catch (e) {
        showToast(e.message, 'error');
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <BottomSheet v-model:open="open" title="Uang Masuk / Keluar">
        <div class="flex flex-col gap-5">
            <SegmentedControl v-model="type" label="Jenis" :options="[{ value: 'out', label: 'Uang Keluar' }, { value: 'in', label: 'Uang Masuk' }]" />
            <MoneyInput v-model="amount" label="Jumlah uang" />
            <div>
                <span class="mb-2 block text-lg font-bold text-ink">Untuk apa?</span>
                <div class="mb-2 flex flex-wrap gap-2">
                    <button
                        v-for="r in quickReasons"
                        :key="r"
                        type="button"
                        class="pressable min-h-12 rounded-full border-2 px-4 text-base font-bold"
                        :class="reason === r ? 'border-primary bg-primary-soft text-primary-ink' : 'border-line text-ink'"
                        @click="reason = r"
                    >
                        {{ r }}
                    </button>
                </div>
                <input v-model="reason" type="text" aria-label="Untuk apa" placeholder="Atau ketik sendiri" class="min-h-touch w-full rounded-2xl border-2 border-line bg-surface px-4 text-lg text-ink focus:border-focus focus:outline-none" />
            </div>
            <BigButton size="large" block :loading="saving" :variant="type === 'out' ? 'danger' : 'primary'" @click="save">Simpan</BigButton>
        </div>
    </BottomSheet>
</template>
