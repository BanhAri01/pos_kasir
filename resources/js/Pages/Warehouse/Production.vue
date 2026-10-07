<script setup>
/**
 * Olah / Giling, langkah demi langkah:
 *   1. Pilih yang mau dibuat (resep)
 *   2. Berapa kali olah
 *   3. Bahan yang benar-benar terpakai (awalnya sesuai resep)
 *   4. Hasil yang didapat -> susut terhitung otomatis
 *   5. Biaya olah (upah, listrik, mesin) -> modal per kg hasil
 */
import { computed, ref, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Check, Factory, Settings, TriangleAlert } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import BigInput from '@/Components/ui/BigInput.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import QtyInput from '@/Components/ui/QtyInput.vue';
import { formatRupiah } from '@/composables/useRupiah';
import { fmtQty, num } from '@/composables/useQty';
import { useAuth } from '@/composables/useAuth';

defineOptions({ layout: AppLayout });

const props = defineProps({ formulas: { type: Array, required: true } });

const { hasModule } = useAuth();
const selectedId = ref(props.formulas.length === 1 ? props.formulas[0].id : null);
const selected = computed(() => props.formulas.find((f) => f.id === selectedId.value) ?? null);

const form = useForm({
    formula_id: null,
    batches: '1',
    inputs: [],
    output_qty: '',
    labor_cost: null,
    utility_cost: null,
    machine_cost: null,
    other_cost: null,
    batch_no: '',
    expires_at: '',
    note: '',
});

const batches = computed(() => num(form.batches));
const plannedOutput = computed(() => num(selected.value?.output_qty) * batches.value);

// Isi ulang bahan & hasil sesuai resep setiap kali resep atau jumlah olah berubah.
watch([selected, () => form.batches], () => {
    if (!selected.value) return;
    form.inputs = selected.value.items.map((item) => ({ product_id: item.product_id, actual_qty: fmtQty(num(item.qty) * batches.value) }));
    form.output_qty = fmtQty(plannedOutput.value);
}, { immediate: true });

const rows = computed(() =>
    (selected.value?.items ?? []).map((item, i) => {
        const actual = num(form.inputs[i]?.actual_qty);
        return { ...item, planned: num(item.qty) * batches.value, actual, short: Math.max(0, actual - num(item.stock)) };
    }),
);

/** Susut = bahan utama (satuan sama dengan bahan pertama) - hasil. Hanya dihitung kalau satuannya sama dengan hasil. */
const mainUnit = computed(() => rows.value.find((r) => !r.is_packaging)?.unit);
const inputWeight = computed(() => rows.value.filter((r) => !r.is_packaging && r.unit === mainUnit.value).reduce((s, r) => s + r.actual, 0));
const output = computed(() => num(form.output_qty));
const shrinkage = computed(() => (selected.value && selected.value.output.unit === mainUnit.value ? inputWeight.value - output.value : null));
const shrinkagePercent = computed(() => (shrinkage.value !== null && inputWeight.value > 0 ? Math.round((shrinkage.value / inputWeight.value) * 1000) / 10 : null));

const materialsCost = computed(() => rows.value.reduce((s, r) => s + Math.round(r.actual * r.cost), 0));
const processingCost = computed(() => ['labor_cost', 'utility_cost', 'machine_cost', 'other_cost'].reduce((s, k) => s + (Number(form[k]) || 0), 0));
const unitCost = computed(() => (output.value > 0 ? Math.round((materialsCost.value + processingCost.value) / output.value) : 0));

function save() {
    form.transform((data) => ({ ...data, formula_id: selected.value.id })).post(route('warehouse.production.store'));
}
</script>

