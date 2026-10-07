<script setup>
/**
 * Catat belanja / terima barang: pilih pemasok, tambah barang (jumlah + harga beli per satuan), lalu berapa yang dibayar.
 * Kalau dibayar kurang dari total, sisanya jadi utang ke pemasok.
 *
 * Timbangan (weighed_receiving): untuk barang per kg, isi jumlah karung + berat per karung menurut nota,
 * lalu berat timbangan asli. Pemasok dibayar sesuai nota, stok bertambah sesuai timbangan.
 * Modal lengkap (landed_cost): ongkos angkut & bongkar muat ikut dihitung ke modal per kg.
 */
import { computed, ref, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Scale, Search, X } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import BigInput from '@/Components/ui/BigInput.vue';
import MoneyDisplay from '@/Components/ui/MoneyDisplay.vue';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import QtyInput from '@/Components/ui/QtyInput.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import { formatRupiah } from '@/composables/useRupiah';
import { useAuth } from '@/composables/useAuth';
import { fmtQty as fmt, num } from '@/composables/useQty';

defineOptions({ layout: AppLayout });

const props = defineProps({
    suppliers: { type: Array, required: true },
    products: { type: Array, required: true },
    paymentMethods: { type: Array, required: true },
    today: { type: String, required: true },
});

const { hasModule } = useAuth();
const canWeigh = hasModule('weighed_receiving');
const withLandedCost = hasModule('landed_cost');
const withBatch = hasModule('batch_lot');
const title = canWeigh ? 'Terima Barang' : 'Catat Belanja';

const form = useForm({
    supplier_id: null,
    supplier_invoice_no: '',
    purchased_on: props.today,
    due_date: '',
    note: '',
    paid_amount: 0,
    payment_method_id: props.paymentMethods[0]?.id ?? null,
    freight_cost: null,
    unloading_cost: null,
    other_cost: null,
    items: [],
});

const query = ref('');
const payMode = ref('full');
const productById = (id) => props.products.find((p) => p.id === id);
const supplier = computed(() => props.suppliers.find((s) => s.id === form.supplier_id) ?? null);
const matches = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return [];
    const chosen = form.items.map((i) => i.product_id);
    return props.products.filter((p) => p.name.toLowerCase().includes(q) && !chosen.includes(p.id)).slice(0, 8);
});


/** Jumlah yang ditagih pemasok (nota). */
const billedQty = (item) => (item.weigh ? num(item.pack_count) * num(item.pack_weight) : num(item.qty));
/** Jumlah yang benar-benar masuk stok. */
const receivedQty = (item) => (item.weigh && String(item.received_qty).trim() !== '' ? num(item.received_qty) : billedQty(item));
const weightDiff = (item) => Math.round((receivedQty(item) - billedQty(item)) * 1000) / 1000;

const lineTotal = (item) => Math.round(billedQty(item) * (Number(item.unit_cost) || 0));
const total = computed(() => form.items.reduce((sum, i) => sum + lineTotal(i), 0));
const extraTotal = computed(() => (Number(form.freight_cost) || 0) + (Number(form.unloading_cost) || 0) + (Number(form.other_cost) || 0));
const debt = computed(() => Math.max(0, total.value - (payMode.value === 'full' ? total.value : Number(form.paid_amount) || 0)));

/** Perkiraan modal per satuan dasar, sama seperti hitungan server (biaya dibagi sebanding nilai belanja). */
function landedCost(item) {
    const received = receivedQty(item);
    if (received <= 0) return null;
    const share = total.value > 0 ? (extraTotal.value * lineTotal(item)) / total.value : 0;
    return Math.round((lineTotal(item) + share) / received);
}

function defaultPackWeight(product) {
    return supplier.value?.pack_weight ?? product.pack_size ?? '';
}

function add(product) {
    const weigh = canWeigh && product.unit_allows_decimal && !product.units.length;
    form.items.push({
        product_id: product.id,
        unit_id: null,
        qty: '1',
        unit_cost: product.cost || 0,
        weigh,
        pack_count: '',
        pack_weight: weigh ? defaultPackWeight(product) : '',
        pack_weight_touched: false,
        received_qty: '',
        batch_no: '',
        expires_at: '',
        moisture: '',
    });
    query.value = '';
}

