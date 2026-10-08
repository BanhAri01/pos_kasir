<script setup>
/** Diskon untuk seluruh transaksi: persen atau rupiah. */
import { ref, watch } from 'vue';
import BigButton from '@/Components/ui/BigButton.vue';
import BottomSheet from '@/Components/ui/BottomSheet.vue';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import { store } from '../store';

const open = defineModel('open', { type: Boolean, default: false });

const type = ref('percent');
const percent = ref(null);
const amount = ref(null);
const quickPercents = [5, 10, 15, 20, 50];

watch(open, (v) => {
    if (!v) return;
    type.value = store.discount.type ?? 'percent';
    percent.value = store.discount.type === 'percent' ? store.discount.value / 100 : null;
    amount.value = store.discount.type === 'amount' ? store.discount.value : null;
});

function save() {
    store.redeemPoints = 0;
    if (type.value === 'percent') {
        const p = Math.min(100, Math.max(0, Number(percent.value) || 0));
        store.discount = p > 0 ? { type: 'percent', value: Math.round(p * 100) } : { type: null, value: 0 };
    } else {
        store.discount = amount.value > 0 ? { type: 'amount', value: amount.value } : { type: null, value: 0 };
    }
    open.value = false;
}

function remove() {
    store.redeemPoints = 0;
    store.discount = { type: null, value: 0 };
    open.value = false;
}
</script>

<template>
    <BottomSheet v-model:open="open" title="Diskon Transaksi">
        <div class="flex flex-col gap-5">
            <SegmentedControl v-model="type" label="Jenis diskon" :options="[{ value: 'percent', label: 'Persen (%)' }, { value: 'amount', label: 'Rupiah (Rp)' }]" />

            <template v-if="type === 'percent'">
                <div class="grid grid-cols-5 gap-2">
                    <button
                        v-for="p in quickPercents"
                        :key="p"
                        type="button"
                        class="pressable min-h-touch rounded-2xl border-2 text-lg font-extrabold"
                        :class="Number(percent) === p ? 'border-primary bg-primary text-on-primary' : 'border-line text-ink'"
                        @click="percent = p"
                    >
                        {{ p }}%
                    </button>
                </div>
                <label class="block">
                    <span class="mb-2 block text-lg font-bold text-ink">Atau ketik persen</span>
                    <input
                        v-model="percent"
                        type="number"
                        inputmode="decimal"
                        min="0"
                        max="100"
                        class="min-h-touch w-full rounded-2xl border-2 border-line bg-surface px-4 font-display text-xl font-bold text-ink focus:border-focus focus:outline-none"
                    />
                </label>
            </template>
            <MoneyInput v-else v-model="amount" label="Potongan harga" />

            <BigButton size="large" block @click="save">Pakai Diskon</BigButton>
            <BigButton v-if="store.discount.type" variant="ghost" block class="text-danger-ink" @click="remove">Hapus Diskon</BigButton>
        </div>
    </BottomSheet>
</template>
