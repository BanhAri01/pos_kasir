<script setup>
/**
 * Sortir Telur: ambil Telur Campur (kg), bagi menjadi hasil per jenis (Besar, Kecil, Retak).
 * Berat total hasil dan telur pecah/susut langsung terlihat sebelum disimpan.
 * Semua hitungan modal/rupiah dihitung di server (bukan di Vue).
 */
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Check, ChevronRight, ClipboardList, Package, TriangleAlert } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import BigInput from '@/Components/ui/BigInput.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import QtyInput from '@/Components/ui/QtyInput.vue';
import { fmtQty, num } from '@/composables/useQty';
import { formatRupiah } from '@/composables/useRupiah';

defineOptions({ layout: AppLayout });

const props = defineProps({
    mixed: { type: Array, required: true },
    grades: { type: Array, required: true },
    prefill: { type: Object, default: () => ({ product_id: null, qty: null }) },
    recent: { type: Array, default: () => [] },
    can_see_cost: { type: Boolean, default: false },
});

const fromKasir = computed(() => {
    if (props.prefill?.product_id || props.prefill?.qty) return true;
    if (typeof window !== 'undefined') {
        return new URLSearchParams(window.location.search).get('kembali') === 'kasir';
    }
    return false;
});

const buildOutputs = () =>
    props.grades.map((g) => ({
        product_id: g.product_id,
        qty: props.prefill?.product_id === g.product_id && props.prefill?.qty ? String(props.prefill.qty) : '',
    }));

const form = useForm({
    mixed_product_id: props.mixed[0]?.product_id ?? null,
    input_qty: props.prefill?.qty ? String(props.prefill.qty) : '',
    outputs: buildOutputs(),
    note: '',
    kembali: fromKasir.value ? 'kasir' : null,
});

const selectedMixed = computed(
    () => props.mixed.find((m) => m.product_id === form.mixed_product_id) ?? props.mixed[0] ?? null,
);

const prefillGrade = computed(() =>
    props.prefill?.product_id ? props.grades.find((g) => g.product_id === props.prefill.product_id) : null,
);

const inputWeight = computed(() => num(form.input_qty));
const outputWeight = computed(() => form.outputs.reduce((sum, row) => sum + num(row.qty), 0));
const shrinkageWeight = computed(() => Math.round((inputWeight.value - outputWeight.value) * 1000) / 1000);
const tooMuch = computed(() => shrinkageWeight.value < 0);
const stockShortage = computed(() => {
    if (!selectedMixed.value) return 0;
    return Math.max(0, Math.round((inputWeight.value - num(selectedMixed.value.stock)) * 1000) / 1000);
});

const outputError = computed(() => {
    if (form.errors.outputs) return form.errors.outputs;
    const key = Object.keys(form.errors).find((k) => k.startsWith('outputs.'));
    return key ? form.errors[key] : '';
});

function save() {
    if (tooMuch.value) return;
    form.transform((data) => ({
        ...(props.mixed.length > 1 ? { mixed_product_id: data.mixed_product_id } : { mixed_product_id: data.mixed_product_id }),
        input_qty: data.input_qty,
        outputs: data.outputs.filter((o) => num(o.qty) > 0),
        note: data.note || null,
        ...(fromKasir.value ? { kembali: 'kasir' } : {}),
    })).post(route('egg.sortings.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.input_qty = '';
            form.outputs = props.grades.map((g) => ({ product_id: g.product_id, qty: '' }));
            form.note = '';
        },
    });
}
</script>