// Ganti pemasok: berat per karung bawaan ikut berganti (kecuali yang sudah diubah sendiri).
watch(supplier, () => {
    form.items.forEach((item) => {
        if (item.weigh && !item.pack_weight_touched) item.pack_weight = defaultPackWeight(productById(item.product_id));
    });
});

function toggleWeigh(item) {
    item.weigh = !item.weigh;
    if (item.weigh) {
        item.unit_id = null;
        if (!item.pack_weight) item.pack_weight = defaultPackWeight(productById(item.product_id));
    }
}

function unitsFor(item) {
    return productById(item.product_id)?.units ?? [];
}

function save() {
    form.transform((data) => ({
        ...data,
        paid_amount: payMode.value === 'full' ? total.value : payMode.value === 'none' ? 0 : Number(data.paid_amount) || 0,
        items: data.items.map((item) => ({
            ...(item.weigh
                ? { product_id: item.product_id, unit_id: null, qty: fmt(billedQty(item)), unit_cost: item.unit_cost, pack_count: item.pack_count, pack_weight: item.pack_weight, received_qty: item.received_qty }
                : { product_id: item.product_id, unit_id: item.unit_id, qty: item.qty, unit_cost: item.unit_cost }),
            ...(withBatch && productById(item.product_id)?.track_batch ? { batch_no: item.batch_no, expires_at: item.expires_at, moisture: item.moisture } : {}),
        })),
    })).post(route('purchases.store'));
}
</script>

