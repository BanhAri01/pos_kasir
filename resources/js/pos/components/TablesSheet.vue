<script setup>
/** Pilih meja. Meja yang masih ada pesanan belum dibayar ditandai "Terisi". */
import { computed } from 'vue';
import BottomSheet from '@/Components/ui/BottomSheet.vue';
import { formatRupiah } from '@/composables/useRupiah';
import { openTable, saveToTable, store } from '../store';
import { lineMoney } from '../lib/calculator';
import { showToast } from '../lib/toast';

const open = defineModel('open', { type: Boolean, default: false });

const areas = computed(() => {
    const groups = {};
    for (const t of store.boot.tables) (groups[t.area || 'Meja'] ??= []).push(t);
    return Object.entries(groups);
});

const billTotal = (tableId) => (store.bills[tableId]?.items ?? []).reduce((s, l) => s + lineMoney(l.unit_price, l.qty) - (l.discount_amount ?? 0), 0);

async function choose(table) {
    // Pesanan di keranjang (meja lain) disimpan dulu supaya tidak hilang.
    if (store.tableId && store.tableId !== table.id && store.cart.length) {
        await saveToTable();
        showToast('Pesanan meja sebelumnya sudah disimpan.', 'info');
    }
    openTable(table.id);
    open.value = false;
}
</script>

<template>
    <BottomSheet v-model:open="open" title="Pilih Meja">
        <div v-for="[area, tables] in areas" :key="area" class="mb-5">
            <p class="mb-2 text-lg font-bold text-ink-soft">{{ area }}</p>
            <div class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                <button
                    v-for="t in tables"
                    :key="t.id"
                    type="button"
                    class="pressable flex min-h-24 flex-col items-center justify-center gap-1 rounded-2xl border-2 p-2 text-center"
                    :class="store.bills[t.id] ? 'border-accent bg-accent-soft text-accent-ink' : store.tableId === t.id ? 'border-primary bg-primary-soft text-primary-ink' : 'border-line text-ink'"
                    @click="choose(t)"
                >
                    <span class="font-display text-2xl font-extrabold">{{ t.name }}</span>
                    <span class="text-sm font-bold">{{ store.bills[t.id] ? formatRupiah(billTotal(t.id)) : 'Kosong' }}</span>
                </button>
            </div>
        </div>
    </BottomSheet>
</template>