<template>
    <Head title="Olah / Giling" />
    <div class="mx-auto max-w-3xl">
        <PageHeader title="Olah / Giling" subtitle="Catat bahan yang dipakai dan hasil yang didapat." :back-href="route('warehouse.dashboard')">
            <template #action>
                <BigButton :href="route('warehouse.formulas')" variant="secondary"><Settings :size="22" aria-hidden="true" /> Atur Resep</BigButton>
            </template>
        </PageHeader>

        <EmptyState v-if="!formulas.length" title="Belum ada resep olah" message="Buat resep dulu. Contoh: 50 kg jagung + 35 kg konsentrat + 15 kg dedak = 100 kg pakan racik.">
            <template #icon><Factory :size="48" aria-hidden="true" /></template>
            <BigButton :href="route('warehouse.formulas.create', { jenis: 'production' })">Buat Resep</BigButton>
        </EmptyState>

        <form v-else class="flex flex-col gap-5" @submit.prevent="save">
            <!-- 1 -->
            <section class="card p-5">
                <h2 class="mb-3 text-xl font-extrabold text-ink"><span class="text-ink-soft">1.</span> Mau buat apa?</h2>
                <ul class="grid gap-3 sm:grid-cols-2">
                    <li v-for="f in formulas" :key="f.id">
                        <button
                            type="button"
                            class="pressable flex min-h-touch-lg w-full items-center gap-3 rounded-2xl border-2 p-4 text-left"
                            :class="selectedId === f.id ? 'border-primary bg-primary-soft' : 'border-line'"
                            :aria-pressed="selectedId === f.id"
                            @click="selectedId = f.id"
                        >
                            <span class="min-w-0 flex-1">
                                <span class="block text-lg font-extrabold text-ink">{{ f.name }}</span>
                                <span class="block text-base text-ink-soft">Hasil {{ f.output_qty }} {{ f.output.unit }} {{ f.output.name }} per olah</span>
                            </span>
                            <Check v-if="selectedId === f.id" :size="26" class="shrink-0 text-primary-ink" aria-hidden="true" />
                        </button>
                    </li>
                </ul>
            </section>

            <template v-if="selected">
                <!-- 2 -->
                <section class="card flex flex-col gap-3 p-5">
                    <h2 class="text-xl font-extrabold text-ink"><span class="text-ink-soft">2.</span> Berapa kali olah?</h2>
                    <QtyInput v-model="form.batches" label="Jumlah olah" unit="kali" decimal :error="form.errors.batches" />
                    <p class="text-lg text-ink-soft">Rencana hasil: <strong class="text-ink">{{ fmtQty(plannedOutput) }} {{ selected.output.unit }}</strong></p>
                </section>

                <!-- 3 -->
                <section class="card flex flex-col gap-3 p-5">
                    <h2 class="text-xl font-extrabold text-ink"><span class="text-ink-soft">3.</span> Bahan yang terpakai</h2>
                    <p class="text-base text-ink-soft">Sudah diisi sesuai resep. Ubah kalau yang terpakai berbeda.</p>
                    <ul class="divide-y divide-line">
                        <li v-for="(r, i) in rows" :key="r.product_id" class="flex flex-col gap-2 py-3">
                            <div class="flex items-start justify-between gap-3">
                                <span class="min-w-0">
                                    <span class="block text-lg font-bold text-ink">{{ r.name }}</span>
                                    <span class="block text-base text-ink-soft">Rencana {{ fmtQty(r.planned) }} {{ r.unit }} · stok {{ r.stock }} {{ r.unit }}</span>
                                </span>
                            </div>
                            <QtyInput v-if="form.inputs[i]" v-model="form.inputs[i].actual_qty" :label="`${r.name} terpakai`" :unit="r.unit" decimal compact :error="form.errors[`inputs.${i}.actual_qty`]" />
                            <p v-if="r.short > 0" class="flex items-center gap-2 text-base font-bold text-danger-ink">
                                <TriangleAlert :size="18" aria-hidden="true" /> Stok kurang {{ fmtQty(r.short) }} {{ r.unit }}
                            </p>
                        </li>
                    </ul>
                </section>

                <!-- 4 -->
                <section class="card flex flex-col gap-3 p-5">
                    <h2 class="text-xl font-extrabold text-ink"><span class="text-ink-soft">4.</span> Hasil yang didapat</h2>
                    <QtyInput v-model="form.output_qty" :label="`${selected.output.name} (setelah ditimbang)`" :unit="selected.output.unit" :decimal="selected.output.decimal" :error="form.errors.output_qty" />
                    <p v-if="shrinkage !== null" class="rounded-2xl px-4 py-3 text-lg font-bold" :class="shrinkage > 0 ? 'bg-warn-soft text-warn-ink' : 'bg-surface-2 text-ink'">
                        Susut olah: {{ fmtQty(shrinkage) }} {{ mainUnit }}<template v-if="shrinkagePercent !== null"> ({{ String(shrinkagePercent).replace('.', ',') }}%)</template>
                    </p>
                    <div v-if="hasModule('batch_lot') && selected.output.track_batch" class="grid gap-3 sm:grid-cols-2">
                        <BigInput v-model="form.batch_no" label="No batch hasil" optional placeholder="Contoh: PR-0610" :error="form.errors.batch_no" />
                        <BigInput v-model="form.expires_at" label="Kedaluwarsa" type="date" optional :error="form.errors.expires_at" />
                    </div>
                </section>

                <!-- 5 -->
                <section class="card flex flex-col gap-4 p-5">
                    <div>
                        <h2 class="text-xl font-extrabold text-ink"><span class="text-ink-soft">5.</span> Biaya olah</h2>
                        <p class="text-base text-ink-soft">
                            Ikut dihitung ke modal hasil. Boleh dikosongkan.
                            <template v-if="selected.cost_per_batch"> Perkiraan: {{ formatRupiah(selected.cost_per_batch) }} per olah.</template>
                        </p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <MoneyInput v-model="form.labor_cost" label="Upah" optional :error="form.errors.labor_cost" />
                        <MoneyInput v-model="form.utility_cost" label="Listrik" optional :error="form.errors.utility_cost" />
                        <MoneyInput v-model="form.machine_cost" label="Mesin / solar" optional :error="form.errors.machine_cost" />
                        <MoneyInput v-model="form.other_cost" label="Biaya lain" optional :error="form.errors.other_cost" />
                    </div>
                    <p class="rounded-2xl bg-surface-2 px-4 py-3 text-lg text-ink">
                        Modal hasil: <strong>{{ formatRupiah(unitCost) }} per {{ selected.output.unit }}</strong>
                        <span class="block text-base text-ink-soft">Bahan {{ formatRupiah(materialsCost) }} + biaya olah {{ formatRupiah(processingCost) }}</span>
                    </p>
                </section>

                <BigButton type="submit" block size="large" :loading="form.processing" :disabled="output <= 0">Simpan Hasil Olah</BigButton>
            </template>
        </form>

        <p class="mt-6 text-center"><Link :href="route('warehouse.orders')" class="text-lg font-bold text-primary-ink">Lihat riwayat olah & kemas</Link></p>
    </div>
</template>
