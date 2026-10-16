<script setup>
/**
 * Untung Telur (Ringkasan Keuangan Pengepul):
 * Hari ini, Minggu ini, Bulan ini — Untung bersih, Uang masuk, Uang keluar, Kg terjual,
 * rincian hitungan untung, untung per jenis telur, ringkasan sortir, utang ke peternak, & piutang pembeli.
 * Semua angka rupiah berasal langsung dari backend (tidak dihitung ulang di Vue).
 */
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronRight, TrendingDown, TrendingUp, TriangleAlert } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import { formatRupiah } from '@/composables/useRupiah';

defineOptions({ layout: AppLayout });

const props = defineProps({
    outlet: { type: String, required: true },
    outlets: { type: Array, required: true },
    can_see_all: { type: Boolean, default: false },
    periods: { type: Object, required: true },
    receivables: { type: Object, required: true },
    payables: { type: Object, required: true },
});

const tanggal = (d) => (d ? new Date(`${d}T00:00:00`).toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'short', year: 'numeric' }) : '');

const periodKey = ref('today');

const periodOptions = computed(() => [
    { value: 'today', label: props.periods.today?.label ?? 'Hari ini' },
    { value: 'week', label: props.periods.week?.label ?? 'Minggu ini' },
    { value: 'month', label: props.periods.month?.label ?? 'Bulan ini' },
]);

const current = computed(() => props.periods[periodKey.value] ?? props.periods.today);

