<script setup>
/**
 * Kemas Ulang, langkah demi langkah:
 *   1. Pilih barang karungan yang mau dibuat (contoh: Jagung Karung 25 kg)
 *   2. Isi berapa karung
 *   3. Cek bahan yang terpakai (curah + karung kosong + benang), lalu Simpan
 *
 * Mode Bongkar: kebalikannya, karung dibuka jadi barang curah lagi.
 */
import { computed, ref, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Check, Package, Settings, TriangleAlert } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import QtyInput from '@/Components/ui/QtyInput.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import ToggleSwitch from '@/Components/ui/ToggleSwitch.vue';
import { formatRupiah } from '@/composables/useRupiah';
import { fmtQty, num } from '@/composables/useQty';

defineOptions({ layout: AppLayout });

const props = defineProps({ formulas: { type: Array, required: true } });

const mode = ref('pack');
const selectedId = ref(props.formulas.length === 1 ? props.formulas[0].id : null);
const selected = computed(() => props.formulas.find((f) => f.id === selectedId.value) ?? null);
const editActual = ref(false);

const form = useForm({ formula_id: null, batches: '1', inputs: [], output_qty: '', note: '' });
const unpack = useForm({ formula_id: null, qty: '1', received_qty: '', reuse_packaging: true, note: '' });

const count = computed(() => num(mode.value === 'pack' ? form.batches : unpack.qty));
/** Resep biasanya untuk 1 karung; kalau resepnya untuk beberapa karung, dibagi dulu. */
const perPack = (item) => num(item.qty) / (num(selected.value?.output_qty) || 1);

const needs = computed(() =>
    (selected.value?.items ?? []).map((item) => {
        const planned = perPack(item) * count.value;
        const override = form.inputs.find((i) => i.product_id === item.product_id);
        const actual = editActual.value && override ? num(override.actual_qty) : planned;
        const stock = num(item.stock);
        return { ...item, planned, actual, short: Math.max(0, actual - stock) };
    }),
);
const shortages = computed(() => needs.value.filter((n) => n.short > 0));
const estimatedCost = computed(() => (count.value > 0 ? Math.round(needs.value.reduce((sum, n) => sum + n.actual * n.cost, 0) / count.value) : 0));

const bulkItem = computed(() => selected.value?.items.find((i) => !i.is_packaging) ?? null);
const plannedBulk = computed(() => (bulkItem.value ? perPack(bulkItem.value) * count.value : 0));
const hasSacks = computed(() => (selected.value?.items ?? []).some((i) => i.is_packaging && Number.isInteger(num(i.qty))));

watch([selected, editActual, () => form.batches], () => {
    form.inputs = needs.value.map((n) => ({ product_id: n.product_id, actual_qty: fmtQty(n.planned) }));
});

function choose(formula) {
    selectedId.value = formula.id;
    editActual.value = false;
}

function save() {
    if (mode.value === 'pack') {
        form.transform((data) => ({
            formula_id: selected.value.id,
            batches: data.batches,
            inputs: editActual.value ? data.inputs : [],
            note: data.note,
        })).post(route('warehouse.repack.store'));
    } else {
        unpack.transform((data) => ({ ...data, formula_id: selected.value.id })).post(route('warehouse.unpack.store'));
    }
}
</script>

