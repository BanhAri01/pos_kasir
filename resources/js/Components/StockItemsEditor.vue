<script setup>
/**
 * Daftar barang + jumlah untuk dokumen stok (masuk, keluar, kirim).
 * Cari / scan barang, lalu atur jumlahnya dengan tombol − / +.
 */
import { computed } from 'vue';
import { Package, Trash2 } from 'lucide-vue-next';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import ProductPicker from '@/Components/ui/ProductPicker.vue';
import QtyInput from '@/Components/ui/QtyInput.vue';

/** items: [{ product_id, name, unit, decimal, stock, qty, unit_cost? }] */
const items = defineModel({ type: Array, required: true });

defineProps({
    withCost: { type: Boolean, default: false },
    errors: { type: Object, default: () => ({}) },
});

const selectedIds = computed(() => items.value.map((i) => i.product_id));

function add(product) {
    items.value = [
        ...items.value,
        {
            product_id: product.id,
            name: product.name,
            unit: product.unit,
            decimal: product.unit_allows_decimal,
            stock: product.stock,
            image_url: product.image_url,
            qty: '1',
            unit_cost: product.cost_price || null,
        },
    ];
}

function remove(index) {
    items.value = items.value.filter((_, i) => i !== index);
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <ProductPicker :exclude-ids="selectedIds" @select="add" />
        <p v-if="errors.items" role="alert" class="text-lg font-bold text-danger-ink">{{ errors.items }}</p>

        <ul v-if="items.length" class="flex flex-col gap-3">
            <li v-for="(item, index) in items" :key="item.product_id" class="rounded-2xl border-2 border-line bg-surface p-4">
                <div class="mb-3 flex items-center gap-3">
                    <img v-if="item.image_url" :src="item.image_url" alt="" class="size-12 rounded-xl object-cover" />
                    <span v-else class="flex size-12 items-center justify-center rounded-xl bg-surface-2 text-ink-soft">
                        <Package :size="24" aria-hidden="true" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-lg font-extrabold text-ink">{{ item.name }}</p>
                        <p v-if="item.stock !== null" class="text-base text-ink-soft">Stok sekarang: {{ item.stock }} {{ item.unit ?? '' }}</p>
                    </div>
                    <button
                        type="button"
                        class="pressable flex size-12 items-center justify-center rounded-xl text-danger-ink hover:bg-danger-soft"
                        :aria-label="`Hapus ${item.name} dari daftar`"
                        @click="remove(index)"
                    >
                        <Trash2 :size="24" aria-hidden="true" />
                    </button>
                </div>
                <div class="flex flex-col gap-3">
                    <QtyInput
                        v-model="item.qty"
                        :label="`Jumlah ${item.name}`"
                        compact
                        :unit="item.unit"
                        :decimal="item.decimal"
                        :error="errors[`items.${index}.qty`]"
                    />
                    <MoneyInput v-if="withCost" v-model="item.unit_cost" :label="`Harga beli per ${item.unit?.toLowerCase() ?? 'barang'}`" optional />
                </div>
            </li>
        </ul>
    </div>
</template>