<template>
    <Head title="Sortir Telur" />
    <div class="mx-auto max-w-3xl">
        <PageHeader
            title="Sortir Telur"
            subtitle="Timbang telur campur yang diambil, lalu isi hasil per jenis telur."
            :back-href="fromKasir ? route('pos.show') : null"
        />

        <EmptyState
            v-if="!mixed.length || !grades.length"
            title="Jenis telur belum lengkap"
            message="Atur dulu barang mana yang menjadi Telur Campur dan mana yang menjadi Hasil Sortir."
        >
            <template #icon><Package :size="48" aria-hidden="true" /></template>
            <BigButton v-if="can_see_cost" :href="route('egg.types')">Atur Jenis Telur</BigButton>
        </EmptyState>

        <form v-else class="flex flex-col gap-5" @submit.prevent="save">
            <div
                v-if="prefillGrade && prefill?.qty"
                class="rounded-2xl bg-accent-soft p-4 text-lg font-bold text-accent-ink"
            >
                Pesanan membutuhkan {{ prefill.qty }} {{ prefillGrade.unit }} {{ prefillGrade.name }}. Silakan sortir dari telur campur.
            </div>

            <!-- Langkah 1: Telur Campur yang diambil -->
            <section class="card flex flex-col gap-4 p-5">
                <h2 class="text-xl font-extrabold text-ink">
                    <span class="text-ink-soft">1.</span> Telur campur yang disortir
                </h2>

                <ul v-if="mixed.length > 1" class="grid gap-3 sm:grid-cols-2">
                    <li v-for="m in mixed" :key="m.product_id">
                        <button
                            type="button"
                            class="pressable flex min-h-touch-lg w-full items-center gap-3 rounded-2xl border-2 p-4 text-left"
                            :class="form.mixed_product_id === m.product_id ? 'border-primary bg-primary-soft' : 'border-line'"
                            :aria-pressed="form.mixed_product_id === m.product_id"
                            @click="form.mixed_product_id = m.product_id"
                        >
                            <span class="min-w-0 flex-1">
                                <span class="block text-lg font-extrabold text-ink">{{ m.name }}</span>
                                <span class="block text-base text-ink-soft">Stok sekarang: {{ m.stock }} {{ m.unit }}</span>
                            </span>
                            <Check
                                v-if="form.mixed_product_id === m.product_id"
                                :size="26"
                                class="shrink-0 text-primary-ink"
                                aria-hidden="true"
                            />
                        </button>
                    </li>
                </ul>

                <div v-else-if="selectedMixed" class="flex flex-wrap items-center justify-between gap-2 rounded-2xl bg-surface-2 px-4 py-3">
                    <div>
                        <p class="text-lg font-extrabold text-ink">{{ selectedMixed.name }}</p>
                        <p class="text-base text-ink-soft">Harga jual {{ formatRupiah(selectedMixed.sell_price) }}/{{ selectedMixed.unit }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-base text-ink-soft">Stok sekarang</p>
                        <p class="font-display text-2xl font-extrabold text-ink tabular-nums">
                            {{ selectedMixed.stock }} {{ selectedMixed.unit }}
                        </p>
                    </div>
                </div>

                <QtyInput
                    v-model="form.input_qty"
                    :label="`Berat ${selectedMixed?.name ?? 'Telur Campur'} yang disortir`"
                    :unit="selectedMixed?.unit ?? 'Kg'"
                    decimal
                    :step="1"
                    :error="form.errors.input_qty ?? form.errors.mixed_product_id ?? ''"
                />

                <p v-if="stockShortage > 0" class="flex items-center gap-2 text-base font-bold text-warn-ink">
                    <TriangleAlert :size="20" aria-hidden="true" />
                    Stok {{ selectedMixed?.name }} tercatat kurang {{ fmtQty(stockShortage) }} {{ selectedMixed?.unit }}.
                </p>
            </section>

            <!-- Langkah 2: Hasil sortir per jenis -->
            <section class="card flex flex-col gap-4 p-5">
                <div>
                    <h2 class="text-xl font-extrabold text-ink">
                        <span class="text-ink-soft">2.</span> Hasil sortir per jenis
                    </h2>
                    <p class="mt-1 text-base text-ink-soft">
                        Isi berat untuk jenis telur yang dihasilkan. Kosongkan jenis yang tidak ada.
                    </p>
                </div>

                <ul class="divide-y divide-line">
                    <li
                        v-for="(g, i) in grades"
                        :key="g.product_id"
                        class="flex flex-col gap-2 py-4 first:pt-0 last:pb-0"
                    >
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <span class="text-xl font-extrabold text-ink">{{ g.name }}</span>
                            <span class="text-base text-ink-soft">
                                Stok sekarang: <strong class="text-ink tabular-nums">{{ g.stock }} {{ g.unit }}</strong>
                                · jual {{ formatRupiah(g.sell_price) }}/{{ g.unit }}
                            </span>
                        </div>
                        <QtyInput
                            v-if="form.outputs[i]"
                            v-model="form.outputs[i].qty"
                            :label="`Berat ${g.name}`"
                            :unit="g.unit"
                            decimal
                            compact
                            :step="1"
                            :error="form.errors[`outputs.${i}.qty`] ?? form.errors[`outputs.${i}.product_id`] ?? ''"
                        />
                    </li>
                </ul>

                <p v-if="outputError" role="alert" class="rounded-2xl bg-danger-soft p-4 text-lg font-bold text-danger-ink">
                    {{ outputError }}
                </p>
            </section>

            <!-- Langkah 3: Ringkasan berat & susut sebelum simpan -->
            <section
                class="card flex flex-col gap-3 p-5"
                :class="tooMuch ? 'border-2 border-danger bg-danger-soft' : ''"
            >
                <h2 class="text-xl font-extrabold text-ink">
                    <span class="text-ink-soft">3.</span> Cek berat &amp; pecah / susut
                </h2>

                <dl class="flex flex-col gap-2 text-lg">
                    <div class="flex justify-between gap-3">
                        <dt class="text-ink-soft">Telur campur diambil</dt>
                        <dd class="font-display font-bold text-ink tabular-nums">
                            {{ fmtQty(inputWeight) }} {{ selectedMixed?.unit ?? 'Kg' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-ink-soft">Total hasil sortir</dt>
                        <dd class="font-display font-bold text-ink tabular-nums">
                            {{ fmtQty(outputWeight) }} {{ selectedMixed?.unit ?? 'Kg' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3 border-t border-line pt-2 text-xl font-extrabold">
                        <dt :class="tooMuch ? 'text-danger-ink' : 'text-ink'">Pecah / susut</dt>
                        <dd
                            class="font-display tabular-nums"
                            :class="tooMuch ? 'text-danger-ink' : shrinkageWeight > 0 ? 'text-warn-ink' : 'text-ink'"
                        >
                            {{ tooMuch ? `Kelebihan ${fmtQty(Math.abs(shrinkageWeight))}` : fmtQty(shrinkageWeight) }}
                            {{ selectedMixed?.unit ?? 'Kg' }}
                        </dd>
                    </div>
                </dl>

                <p v-if="tooMuch" role="alert" class="flex items-center gap-2 text-lg font-bold text-danger-ink">
                    <TriangleAlert :size="22" class="shrink-0" aria-hidden="true" />
                    Hasil sortir lebih berat dari telur campur yang diambil. Periksa lagi timbangannya.
                </p>

                <BigInput
                    v-model="form.note"
                    label="Catatan"
                    optional
                    placeholder="Contoh: untuk pesanan Bu Ketut"
                    :error="form.errors.note"
                />
            </section>

            <BigButton
                type="submit"
                size="large"
                block
                :loading="form.processing"
                :disabled="inputWeight <= 0 || outputWeight <= 0 || tooMuch"
            >
                Simpan sortir telur
            </BigButton>
        </form>

        <!-- Riwayat 10 sortir terakhir -->
        <section v-if="recent.length" class="mt-8">
            <h2 class="mb-3 flex items-center gap-2 text-xl font-extrabold text-ink">
                <ClipboardList :size="22" aria-hidden="true" /> Riwayat sortir terakhir
            </h2>
            <ul class="card divide-y divide-line overflow-hidden">
                <li v-for="s in recent" :key="s.uuid">
                    <Link
                        :href="route('egg.sortings.show', s.uuid)"
                        class="flex min-h-touch-lg items-center justify-between gap-3 px-4 py-3 hover:bg-surface-2"
                    >
                        <span class="min-w-0 flex-1">
                            <span class="block text-lg font-extrabold text-ink">
                                {{ s.input_qty }} Kg {{ s.mixed || 'Telur Campur' }} → hasil {{ s.output_qty }} Kg
                            </span>
                            <span class="block text-base text-ink-soft">
                                {{ s.number }} · {{ s.date }}
                                <template v-if="s.shrinkage_qty && s.shrinkage_qty !== '0'">
                                    · pecah/susut {{ s.shrinkage_qty }} Kg
                                </template>
                                <template v-if="s.note"> · {{ s.note }}</template>
                            </span>
                        </span>
                        <ChevronRight :size="24" class="shrink-0 text-ink-soft" aria-hidden="true" />
                    </Link>
                </li>
            </ul>
        </section>
    </div>
</template>