<template>
    <Head title="Kemas Ulang" />
    <div class="mx-auto max-w-3xl">
        <PageHeader title="Kemas Ulang" subtitle="Barang curah dikemas jadi karungan, atau sebaliknya." :back-href="route('warehouse.dashboard')">
            <template #action>
                <BigButton :href="route('warehouse.formulas')" variant="secondary"><Settings :size="22" aria-hidden="true" /> Atur Kemasan</BigButton>
            </template>
        </PageHeader>

        <EmptyState v-if="!formulas.length" title="Belum ada kemasan" message="Atur dulu isi 1 karung. Contoh: 25 kg Jagung Pipil + 1 Karung Kosong = 1 Jagung Karung 25 kg.">
            <template #icon><Package :size="48" aria-hidden="true" /></template>
            <BigButton :href="route('warehouse.formulas.create', { jenis: 'repack' })">Atur Kemasan</BigButton>
        </EmptyState>

        <form v-else class="flex flex-col gap-5" @submit.prevent="save">
            <SegmentedControl v-model="mode" label="Mau apa?" :options="[{ value: 'pack', label: 'Kemas (curah → karung)' }, { value: 'unpack', label: 'Bongkar (karung → curah)' }]" />

            <!-- Langkah 1 -->
            <section class="card p-5">
                <h2 class="mb-3 text-xl font-extrabold text-ink"><span class="text-ink-soft">1.</span> {{ mode === 'pack' ? 'Mau buat apa?' : 'Karung apa yang dibongkar?' }}</h2>
                <ul class="grid gap-3 sm:grid-cols-2">
                    <li v-for="f in formulas" :key="f.id">
                        <button
                            type="button"
                            class="pressable flex min-h-touch-lg w-full items-center gap-3 rounded-2xl border-2 p-4 text-left"
                            :class="selectedId === f.id ? 'border-primary bg-primary-soft' : 'border-line'"
                            :aria-pressed="selectedId === f.id"
                            @click="choose(f)"
                        >
                            <span class="min-w-0 flex-1">
                                <span class="block text-lg font-extrabold text-ink">{{ f.output.name }}</span>
                                <span class="block text-base text-ink-soft">Stok sekarang {{ f.output.stock }} {{ f.output.unit }}</span>
                            </span>
                            <Check v-if="selectedId === f.id" :size="26" class="shrink-0 text-primary-ink" aria-hidden="true" />
                        </button>
                    </li>
                </ul>
            </section>

            <template v-if="selected">
                <!-- Langkah 2 -->
                <section class="card flex flex-col gap-4 p-5">
                    <h2 class="text-xl font-extrabold text-ink"><span class="text-ink-soft">2.</span> Berapa {{ selected.output.unit.toLowerCase() || 'karung' }}?</h2>
                    <QtyInput v-if="mode === 'pack'" v-model="form.batches" :label="`Jumlah ${selected.output.unit}`" :unit="selected.output.unit" :error="form.errors.batches" />
                    <template v-else>
                        <QtyInput v-model="unpack.qty" :label="`Jumlah ${selected.output.unit} dibongkar`" :unit="selected.output.unit" :error="unpack.errors.qty" />
                        <QtyInput
                            v-if="bulkItem"
                            v-model="unpack.received_qty"
                            :label="`Berat ${bulkItem.name} setelah ditimbang`"
                            :unit="bulkItem.unit"
                            decimal
                            :error="unpack.errors.received_qty"
                        />
                        <p v-if="bulkItem" class="-mt-2 text-base text-ink-soft">Kosongkan kalau tidak ditimbang: dianggap {{ fmtQty(plannedBulk) }} {{ bulkItem.unit }}.</p>
                        <div v-if="hasSacks" class="flex flex-wrap items-center gap-x-4 gap-y-2">
                            <span class="min-w-48 flex-1 text-lg text-ink">Karung masih bagus, simpan lagi ke stok bahan kemas</span>
                            <ToggleSwitch v-model="unpack.reuse_packaging" label="Karung disimpan lagi" class="ml-auto" />
                        </div>
                    </template>
                </section>

                <!-- Langkah 3: kemas -->
                <section v-if="mode === 'pack'" class="card flex flex-col gap-4 p-5">
                    <h2 class="text-xl font-extrabold text-ink"><span class="text-ink-soft">3.</span> Bahan yang terpakai</h2>
                    <ul class="divide-y divide-line">
                        <li v-for="(n, i) in needs" :key="n.product_id" class="flex flex-col gap-2 py-3">
                            <div class="flex items-start justify-between gap-3">
                                <span class="min-w-0">
                                    <span class="block text-lg font-bold text-ink">{{ n.name }}</span>
                                    <span class="block text-base text-ink-soft">Stok {{ n.stock }} {{ n.unit }}</span>
                                </span>
                                <span v-if="!editActual" class="shrink-0 font-display text-xl font-extrabold text-ink tabular-nums">{{ fmtQty(n.planned) }} {{ n.unit }}</span>
                            </div>
                            <QtyInput v-if="editActual && form.inputs[i]" v-model="form.inputs[i].actual_qty" :label="`${n.name} terpakai`" :unit="n.unit" decimal compact />
                            <p v-if="n.short > 0" class="flex items-center gap-2 text-base font-bold text-danger-ink">
                                <TriangleAlert :size="18" aria-hidden="true" /> Stok kurang {{ fmtQty(n.short) }} {{ n.unit }}
                            </p>
                        </li>
                    </ul>
                    <button type="button" class="pressable self-start rounded-xl px-2 py-2 text-base font-bold text-primary-ink" @click="editActual = !editActual">
                        {{ editActual ? 'Pakai jumlah sesuai resep' : 'Jumlah terpakai berbeda? Ubah di sini' }}
                    </button>
                    <p class="rounded-2xl bg-surface-2 px-4 py-3 text-lg text-ink">
                        Modal per {{ selected.output.unit.toLowerCase() || 'karung' }} kira-kira <strong>{{ formatRupiah(estimatedCost) }}</strong>
                    </p>
                    <p v-if="shortages.length" class="rounded-2xl bg-warn-soft px-4 py-3 text-base font-bold text-warn-ink">
                        Ada bahan yang stoknya kurang. Tetap bisa disimpan, tapi stoknya akan minus. Cek lagi stok atau jumlah karungnya.
                    </p>
                </section>

                <BigButton type="submit" block size="large" :loading="form.processing || unpack.processing" :disabled="count <= 0">
                    {{ mode === 'pack' ? `Simpan: ${fmtQty(count)} ${selected.output.unit} ${selected.output.name}` : `Bongkar ${fmtQty(count)} ${selected.output.unit}` }}
                </BigButton>
            </template>
        </form>

        <p class="mt-6 text-center"><Link :href="route('warehouse.orders')" class="text-lg font-bold text-primary-ink">Lihat riwayat kemas & olah</Link></p>
    </div>
</template>