<template>
    <Head :title="title" />
    <div class="mx-auto max-w-3xl">
        <PageHeader :title="title" :back-href="route('purchases.index')" />

        <form class="flex flex-col gap-5" @submit.prevent="save">
            <div class="card grid gap-4 p-5 sm:grid-cols-2">
                <label class="block sm:col-span-2">
                    <span class="mb-2 block text-lg font-bold text-ink">Pemasok</span>
                    <select v-model="form.supplier_id" class="min-h-touch w-full rounded-2xl border-2 border-line bg-surface px-4 text-lg text-ink">
                        <option :value="null">Tanpa pemasok / beli di pasar</option>
                        <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </label>
                <BigInput v-model="form.purchased_on" :label="canWeigh ? 'Tanggal terima' : 'Tanggal belanja'" type="date" :error="form.errors.purchased_on" />
                <BigInput v-model="form.supplier_invoice_no" label="No nota dari pemasok" optional :error="form.errors.supplier_invoice_no" />
            </div>

            <div class="card p-5">
                <h2 class="mb-3 text-xl font-extrabold text-ink">{{ canWeigh ? 'Barang yang diterima' : 'Barang yang dibeli' }}</h2>
                <div class="relative mb-4">
                    <label class="flex min-h-touch items-center gap-3 rounded-2xl border-2 border-line bg-surface px-4 focus-within:border-focus">
                        <Search :size="22" class="shrink-0 text-ink-soft" aria-hidden="true" />
                        <span class="sr-only">Cari barang</span>
                        <input v-model="query" type="search" placeholder="Ketik nama barang" class="min-w-0 flex-1 bg-transparent text-lg text-ink focus:outline-none" />
                    </label>
                    <ul v-if="matches.length" class="absolute inset-x-0 top-full z-10 mt-1 overflow-hidden rounded-2xl border-2 border-line bg-surface shadow-float">
                        <li v-for="p in matches" :key="p.id">
                            <button type="button" class="flex min-h-touch w-full items-center justify-between gap-3 px-4 text-left text-lg hover:bg-surface-2" @click="add(p)">
                                <span class="min-w-0 truncate font-bold text-ink">{{ p.name }}</span>
                                <span class="shrink-0 text-base text-ink-soft">modal {{ formatRupiah(p.cost) }}/{{ p.unit }}</span>
                            </button>
                        </li>
                    </ul>
                </div>
                <p v-if="form.errors.items" class="mb-3 text-base font-bold text-danger-ink">{{ form.errors.items }}</p>
                <p v-if="!form.items.length" class="text-lg text-ink-soft">Belum ada barang. Cari lalu ketuk nama barangnya.</p>

                <ul class="flex flex-col divide-y divide-line">
                    <li v-for="(item, i) in form.items" :key="item.product_id" class="flex flex-col gap-3 py-4">
                        <div class="flex items-start justify-between gap-2">
                            <p class="min-w-0 text-lg font-extrabold text-ink">{{ productById(item.product_id)?.name }}</p>
                            <button type="button" class="pressable flex size-touch shrink-0 items-center justify-center rounded-xl text-danger-ink" :aria-label="`Hapus ${productById(item.product_id)?.name}`" @click="form.items.splice(i, 1)"><X :size="24" /></button>
                        </div>

                        <!-- Pakai timbangan -->
                        <template v-if="item.weigh">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <QtyInput v-model="item.pack_count" label="Jumlah karung" :error="form.errors[`items.${i}.pack_count`]" />
                                <QtyInput
                                    v-model="item.pack_weight"
                                    label="Berat per karung (nota)"
                                    :unit="productById(item.product_id)?.unit"
                                    decimal
                                    :error="form.errors[`items.${i}.pack_weight`]"
                                    @update:model-value="item.pack_weight_touched = true"
                                />
                            </div>
                            <p class="rounded-2xl bg-surface-2 px-4 py-3 text-lg text-ink">
                                Berat menurut nota: <strong>{{ fmt(billedQty(item)) }} {{ productById(item.product_id)?.unit }}</strong>
                            </p>
                            <QtyInput
                                v-model="item.received_qty"
                                label="Berat timbangan asli"
                                :unit="productById(item.product_id)?.unit"
                                decimal
                                :step="1"
                                :error="form.errors[`items.${i}.received_qty`]"
                            />
                            <p v-if="String(item.received_qty).trim() !== '' && weightDiff(item) !== 0" class="rounded-2xl px-4 py-3 text-lg font-bold" :class="weightDiff(item) < 0 ? 'bg-danger-soft text-danger-ink' : 'bg-primary-soft text-primary-ink'">
                                {{ weightDiff(item) < 0 ? 'Kurang' : 'Lebih' }} {{ fmt(Math.abs(weightDiff(item))) }} {{ productById(item.product_id)?.unit }} dari nota.
                                <span class="block text-base font-semibold">Tercatat sebagai selisih timbang pemasok. Stok bertambah sesuai timbangan.</span>
                            </p>
                            <p v-else-if="String(item.received_qty).trim() === ''" class="text-base text-ink-soft">Kosongkan kalau tidak ditimbang. Stok akan bertambah sesuai berat nota.</p>
                            <MoneyInput v-model="item.unit_cost" :label="`Harga beli per ${productById(item.product_id)?.unit}`" :error="form.errors[`items.${i}.unit_cost`]" />
                        </template>

                        <div v-else class="grid gap-3 sm:grid-cols-3">
                            <label v-if="unitsFor(item).length" class="block">
                                <span class="mb-2 block text-lg font-bold text-ink">Satuan</span>
                                <select v-model="item.unit_id" class="min-h-touch w-full rounded-2xl border-2 border-line bg-surface px-4 text-lg text-ink">
                                    <option :value="null">{{ productById(item.product_id)?.unit }}</option>
                                    <option v-for="u in unitsFor(item)" :key="u.id" :value="u.id">{{ u.name }} (isi {{ u.conversion }})</option>
                                </select>
                            </label>
                            <QtyInput v-model="item.qty" label="Jumlah" decimal :error="form.errors[`items.${i}.qty`]" />
                            <MoneyInput v-model="item.unit_cost" label="Harga beli per satuan" :error="form.errors[`items.${i}.unit_cost`]" />
                        </div>

                        <!-- Batch / lot -->
                        <div v-if="withBatch && productById(item.product_id)?.track_batch" class="grid gap-3 rounded-2xl bg-surface-2 p-3 sm:grid-cols-3">
                            <BigInput v-model="item.batch_no" label="No batch" optional placeholder="Contoh: JG-0610" :error="form.errors[`items.${i}.batch_no`]" />
                            <BigInput v-model="item.expires_at" label="Kedaluwarsa" type="date" optional :error="form.errors[`items.${i}.expires_at`]" />
                            <BigInput v-model="item.moisture" label="Kadar air (%)" inputmode="decimal" optional placeholder="Contoh: 14" :error="form.errors[`items.${i}.moisture`]" />
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <button v-if="canWeigh && !unitsFor(item).length" type="button" class="pressable flex min-h-12 items-center gap-2 rounded-xl px-2 text-base font-bold text-primary-ink" @click="toggleWeigh(item)">
                                <Scale :size="20" aria-hidden="true" />
                                {{ item.weigh ? 'Tanpa timbangan' : 'Pakai timbangan' }}
                            </button>
                            <span v-else />
                            <p class="text-right text-lg text-ink-soft">
                                Subtotal <strong class="text-ink">{{ formatRupiah(lineTotal(item)) }}</strong>
                                <span v-if="withLandedCost && landedCost(item) !== null" class="block text-base">
                                    Modal jadi {{ formatRupiah(landedCost(item)) }}/{{ productById(item.product_id)?.unit }}
                                </span>
                            </p>
                        </div>
                    </li>
                </ul>
            </div>

            <!-- Biaya tambahan (masuk ke modal, bukan utang pemasok) -->
            <div v-if="withLandedCost" class="card flex flex-col gap-4 p-5">
                <div>
                    <h2 class="text-xl font-extrabold text-ink">Biaya tambahan</h2>
                    <p class="text-base text-ink-soft">Ikut dihitung ke modal barang. Tidak menambah utang ke pemasok.</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-3">
                    <MoneyInput v-model="form.freight_cost" label="Ongkos angkut" optional :error="form.errors.freight_cost" />
                    <MoneyInput v-model="form.unloading_cost" label="Bongkar muat" optional :error="form.errors.unloading_cost" />
                    <MoneyInput v-model="form.other_cost" label="Biaya lain" optional :error="form.errors.other_cost" />
                </div>
            </div>

            <div class="card flex flex-col gap-4 p-5">
                <MoneyDisplay :amount="total" size="xl" label="Total ke pemasok" />
                <SegmentedControl v-model="payMode" label="Pembayaran" :options="[{ value: 'full', label: 'Lunas' }, { value: 'partial', label: 'Bayar sebagian' }, { value: 'none', label: 'Utang semua' }]" />
                <MoneyInput v-if="payMode === 'partial'" v-model="form.paid_amount" label="Yang dibayar sekarang" :error="form.errors.paid_amount" />
                <label v-if="payMode !== 'none'" class="block">
                    <span class="mb-2 block text-lg font-bold text-ink">Dibayar dengan</span>
                    <select v-model="form.payment_method_id" class="min-h-touch w-full rounded-2xl border-2 border-line bg-surface px-4 text-lg text-ink">
                        <option v-for="m in paymentMethods" :key="m.id" :value="m.id">{{ m.name }}</option>
                    </select>
                </label>
                <template v-if="debt > 0">
                    <p class="rounded-2xl bg-danger-soft p-4 text-lg font-bold text-danger-ink">Utang ke pemasok: {{ formatRupiah(debt) }}</p>
                    <BigInput v-model="form.due_date" label="Jatuh tempo" type="date" optional :error="form.errors.due_date" />
                </template>
                <BigInput v-model="form.note" label="Catatan" optional :error="form.errors.note" />
            </div>

            <BigButton type="submit" block size="large" :loading="form.processing" :disabled="!form.items.length">{{ canWeigh ? 'Simpan Barang Masuk' : 'Simpan Belanja' }}</BigButton>
        </form>
    </div>
</template>
