<script setup>
/**
 * Laporan. Pilih periode (Hari ini / 7 hari / Bulan ini / tanggal sendiri) dan outlet,
 * lalu lihat per bagian. Bisa diunduh Excel atau dicetak / disimpan PDF.
 */
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { CalendarRange, FileSpreadsheet, Printer } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import BarChart from '@/Components/BarChart.vue';
import BottomSheet from '@/Components/ui/BottomSheet.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import BigInput from '@/Components/ui/BigInput.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SalesTabs from '@/Components/SalesTabs.vue';
import StatCard from '@/Components/StatCard.vue';
import { formatRupiah } from '@/composables/useRupiah';

defineOptions({ layout: AppLayout });

const props = defineProps({
    tab: { type: String, required: true },
    filters: { type: Object, required: true },
    presets: { type: Object, required: true },
    outlets: { type: Array, required: true },
    summary: { type: Object, default: null },
    series: { type: Array, default: () => [] },
    hours: { type: Array, default: () => [] },
    expenseCategories: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    payments: { type: Array, default: () => [] },
    cashiers: { type: Array, default: () => [] },
    staff: { type: Array, default: () => [] },
    outletSales: { type: Array, default: () => [] },
});

const TABS = [
    { key: 'ringkasan', label: 'Ringkasan' },
    { key: 'barang', label: 'Barang' },
    { key: 'pembayaran', label: 'Cara Bayar' },
    { key: 'karyawan', label: 'Karyawan' },
    { key: 'outlet', label: 'Outlet' },
];

const customOpen = ref(false);
const custom = ref({ dari: props.filters.dari, sampai: props.filters.sampai });
const activePreset = computed(() => Object.entries(props.presets).find(([, p]) => p.from === props.filters.dari && p.to === props.filters.sampai)?.[0] ?? null);
const query = computed(() => ({ dari: props.filters.dari, sampai: props.filters.sampai, outlet: props.filters.outlet }));
const visibleTabs = computed(() => TABS.filter((t) => t.key !== 'outlet' || props.filters.canAllOutlets));

function go(params) {
    router.get(route('reports.index'), { ...query.value, tab: props.tab, ...params }, { preserveState: true, preserveScroll: true, replace: true });
}

function applyCustom() {
    customOpen.value = false;
    go({ dari: custom.value.dari, sampai: custom.value.sampai });
}

const chartPoints = computed(() => props.series.map((p) => ({ label: p.label, value: p.total, sub: `${p.transactions} transaksi` })));
const hourPoints = computed(() => props.hours.slice(6, 24).map((h) => ({ label: h.label, value: h.transactions, sub: formatRupiah(h.total) })));
const busiest = computed(() => [...props.hours].sort((a, b) => b.transactions - a.transactions)[0]);
const maxRevenue = computed(() => Math.max(1, ...props.products.map((p) => p.revenue)));
const qty = (n) => Number(n).toLocaleString('id-ID', { maximumFractionDigits: 3 });
</script>