function changeOutlet(value) {
    router.get(
        route('egg.summary'),
        { outlet: value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}
</script>

<template>
    <Head title="Untung Telur" />
    <div class="mx-auto max-w-4xl">
        <PageHeader
            title="Untung Telur"
            :subtitle="`${current.label} (${current.from === current.to ? tanggal(current.from) : `${tanggal(current.from)} s.d. ${tanggal(current.to)}`})`"
        >
            <template #action>
                <div class="flex flex-wrap gap-2">
                    <BigButton :href="route('egg.prices')" variant="secondary">Harga Hari Ini</BigButton>
                    <BigButton :href="route('egg.sortings.create')" variant="secondary">Sortir Telur</BigButton>
                </div>
            </template>
        </PageHeader>

        <!-- Pilihan Outlet & Periode -->
        <div class="mb-5 flex flex-col gap-3">
            <label v-if="outlets.length > 1" class="block max-w-sm">
                <span class="mb-1 block text-base font-bold text-ink-soft">Gudang / Outlet</span>
                <select
                    :value="outlet"
                    aria-label="Pilih Gudang atau Outlet"
                    class="min-h-touch w-full rounded-2xl border-2 border-line bg-surface px-4 text-lg font-bold text-ink"
                    @change="changeOutlet($event.target.value)"
                >
                    <option v-if="can_see_all" value="semua">Semua gudang / outlet</option>
                    <option v-for="o in outlets" :key="o.id" :value="String(o.id)">{{ o.name }}</option>
                </select>
            </label>

            <SegmentedControl
                v-model="periodKey"
                label="Pilih periode laporan"
                :options="periodOptions"
            />
        </div>

        <!-- Kartu Utama: Untung Bersih paling besar -->
        <section class="card mb-4 border-2 border-line p-5 sm:p-6">
            <p class="text-lg font-bold text-ink-soft">Untung bersih ({{ current.label.toLowerCase() }})</p>
            <p
                class="mt-1 font-display text-4xl leading-tight font-extrabold tabular-nums sm:text-5xl"
                :class="current.net_profit < 0 ? 'text-danger-ink' : 'text-primary-ink'"
            >
                {{ formatRupiah(current.net_profit) }}
            </p>
            <p
                v-if="current.change?.net_profit !== null && current.change?.net_profit !== undefined"
                class="mt-2 flex items-center gap-1.5 text-lg font-bold"
                :class="current.change.net_profit >= 0 ? 'text-primary-ink' : 'text-danger-ink'"
            >
                <component
                    :is="current.change.net_profit >= 0 ? TrendingUp : TrendingDown"
                    :size="22"
                    aria-hidden="true"
                />
                {{ current.change.net_profit >= 0 ? 'Naik' : 'Turun' }}
                {{ Math.abs(current.change.net_profit).toLocaleString('id-ID') }}%
                <span class="font-normal text-ink-soft">
                    dibanding {{ current.previous?.label?.toLowerCase() || 'periode lalu' }}
                    ({{ formatRupiah(current.previous?.net_profit ?? 0) }})
                </span>
            </p>
            <p v-else-if="current.previous?.label" class="mt-2 text-base text-ink-soft">
                {{ current.previous.label }}: {{ formatRupiah(current.previous.net_profit ?? 0) }}
            </p>
        </section>

        <!-- 3 Kartu Pendukung: Uang Masuk, Uang Keluar, Telur Terjual -->
        <div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="card flex flex-col gap-1 p-5">
                <p class="text-base font-bold text-ink-soft">Uang masuk</p>
                <p class="font-display text-2xl font-extrabold text-ink tabular-nums">
                    {{ formatRupiah(current.cash_in) }}
                </p>
                <p
                    v-if="current.change?.cash_in !== null && current.change?.cash_in !== undefined"
                    class="flex items-center gap-1 text-base font-bold"
                    :class="current.change.cash_in >= 0 ? 'text-primary-ink' : 'text-danger-ink'"
                >
                    <component
                        :is="current.change.cash_in >= 0 ? TrendingUp : TrendingDown"
                        :size="18"
                        aria-hidden="true"
                    />
                    {{ current.change.cash_in >= 0 ? 'Naik' : 'Turun' }}
                    {{ Math.abs(current.change.cash_in).toLocaleString('id-ID') }}%
                    <span class="font-normal text-ink-soft">dibanding {{ current.previous?.label?.toLowerCase() }}</span>
                </p>
                <p v-if="current.kasbon_collected > 0" class="text-base text-ink-soft">
                    Termasuk cicilan kasbon {{ formatRupiah(current.kasbon_collected) }}
                </p>
            </div>

            <div class="card flex flex-col gap-1 p-5">
                <p class="text-base font-bold text-ink-soft">Uang keluar</p>
                <p class="font-display text-2xl font-extrabold text-ink tabular-nums">
                    {{ formatRupiah(current.cash_out) }}
                </p>
                <p class="text-base text-ink-soft">
                    Bayar peternak {{ formatRupiah(current.paid_farmers) }} · ongkos {{ formatRupiah(current.expenses) }}
                </p>
            </div>

            <div class="card flex flex-col gap-1 p-5">
                <p class="text-base font-bold text-ink-soft">Telur terjual</p>
                <p class="font-display text-2xl font-extrabold text-ink tabular-nums">
                    {{ current.kg_sold }} Kg
                </p>
                <p class="text-base text-ink-soft">
                    {{ current.transactions }} transaksi · beli {{ current.kg_bought }} Kg dari peternak
                </p>
            </div>
        </div>

        <!-- Rincian Hitungan Untung & Kotak Sortir -->
        <div class="mb-6 grid grid-cols-1 items-start gap-4 lg:grid-cols-2">
            <section class="card p-5">
                <h2 class="mb-3 text-xl font-extrabold text-ink">Rincian hitungan untung</h2>
                <dl class="flex flex-col gap-2.5 text-lg">
                    <div class="flex justify-between gap-3">
                        <dt class="text-ink-soft">Penjualan telur</dt>
                        <dd class="font-bold text-ink tabular-nums">{{ formatRupiah(current.sales) }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-ink-soft">Modal telur terjual</dt>
                        <dd class="text-danger-ink tabular-nums">− {{ formatRupiah(current.cogs) }}</dd>
                    </div>
                    <div class="flex justify-between gap-3 border-t border-line pt-2 font-bold">
                        <dt class="text-ink">Untung kotor</dt>
                        <dd class="text-ink tabular-nums">{{ formatRupiah(current.gross_profit) }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-ink-soft">Telur pecah saat sortir</dt>
                        <dd class="text-danger-ink tabular-nums">− {{ formatRupiah(current.sorting_loss) }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-ink-soft">Telur rusak / hilang (Stok Keluar)</dt>
                        <dd class="text-danger-ink tabular-nums">− {{ formatRupiah(current.damaged_loss) }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-ink-soft">Pengeluaran / ongkos</dt>
                        <dd class="text-danger-ink tabular-nums">− {{ formatRupiah(current.expenses) }}</dd>
                    </div>
                    <div class="flex justify-between gap-3 border-t-2 border-ink pt-2.5 text-xl font-extrabold">
                        <dt class="text-ink">Untung bersih</dt>
                        <dd
                            class="tabular-nums"
                            :class="current.net_profit < 0 ? 'text-danger-ink' : 'text-primary-ink'"
                        >
                            {{ formatRupiah(current.net_profit) }}
                        </dd>
                    </div>
                </dl>
                <p v-if="current.unpaid_sales > 0" class="mt-3 rounded-xl bg-warn-soft px-3 py-2 text-base font-bold text-warn-ink">
                    Penjualan belum dibayar (kasbon/tempo): {{ formatRupiah(current.unpaid_sales) }}
                </p>
            </section>

            <!-- Kotak Sortir -->
            <section class="card flex flex-col justify-between gap-4 p-5">
                <div>
                    <h2 class="mb-3 text-xl font-extrabold text-ink">Sortir telur ({{ current.label.toLowerCase() }})</h2>
                    <dl class="flex flex-col gap-2.5 text-lg">
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-soft">Jumlah sortir</dt>
                            <dd class="font-bold text-ink tabular-nums">{{ current.sorting?.count ?? 0 }} kali</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-soft">Telur campur disortir</dt>
                            <dd class="font-bold text-ink tabular-nums">{{ current.sorting?.input_kg ?? '0' }} Kg</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-soft">Pecah / susut sortir</dt>
                            <dd class="font-bold text-warn-ink tabular-nums">
                                {{ current.sorting?.shrinkage_kg ?? '0' }} Kg
                                ({{ (current.sorting?.shrinkage_percent ?? 0).toLocaleString('id-ID') }}%)
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3 border-t border-line pt-2">
                            <dt class="text-ink-soft">Nilai rugi pecah</dt>
                            <dd class="font-bold text-danger-ink tabular-nums">
                                {{ formatRupiah(current.sorting_loss) }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <Link
                    :href="route('egg.sortings.create')"
                    class="pressable inline-flex min-h-touch items-center justify-between rounded-2xl bg-surface-2 px-4 text-lg font-bold text-primary-ink hover:bg-primary-soft"
                >
                    <span>Buka halaman Sortir Telur</span>
                    <ChevronRight :size="22" aria-hidden="true" />
                </Link>
            </section>
        </div>

        <!-- Tabel / Daftar Untung per Jenis Telur -->
        <section class="mb-6">
            <h2 class="mb-3 text-xl font-extrabold text-ink">Untung per jenis telur</h2>
            <EmptyState
                v-if="!current.eggs?.length"
                title="Belum ada penjualan telur"
                :message="`Belum ada telur yang terjual pada periode ${current.label.toLowerCase()}.`"
            />
            <div v-else class="card overflow-x-auto">
                <table class="w-full min-w-max text-left text-lg">
                    <thead>
                        <tr class="border-b border-line text-base text-ink-soft">
                            <th scope="col" class="px-4 py-3 font-bold">Jenis Telur</th>
                            <th scope="col" class="px-4 py-3 text-right font-bold">Terjual</th>
                            <th scope="col" class="px-4 py-3 text-right font-bold">Penjualan</th>
                            <th scope="col" class="px-4 py-3 text-right font-bold">Modal</th>
                            <th scope="col" class="px-4 py-3 text-right font-bold">Untung</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        <tr v-for="egg in current.eggs" :key="egg.product_id">
                            <th scope="row" class="px-4 py-3 font-extrabold text-ink">{{ egg.name }}</th>
                            <td class="px-4 py-3 text-right font-bold text-ink tabular-nums">
                                {{ egg.qty }} {{ egg.unit }}
                            </td>
                            <td class="px-4 py-3 text-right text-ink tabular-nums">
                                {{ formatRupiah(egg.revenue) }}
                            </td>
                            <td class="px-4 py-3 text-right text-ink-soft tabular-nums">
                                {{ formatRupiah(egg.cost) }}
                            </td>
                            <td
                                class="px-4 py-3 text-right font-extrabold tabular-nums"
                                :class="egg.profit < 0 ? 'text-danger-ink' : 'text-primary-ink'"
                            >
                                {{ formatRupiah(egg.profit) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Utang ke Peternak & Piutang Pembeli -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <!-- Utang ke Peternak -->
            <section class="card flex flex-col justify-between p-5">
                <div>
                    <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                        <h2 class="text-xl font-extrabold text-ink">Utang ke peternak</h2>
                        <span class="font-display text-2xl font-extrabold tabular-nums" :class="payables.total > 0 ? 'text-danger-ink' : 'text-ink'">
                            {{ formatRupiah(payables.total) }}
                        </span>
                    </div>

                    <p v-if="!payables.suppliers?.length" class="py-4 text-lg text-ink-soft">
                        Tidak ada utang belanja ke peternak. Semua sudah lunas.
                    </p>
                    <ul v-else class="divide-y divide-line">
                        <li
                            v-for="s in payables.suppliers"
                            :key="s.supplier_id ?? s.name"
                            class="flex min-h-touch items-center justify-between gap-3 py-3"
                        >
                            <span class="min-w-0">
                                <span class="block text-lg font-extrabold text-ink">{{ s.name }}</span>
                                <span class="flex flex-wrap items-center gap-1.5 text-base text-ink-soft">
                                    <span>{{ s.count }} nota</span>
                                    <template v-if="s.oldest_due">
                                        <span>· jatuh tempo {{ s.oldest_due }}</span>
                                    </template>
                                    <span
                                        v-if="s.overdue"
                                        class="inline-flex items-center gap-1 rounded-lg bg-danger-soft px-2 py-0.5 text-base font-bold text-danger-ink"
                                    >
                                        <TriangleAlert :size="16" aria-hidden="true" /> Lewat jatuh tempo
                                    </span>
                                </span>
                            </span>
                            <span
                                class="shrink-0 font-display text-xl font-extrabold tabular-nums"
                                :class="s.overdue ? 'text-danger-ink' : 'text-ink'"
                            >
                                {{ formatRupiah(s.amount) }}
                            </span>
                        </li>
                    </ul>
                </div>

                <Link
                    :href="route('purchases.index')"
                    class="pressable mt-4 inline-flex min-h-touch items-center justify-between rounded-2xl bg-surface-2 px-4 text-lg font-bold text-primary-ink hover:bg-primary-soft"
                >
                    <span>Lihat belanja &amp; bayar peternak</span>
                    <ChevronRight :size="22" aria-hidden="true" />
                </Link>
            </section>

            <!-- Piutang Pembeli -->
            <section class="card flex flex-col justify-between p-5">
                <div>
                    <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                        <div>
                            <h2 class="text-xl font-extrabold text-ink">Piutang pembeli</h2>
                            <p v-if="receivables.customer_count" class="text-base text-ink-soft">
                                {{ receivables.customer_count }} pelanggan belum lunas
                            </p>
                        </div>
                        <span class="font-display text-2xl font-extrabold text-ink tabular-nums">
                            {{ formatRupiah(receivables.total) }}
                        </span>
                    </div>

                    <p v-if="!receivables.customers?.length" class="py-4 text-lg text-ink-soft">
                        Tidak ada piutang pembeli yang belum lunas.
                    </p>
                    <ul v-else class="divide-y divide-line">
                        <li
                            v-for="c in receivables.customers"
                            :key="c.customer_id ?? c.name"
                            class="flex min-h-touch items-center justify-between gap-3 py-3"
                        >
                            <span class="min-w-0">
                                <span class="block text-lg font-extrabold text-ink">{{ c.name }}</span>
                                <span class="block text-base text-ink-soft">
                                    {{ c.count }} nota
                                    <template v-if="c.oldest_due"> · jatuh tempo {{ c.oldest_due }}</template>
                                </span>
                            </span>
                            <span class="shrink-0 font-display text-xl font-extrabold text-ink tabular-nums">
                                {{ formatRupiah(c.amount) }}
                            </span>
                        </li>
                    </ul>
                </div>

                <Link
                    :href="route('receivables.index')"
                    class="pressable mt-4 inline-flex min-h-touch items-center justify-between rounded-2xl bg-surface-2 px-4 text-lg font-bold text-primary-ink hover:bg-primary-soft"
                >
                    <span>Lihat &amp; tagih piutang pembeli</span>
                    <ChevronRight :size="22" aria-hidden="true" />
                </Link>
            </section>
        </div>
    </div>
</template>
