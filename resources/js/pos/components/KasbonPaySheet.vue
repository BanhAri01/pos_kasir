<script setup>
/** Terima pembayaran utang (kasbon) pelanggan di kasir. Bisa sebagian (cicil). */
import { ref, watch } from 'vue';
import BigButton from '@/Components/ui/BigButton.vue';
import BottomSheet from '@/Components/ui/BottomSheet.vue';
import MoneyDisplay from '@/Components/ui/MoneyDisplay.vue';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import { formatRupiah } from '@/composables/useRupiah';
import { payReceivable, store } from '../store';
import { showToast } from '../lib/toast';

const open = defineModel('open', { type: Boolean, default: false });

const props = defineProps({
    customer: { type: Object, default: null },
});

const amount = ref(null);
const methodId = ref(null);
const saving = ref(false);

watch(open, (v) => {
    if (v) {
        amount.value = props.customer?.balance ?? null;
        methodId.value = store.boot.payment_methods.find((m) => m.type === 'cash')?.id ?? store.boot.payment_methods[0]?.id;
    }
});

async function save() {
    if (!amount.value || amount.value <= 0) return showToast('Isi jumlah yang dibayar.', 'error');
    if (amount.value > props.customer.balance) return showToast(`Melebihi utang. Sisa utang ${formatRupiah(props.customer.balance)}.`, 'error');
    saving.value = true;
    try {
        await payReceivable(props.customer, amount.value, methodId.value);
        const left = props.customer.balance;
        showToast(left > 0 ? `Pembayaran dicatat. Sisa utang ${formatRupiah(left)}.` : 'Lunas! Terima kasih.');
        open.value = false;
    } catch (e) {
        showToast(e.message, 'error');
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <BottomSheet v-model:open="open" :title="`Bayar Utang ${customer?.name ?? ''}`">
        <div v-if="customer" class="flex flex-col gap-5">
            <div class="rounded-3xl bg-surface-2 p-5 text-center"><MoneyDisplay :amount="customer.balance" size="xl" label="Sisa utang" /></div>
            <div>
                <p class="mb-2 text-lg font-bold text-ink">Dibayar dengan</p>
                <div class="grid grid-cols-2 gap-2">
                    <button
                        v-for="m in store.boot.payment_methods"
                        :key="m.id"
                        type="button"
                        class="pressable min-h-touch rounded-2xl border-2 text-base font-bold"
                        :class="methodId === m.id ? 'border-primary bg-primary-soft text-primary-ink' : 'border-line text-ink'"
                        @click="methodId = m.id"
                    >
                        {{ m.name }}
                    </button>
                </div>
            </div>
            <MoneyInput v-model="amount" label="Jumlah yang dibayar" hint="Boleh sebagian (cicil)." />
            <BigButton size="large" block :loading="saving" @click="save">Simpan Pembayaran</BigButton>
        </div>
    </BottomSheet>
</template>
