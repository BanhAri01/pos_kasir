<script setup>
/**
 * Sortir telur campur dari kasir saat stok jenis yang dipesan kurang.
 * Keranjang tidak hilang; stok di layar langsung bertambah setelah disimpan.
 */
import { computed, ref, watch } from 'vue';
import BigButton from '@/Components/ui/BigButton.vue';
import BottomSheet from '@/Components/ui/BottomSheet.vue';
import QtyInput from '@/Components/ui/QtyInput.vue';
import { sortEggs, store } from '../store';
import { showToast } from '../lib/toast';

const open = defineModel('open', { type: Boolean, default: false });

const props = defineProps({
    productId: { type: Number, default: null },
    shortage: { type: Number, default: 0 },
});

const num = (v) => parseFloat(String(v ?? '').replace(',', '.')) || 0;
const text = (n) => String(Math.round(n * 1000) / 1000).replace('.', ',');
const product = (id) => store.boot.products.find((p) => p.id === id);

const mixedList = computed(() => (store.boot.eggs?.mixed ?? []).map(product).filter(Boolean));
const gradeList = computed(() => (store.boot.eggs?.grades ?? []).map(product).filter(Boolean));

const mixedId = ref(null);
const inputQty = ref('');
const outputs = ref({});
const errors = ref({});
const saving = ref(false);

watch(open, (v) => {
    if (!v) return;
    mixedId.value = mixedList.value[0]?.id ?? null;
    inputQty.value = props.shortage ? text(props.shortage) : '';
    outputs.value = Object.fromEntries(gradeList.value.map((g) => [g.id, g.id === props.productId && props.shortage ? text(props.shortage) : '']));
    errors.value = {};
});

const mixed = computed(() => product(mixedId.value));
const outputTotal = computed(() => Object.values(outputs.value).reduce((sum, q) => sum + num(q), 0));
const shrinkage = computed(() => Math.round((num(inputQty.value) - outputTotal.value) * 1000) / 1000);
const tooMuch = computed(() => shrinkage.value < 0);
const target = computed(() => product(props.productId));

async function save() {
    errors.value = {};
    const rows = Object.entries(outputs.value)
        .filter(([, q]) => num(q) > 0)
        .map(([id, q]) => ({ product_id: Number(id), qty: String(q) }));
    if (num(inputQty.value) <= 0) return (errors.value = { input_qty: 'Isi berapa kg telur campur yang disortir.' });
    if (!rows.length) return (errors.value = { outputs: 'Isi berat hasil sortir, minimal satu jenis.' });
    if (tooMuch.value) return;

    saving.value = true;
    try {
        const result = await sortEggs({ mixed_product_id: mixedId.value, input_qty: String(inputQty.value), outputs: rows, note: target.value ? `Dari kasir untuk ${target.value.name}` : null });
        showToast(result.message);
        open.value = false;
    } catch (e) {
        errors.value = Object.fromEntries(Object.entries(e.errors ?? {}).map(([k, v]) => [k.startsWith('outputs') ? 'outputs' : k, v[0]]));
        showToast(e.message, 'error');
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <BottomSheet v-model:open="open" title="Sortir Telur Campur">
        <div class="flex flex-col gap-5">
            <p v-if="target && shortage" class="rounded-2xl bg-accent-soft p-4 text-lg font-bold text-accent-ink">
                Stok {{ target.name }} kurang {{ text(shortage) }} kg. Sortir telur campur dulu, pesanan tetap tersimpan.
            </p>

            <div v-if="mixedList.length > 1" class="grid grid-cols-2 gap-2">
                <button
                    v-for="m in mixedList"
                    :key="m.id"
                    type="button"
                    class="pressable min-h-touch rounded-2xl border-2 text-base font-bold"
                    :class="mixedId === m.id ? 'border-primary bg-primary-soft text-primary-ink' : 'border-line text-ink'"
                    @click="mixedId = m.id"
                >
                    {{ m.name }}
                </button>
            </div>

            <div>
                <QtyInput v-model="inputQty" :label="`${mixed?.name ?? 'Telur campur'} yang disortir`" unit="kg" decimal :step="1" :error="errors.input_qty ?? errors.mixed_product_id ?? ''" />
                <p v-if="mixed?.track_stock && mixed.stock_raw !== null" class="mt-1 text-base text-ink-soft">Stok sekarang: {{ mixed.stock }} kg</p>
            </div>

            <div class="flex flex-col gap-3">
                <p class="text-lg font-bold text-ink">Hasil sortir</p>
                <QtyInput v-for="g in gradeList" :key="g.id" v-model="outputs[g.id]" :label="g.name" unit="kg" decimal :step="1" />
                <p v-if="errors.outputs" class="text-base font-bold text-danger-ink">{{ errors.outputs }}</p>
            </div>

            <div class="rounded-2xl p-4 text-lg" :class="tooMuch ? 'bg-danger-soft text-danger-ink' : 'bg-surface-2 text-ink'">
                <p class="flex justify-between"><span>Total hasil</span><span class="font-bold tabular-nums">{{ text(outputTotal) }} kg</span></p>
                <p class="flex justify-between">
                    <span>Pecah / susut</span><span class="font-bold tabular-nums">{{ tooMuch ? '-' : text(shrinkage) }} kg</span>
                </p>
                <p v-if="tooMuch" class="mt-1 font-bold">Hasil sortir lebih banyak dari telur campur. Periksa lagi beratnya.</p>
            </div>

            <BigButton size="large" block :loading="saving" :disabled="tooMuch" @click="save">Simpan Sortir</BigButton>
        </div>
    </BottomSheet>
</template>