<template>
    <Head title="Laporan" />
    <PageHeader title="Laporan" :subtitle="filters.label" help="reports">
        <template #action>
            <div class="flex flex-wrap gap-2">
                <a :href="route('reports.export', query)" class="pressable flex min-h-touch items-center gap-2 rounded-2xl border-2 border-line bg-surface px-4 text-lg font-bold text-ink">
                    <FileSpreadsheet :size="22" aria-hidden="true" /> Excel
                </a>
                <a :href="route('reports.print', query)" target="_blank" class="pressable flex min-h-touch items-center gap-2 rounded-2xl border-2 border-line bg-surface px-4 text-lg font-bold text-ink">
                    <Printer :size="22" aria-hidden="true" /> Cetak / PDF
                </a>
            </div>
        </template>
    </PageHeader>

    <SalesTabs />

    <!-- Periode & outlet -->
    <div class="-mx-4 mb-3 flex gap-2 overflow-x-auto px-4 pb-1">
        <button
            v-for="(p, key) in presets"
            :key="key"
            type="button"
            class="pressable min-h-touch shrink-0 rounded-2xl px-4 text-lg font-bold"
            :class="activePreset === key ? 'bg-primary text-on-primary' : 'bg-surface-2 text-ink'"
            @click="go({ dari: p.from, sampai: p.to })"
        >
            {{ p.label }}
        </button>
        <button type="button" class="pressable flex min-h-touch shrink-0 items-center gap-2 rounded-2xl px-4 text-lg font-bold" :class="!activePreset ? 'bg-primary text-on-primary' : 'bg-surface-2 text-ink'" @click="customOpen = true">
            <CalendarRange :size="20" aria-hidden="true" /> Pilih tanggal
        </button>
    </div>
    <label v-if="outlets.length > 1 || filters.canAllOutlets" class="mb-4 block max-w-sm">
        <span class="sr-only">Outlet</span>
        <select :value="filters.outlet" class="min-h-touch w-full rounded-2xl border-2 border-line bg-surface px-4 text-lg text-ink" @change="go({ outlet: $event.target.value })">
            <option v-if="filters.canAllOutlets" value="semua">Semua outlet</option>
            <option v-for="o in outlets" :key="o.id" :value="String(o.id)">{{ o.name }}</option>
        </select>
    </label>

    <!-- Tab bagian laporan -->
    <nav class="-mx-4 mb-6 flex gap-1 overflow-x-auto border-b-2 border-line px-4" aria-label="Bagian laporan">
        <button
            v-for="t in visibleTabs"
            :key="t.key"
            type="button"
            class="-mb-[2px] min-h-touch shrink-0 border-b-4 px-4 text-lg font-bold"
            :class="tab === t.key ? 'border-primary text-primary-ink' : 'border-transparent text-ink-soft hover:text-ink'"
            :aria-current="tab === t.key ? 'page' : undefined"
            @click="go({ tab: t.key })"
        >
            {{ t.label }}
        </button>
    </nav>

    <!-- RINGKASAN -->
    <template v-if="tab === 'ringkasan' && summary">
        <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard label="Uang masuk" :value="summary.total_collected" :change="summary.change.total_collected" hint="dari periode sebelumnya" big />
            <StatCard label="Transaksi" :value="summary.transactions" :money="false" :change="summary.change.transactions" :hint="`rata-rata ${formatRupiah(summary.average)}`" />
            <StatCard label="Untung kotor" :value="summary.gross_profit" :change="summary.change.gross_profit" :hint="`margin ${summary.margin}%`" />
            <StatCard label="Untung bersih" :value="summary.net_profit" :tone="summary.net_profit < 0 ? 'negative' : 'positive'" :hint="`setelah pengeluaran ${formatRupiah(summary.expenses)}`" />
        </div>

        <div class="grid grid-cols-1 items-start gap-4 xl:grid-cols-[3fr_2fr]">
            <section class="card min-w-0 p-5">
                <h2 class="mb-2 text-xl font-extrabold text-ink">Penjualan</h2>
                <BarChart v-if="chartPoints.length > 1" :points="chartPoints" label="Ketuk batang untuk melihat angka" />
                <p v-else class="text-lg text-ink-soft">Pilih “7 hari” atau “Bulan ini” untuk melihat grafik.</p>
            </section>

            <section class="card min-w-0 p-5">
                <h2 class="mb-3 text-xl font-extrabold text-ink">Hitungan untung</h2>
                <dl class="flex flex-col gap-2 text-lg">
                    <div class="flex justify-between gap-3"><dt class="text-ink-soft">Penjualan kotor</dt><dd class="whitespace-nowrap tabular-nums">{{ formatRupiah(summary.gross_sales) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-soft">Diskon</dt><dd class="whitespace-nowrap tabular-nums text-danger-ink">− {{ formatRupiah(summary.discounts) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-soft">Barang dikembalikan</dt><dd class="whitespace-nowrap tabular-nums text-danger-ink">− {{ formatRupiah(summary.refunds) }}</dd></div>
                    <div class="flex justify-between gap-3 border-t border-line pt-2 font-bold"><dt>Penjualan bersih</dt><dd class="whitespace-nowrap tabular-nums">{{ formatRupiah(summary.net_sales) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-soft">Modal barang terjual</dt><dd class="whitespace-nowrap tabular-nums text-danger-ink">− {{ formatRupiah(summary.cogs) }}</dd></div>
                    <div class="flex justify-between gap-3 border-t border-line pt-2 font-bold"><dt>Untung kotor</dt><dd class="whitespace-nowrap tabular-nums">{{ formatRupiah(summary.gross_profit) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-soft">Pengeluaran</dt><dd class="whitespace-nowrap tabular-nums text-danger-ink">− {{ formatRupiah(summary.expenses) }}</dd></div>
                    <div class="flex justify-between gap-3 border-t-2 border-ink pt-2 text-xl font-extrabold"><dt>Untung bersih</dt><dd class="whitespace-nowrap tabular-nums" :class="summary.net_profit < 0 ? 'text-danger-ink' : 'text-primary-ink'">{{ formatRupiah(summary.net_profit) }}</dd></div>
                </dl>
                <p class="mt-3 text-base text-ink-soft">
                    Pajak dipungut {{ formatRupiah(summary.tax) }}<template v-if="summary.service"> · biaya layanan {{ formatRupiah(summary.service) }}</template><template v-if="summary.unpaid"> · belum dibayar {{ formatRupiah(summary.unpaid) }}</template>
                </p>
                <p v-if="!summary.cogs && summary.net_sales" class="mt-2 rounded-xl bg-warn-soft p-3 text-base text-warn-ink">Modal barang masih 0. Isi “Modal” di data barang supaya untung terhitung benar.</p>
            </section>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
            <section class="card min-w-0 p-5">
                <h2 class="mb-1 text-xl font-extrabold text-ink">Jam ramai</h2>
                <p v-if="busiest?.transactions" class="mb-2 text-base text-ink-soft">Paling ramai jam {{ busiest.label }}.00 ({{ busiest.transactions }} transaksi)</p>
                <BarChart :points="hourPoints" :money="false" :height="140" label="Jumlah transaksi per jam" />
            </section>
            <section class="card min-w-0 p-5">
                <h2 class="mb-3 text-xl font-extrabold text-ink">Pengeluaran</h2>
                <ul v-if="expenseCategories.length" class="flex flex-col gap-2">
                    <li v-for="e in expenseCategories" :key="e.name" class="flex justify-between gap-3 text-lg"><span class="text-ink">{{ e.name }}</span><span class="font-bold tabular-nums">{{ formatRupiah(e.total) }}</span></li>
                </ul>
                <p v-else class="text-lg text-ink-soft">Belum ada pengeluaran di periode ini.</p>
            </section>
        </div>
    </template>

    <!-- BARANG -->
    <template v-if="tab === 'barang'">
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <span class="text-lg text-ink-soft">Urutkan:</span>
            <button
                v-for="s in [{ v: 'revenue', l: 'Penjualan' }, { v: 'qty', l: 'Jumlah terjual' }, { v: 'profit', l: 'Untung' }]"
                :key="s.v"
                type="button"
                class="pressable min-h-12 rounded-xl px-3 text-base font-bold"
                :class="filters.urut === s.v ? 'bg-primary-soft text-primary-ink' : 'bg-surface-2 text-ink'"
                @click="go({ urut: s.v })"
            >
                {{ s.l }}
            </button>
        </div>
        <EmptyState v-if="!products.length" title="Belum ada barang terjual" message="Coba pilih periode lain." />
        <ul v-else class="card divide-y divide-line overflow-hidden">
            <li v-for="(p, i) in products" :key="p.product_id ?? p.name" class="px-4 py-3">
                <div class="flex items-start justify-between gap-3">
                    <span class="min-w-0">
                        <span class="block text-lg font-bold text-ink"><span class="text-ink-soft">{{ i + 1 }}.</span> {{ p.name }}</span>
                        <span class="block text-base text-ink-soft">{{ qty(p.qty) }} {{ p.unit }} · {{ p.category }}</span>
                    </span>
                    <span class="shrink-0 text-right">
                        <span class="block font-display text-lg font-extrabold text-ink tabular-nums">{{ formatRupiah(p.revenue) }}</span>
                        <span class="block text-base tabular-nums" :class="p.profit < 0 ? 'text-danger-ink' : 'text-primary-ink'">untung {{ formatRupiah(p.profit) }}</span>
                    </span>
                </div>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-surface-2" aria-hidden="true"><div class="h-full rounded-full bg-primary/70" :style="{ width: `${(p.revenue / maxRevenue) * 100}%` }" /></div>
            </li>
        </ul>

        <section v-if="categories.length > 1" class="mt-6">
            <h2 class="mb-3 text-xl font-extrabold text-ink">Per kategori</h2>
            <ul class="card divide-y divide-line">
                <li v-for="c in categories" :key="c.name" class="flex min-h-touch items-center justify-between gap-3 px-4 py-2 text-lg">
                    <span class="font-bold text-ink">{{ c.name }}</span>
                    <span class="text-right tabular-nums">{{ formatRupiah(c.revenue) }} <span class="block text-base text-primary-ink">untung {{ formatRupiah(c.profit) }}</span></span>
                </li>
            </ul>
        </section>
    </template>

    <!-- CARA BAYAR -->
    <template v-if="tab === 'pembayaran'">
        <EmptyState v-if="!payments.length" title="Belum ada pembayaran" />
        <ul v-else class="grid gap-3 sm:grid-cols-2">
            <li v-for="p in payments" :key="p.name" class="card flex flex-col gap-1 p-4">
                <p class="text-lg font-extrabold text-ink">{{ p.name }}</p>
                <p class="font-display text-2xl font-extrabold text-ink tabular-nums">{{ formatRupiah(p.net) }}</p>
                <p class="text-base text-ink-soft">{{ p.transactions }} transaksi<template v-if="p.refunds"> · dikembalikan {{ formatRupiah(p.refunds) }}</template></p>
            </li>
        </ul>
        <p v-if="summary?.unpaid" class="mt-4 rounded-2xl bg-warn-soft p-4 text-lg text-warn-ink">Belum dibayar (kasbon / jual tempo): <strong>{{ formatRupiah(summary.unpaid) }}</strong></p>
    </template>

    <!-- KARYAWAN -->
    <template v-if="tab === 'karyawan'">
        <h2 class="mb-3 text-xl font-extrabold text-ink">Per kasir</h2>
        <EmptyState v-if="!cashiers.length" title="Belum ada transaksi" />
        <ul v-else class="card mb-6 divide-y divide-line">
            <li v-for="c in cashiers" :key="c.user_id" class="flex min-h-touch items-center justify-between gap-3 px-4 py-3">
                <span><span class="block text-lg font-bold text-ink">{{ c.name }}</span><span class="block text-base text-ink-soft">{{ c.transactions }} transaksi · rata-rata {{ formatRupiah(c.average) }}</span></span>
                <span class="font-display text-lg font-extrabold text-ink tabular-nums">{{ formatRupiah(c.total) }}</span>
            </li>
        </ul>
        <template v-if="staff.length">
            <h2 class="mb-3 text-xl font-extrabold text-ink">Per karyawan yang mengerjakan</h2>
            <ul class="card divide-y divide-line">
                <li v-for="s in staff" :key="s.user_id" class="flex min-h-touch items-center justify-between gap-3 px-4 py-3">
                    <span><span class="block text-lg font-bold text-ink">{{ s.name }}</span><span class="block text-base text-ink-soft">{{ s.jobs }} pekerjaan<template v-if="s.commission"> · komisi {{ formatRupiah(s.commission) }}</template></span></span>
                    <span class="font-display text-lg font-extrabold text-ink tabular-nums">{{ formatRupiah(s.revenue) }}</span>
                </li>
            </ul>
        </template>
    </template>

    <!-- OUTLET -->
    <template v-if="tab === 'outlet'">
        <EmptyState v-if="!outletSales.length" title="Belum ada penjualan" />
        <ul class="grid gap-3 md:grid-cols-2">
            <li v-for="o in outletSales" :key="o.outlet_id" class="card flex flex-col gap-1 p-4">
                <p class="text-lg font-extrabold text-ink">{{ o.name }}</p>
                <p class="font-display text-2xl font-extrabold text-ink tabular-nums">{{ formatRupiah(o.total) }}</p>
                <p class="text-base text-ink-soft">{{ o.transactions }} transaksi · pengeluaran {{ formatRupiah(o.expenses) }}</p>
            </li>
        </ul>
    </template>

    <BottomSheet v-model:open="customOpen" title="Pilih Tanggal">
        <form class="flex flex-col gap-4" @submit.prevent="applyCustom">
            <BigInput v-model="custom.dari" label="Dari tanggal" type="date" />
            <BigInput v-model="custom.sampai" label="Sampai tanggal" type="date" />
            <BigButton type="submit" block size="large">Tampilkan</BigButton>
        </form>
    </BottomSheet>
</template>
