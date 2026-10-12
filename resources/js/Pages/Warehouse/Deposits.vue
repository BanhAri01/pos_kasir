<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowDownRight,
    ArrowUpRight,
    Banknote,
    Clock,
    Copy,
    CreditCard,
    ExternalLink,
    HandCoins,
    History,
    MessageCircle,
    MinusCircle,
    NotebookPen,
    Plus,
    Receipt,
    Search,
    UserCheck,
    Wallet,
} from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import BigInput from '@/Components/ui/BigInput.vue';
import BottomSheet from '@/Components/ui/BottomSheet.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import MoneyDisplay from '@/Components/ui/MoneyDisplay.vue';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { formatRupiah } from '@/composables/useRupiah';

defineOptions({ layout: AppLayout });

const props = defineProps({
    customers: { type: Array, required: true },
    stats: { type: Object, required: true },
    filters: { type: Object, required: true },
    allCustomers: { type: Array, required: true },
    paymentMethods: { type: Array, required: true },
});

const search = ref(props.filters.q || '');
const currentTab = ref(props.filters.tab || 'all');

let searchTimer;
function onSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get(
            route('warehouse.deposits'),
            { q: search.value || undefined, tab: currentTab.value !== 'all' ? currentTab.value : undefined },
            { preserveState: true, replace: true }
        );
    }, 300);
}

function setTab(tab) {
    currentTab.value = tab;
    router.get(
        route('warehouse.deposits'),
        { q: search.value || undefined, tab: tab !== 'all' ? tab : undefined },
        { preserveState: true, replace: true }
    );
}

// Modals
const depositSheetOpen = ref(false);
const withdrawSheetOpen = ref(false);
const deductSheetOpen = ref(false);
const historySheetOpen = ref(false);

const selectedCustomer = ref(null);
const historyLoading = ref(false);
const customerHistory = ref({ customer: {}, deposits: [] });
const copiedCustomerId = ref(null);

const depositForm = useForm({
    customer_id: null,
    type: 'deposit',
    amount: null,
    notes: '',
    payment_method_id: props.paymentMethods[0]?.id ?? null,
});

const withdrawForm = useForm({
    customer_id: null,
    type: 'withdrawal',
    amount: null,
    notes: '',
    payment_method_id: props.paymentMethods[0]?.id ?? null,
});

const deductForm = useForm({
    customer_id: null,
    type: 'deduction',
    amount: null,
    notes: '',
    payment_method_id: props.paymentMethods[0]?.id ?? null,
});

function openDeposit(customer = null) {
    depositForm.reset();
    depositForm.clearErrors();
    depositForm.type = 'deposit';
    depositForm.customer_id = customer ? customer.id : (props.allCustomers[0]?.id ?? null);
    depositSheetOpen.value = true;
}

function submitDeposit() {
    depositForm.post(route('warehouse.deposits.store'), {
        preserveScroll: true,
        onSuccess: () => {
            depositSheetOpen.value = false;
        },
    });
}

function openWithdraw(customer) {
    selectedCustomer.value = customer;
    withdrawForm.reset();
    withdrawForm.clearErrors();
    withdrawForm.type = 'withdrawal';
    withdrawForm.customer_id = customer.id;
    withdrawForm.amount = customer.deposit_balance;
    withdrawSheetOpen.value = true;
}

function submitWithdraw() {
    withdrawForm.post(route('warehouse.deposits.store'), {
        preserveScroll: true,
        onSuccess: () => {
            withdrawSheetOpen.value = false;
        },
    });
}

function openDeduct(customer) {
    selectedCustomer.value = customer;
    deductForm.reset();
    deductForm.clearErrors();
    deductForm.type = 'deduction';
    deductForm.customer_id = customer.id;
    deductForm.amount = Math.min(customer.deposit_balance, customer.gross_debt);
    deductSheetOpen.value = true;
}

function submitDeduct() {
    deductForm.post(route('warehouse.deposits.store'), {
        preserveScroll: true,
        onSuccess: () => {
            deductSheetOpen.value = false;
        },
    });
}

async function openHistory(customer) {
    selectedCustomer.value = customer;
    historySheetOpen.value = true;
    historyLoading.value = true;
    try {
        const res = await fetch(route('warehouse.deposits.history', customer.id));
        customerHistory.value = await res.json();
    } catch (e) {
        console.error(e);
    } finally {
        historyLoading.value = false;
    }
}

async function copyLink(customer) {
    try {
        await navigator.clipboard.writeText(customer.bill_url);
        copiedCustomerId.value = customer.id;
        setTimeout(() => (copiedCustomerId.value = null), 2000);
    } catch {
        window.prompt('Salin link tagihan:', customer.bill_url);
    }
}
</script>

<template>
    <Head title="Titip Uang & Tagihan WA Gudang" />

    <PageHeader
        title="Titip Uang & Tagihan WA"
        subtitle="Kelola saldo titip uang pelanggan gudang, potong utang otomatis, dan kirim tagihan WhatsApp."
    >
        <template #action>
            <div class="flex flex-wrap gap-2">
                <BigButton variant="primary" @click="openDeposit()">
                    <Plus :size="22" aria-hidden="true" />
                    Catat Titip Uang
                </BigButton>
            </div>
        </template>
    </PageHeader>

    <!-- KPI Metric Cards -->
    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <!-- Saldo Titip Uang -->
        <div class="card flex flex-col justify-between border-l-4 border-l-emerald-500 p-5">
            <div class="flex items-center justify-between">
                <span class="text-base font-bold text-ink-soft">Total Titip Uang (Deposit)</span>
                <span class="flex size-10 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400">
                    <HandCoins :size="22" aria-hidden="true" />
                </span>
            </div>
            <div class="mt-3">
                <span class="font-display text-2xl lg:text-3xl font-black text-emerald-700 dark:text-emerald-400 tabular-nums">
                    {{ formatRupiah(stats.total_deposit) }}
                </span>
                <p class="mt-1 text-sm text-ink-soft">
                    {{ stats.count_deposit_customers }} pelanggan memiliki saldo
                </p>
            </div>
        </div>

        <!-- Total Piutang Kotor -->
        <div class="card flex flex-col justify-between border-l-4 border-l-amber-500 p-5">
            <div class="flex items-center justify-between">
                <span class="text-base font-bold text-ink-soft">Total Tagihan Nota Kotor</span>
                <span class="flex size-10 items-center justify-center rounded-xl bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400">
                    <Receipt :size="22" aria-hidden="true" />
                </span>
            </div>
            <div class="mt-3">
                <span class="font-display text-2xl lg:text-3xl font-extrabold text-ink tabular-nums">
                    {{ formatRupiah(stats.total_gross_debt) }}
                </span>
                <p class="mt-1 text-sm text-ink-soft">
                    {{ stats.count_debt_customers }} pelanggan dengan nota aktif
                </p>
            </div>
        </div>

        <!-- Sisa Kewajiban Bersih -->
        <div class="card flex flex-col justify-between border-l-4 border-l-rose-500 p-5">
            <div class="flex items-center justify-between">
                <span class="text-base font-bold text-ink-soft">Sisa Kewajiban Bersih</span>
                <span class="flex size-10 items-center justify-center rounded-xl bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-400">
                    <Wallet :size="22" aria-hidden="true" />
                </span>
            </div>
            <div class="mt-3">
                <span class="font-display text-2xl lg:text-3xl font-black text-rose-700 dark:text-rose-400 tabular-nums">
                    {{ formatRupiah(stats.total_net_debt) }}
                </span>
                <p class="mt-1 text-sm text-ink-soft">
                    Setelah terpotong titip uang
                </p>
            </div>
        </div>

        <!-- Rasio Aman -->
        <div class="card flex flex-col justify-between border-l-4 border-l-blue-500 p-5">
            <div class="flex items-center justify-between">
                <span class="text-base font-bold text-ink-soft">Kirim Tagihan WA</span>
                <span class="flex size-10 items-center justify-center rounded-xl bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-400">
                    <MessageCircle :size="22" aria-hidden="true" />
                </span>
            </div>
            <div class="mt-3">
                <span class="font-display text-xl font-extrabold text-ink">
                    Otomatis & Real-time
                </span>
                <p class="mt-1 text-sm text-ink-soft">
                    Rincian nota, titip uang & sisa bersih terlampir
                </p>
            </div>
        </div>
    </div>

    <!-- Search & Filter Tab Controls -->
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <!-- Tabs -->
        <div class="flex flex-wrap gap-1 rounded-2xl border-2 border-line bg-surface p-1">
            <button
                type="button"
                :class="[
                    'pressable rounded-xl px-4 py-2 text-base font-bold transition-all',
                    currentTab === 'all' ? 'bg-brand text-white shadow-sm' : 'text-ink-soft hover:text-ink'
                ]"
                @click="setTab('all')"
            >
                Semua
            </button>
            <button
                type="button"
                :class="[
                    'pressable rounded-xl px-4 py-2 text-base font-bold transition-all',
                    currentTab === 'has_deposit' ? 'bg-brand text-white shadow-sm' : 'text-ink-soft hover:text-ink'
                ]"
                @click="setTab('has_deposit')"
            >
                Ada Titip Uang ({{ stats.count_deposit_customers }})
            </button>
            <button
                type="button"
                :class="[
                    'pressable rounded-xl px-4 py-2 text-base font-bold transition-all',
                    currentTab === 'has_debt' ? 'bg-brand text-white shadow-sm' : 'text-ink-soft hover:text-ink'
                ]"
                @click="setTab('has_debt')"
            >
                Masih Berutang
            </button>
            <button
                type="button"
                :class="[
                    'pressable rounded-xl px-4 py-2 text-base font-bold transition-all',
                    currentTab === 'overdue' ? 'bg-brand text-white shadow-sm' : 'text-ink-soft hover:text-ink'
                ]"
                @click="setTab('overdue')"
            >
                Jatuh Tempo
            </button>
        </div>

        <!-- Search Input -->
        <label class="flex min-h-touch items-center gap-3 rounded-2xl border-2 border-line bg-surface px-4 focus-within:border-focus sm:w-80">
            <Search :size="22" class="shrink-0 text-ink-soft" aria-hidden="true" />
            <input
                v-model="search"
                type="search"
                placeholder="Cari nama / no. HP…"
                class="min-w-0 flex-1 bg-transparent text-base text-ink focus:outline-none"
                @input="onSearch"
            />
        </label>
    </div>

    <!-- Empty State -->
    <EmptyState
        v-if="!customers.length"
        title="Belum ada data pelanggan"
        message="Tidak ada pelanggan yang sesuai dengan filter. Tambah transaksi kasbon atau catat titip uang baru."
    >
        <template #icon>
            <HandCoins :size="48" aria-hidden="true" />
        </template>
        <template #action>
            <BigButton variant="primary" @click="openDeposit()">Catat Titip Uang</BigButton>
        </template>
    </EmptyState>

    <!-- Customer Cards List -->
    <div v-else class="flex flex-col gap-4">
        <div
            v-for="c in customers"
            :key="c.id"
            class="card flex flex-col gap-4 p-5 hover:border-line-active transition-all"
        >
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="font-display text-xl font-extrabold text-ink">{{ c.name }}</h3>
                        <span
                            v-if="c.deposit_balance > 0"
                            class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300"
                        >
                            <HandCoins :size="13" aria-hidden="true" />
                            Ada Titip Uang
                        </span>
                        <span
                            v-if="c.is_overdue"
                            class="inline-flex items-center gap-1 rounded-full bg-danger-soft px-2.5 py-0.5 text-xs font-bold text-danger-ink"
                        >
                            <Clock :size="13" aria-hidden="true" />
                            Jatuh Tempo
                        </span>
                    </div>
                    <p class="text-sm text-ink-soft">
                        {{ c.formatted_phone || 'Tanpa No. HP' }}
                        <template v-if="c.nearest_due"> · Tempo terdekat: {{ c.nearest_due }}</template>
                    </p>
                </div>

                <!-- WhatsApp & Public Link Buttons -->
                <div class="flex flex-wrap items-center gap-2">
                    <a
                        v-if="c.wa_billing_url"
                        :href="c.wa_billing_url"
                        target="_blank"
                        rel="noopener"
                        class="pressable inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-3.5 py-2 text-sm font-bold text-white shadow-sm hover:bg-emerald-700"
                        title="Buka WhatsApp untuk kirim rincian tagihan"
                    >
                        <MessageCircle :size="18" aria-hidden="true" />
                        Kirim Tagihan WA
                    </a>
                    <button
                        type="button"
                        class="pressable inline-flex items-center gap-1.5 rounded-xl border border-line bg-surface px-3 py-2 text-sm font-bold text-ink hover:bg-surface-2"
                        @click="copyLink(c)"
                        title="Salin tautan tagihan resmi"
                    >
                        <Copy :size="16" aria-hidden="true" />
                        {{ copiedCustomerId === c.id ? 'Tersalin!' : 'Salin Link' }}
                    </button>
                    <a
                        :href="c.bill_url"
                        target="_blank"
                        rel="noopener"
                        class="pressable inline-flex items-center gap-1.5 rounded-xl border border-line bg-surface px-3 py-2 text-sm font-bold text-ink hover:bg-surface-2"
                        title="Buka tampilan tagihan pelanggan"
                    >
                        <ExternalLink :size="16" aria-hidden="true" />
                        Buka Tagihan
                    </a>
                </div>
            </div>

            <!-- Financial Breakdown Grid -->
            <div class="grid gap-3 rounded-2xl bg-surface-2 p-4 sm:grid-cols-3">
                <!-- Saldo Titip Uang -->
                <div>
                    <span class="block text-xs font-bold uppercase tracking-wider text-ink-soft">
                        Saldo Titip Uang (Deposit)
                    </span>
                    <span
                        class="block font-display text-2xl font-black tabular-nums"
                        :class="c.deposit_balance > 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-ink-soft'"
                    >
                        {{ formatRupiah(c.deposit_balance) }}
                    </span>
                </div>

                <!-- Utang Nota Kotor -->
                <div>
                    <span class="block text-xs font-bold uppercase tracking-wider text-ink-soft">
                        Total Utang Nota ({{ c.open_invoices }} nota)
                    </span>
                    <span class="block font-display text-2xl font-extrabold text-ink tabular-nums">
                        {{ formatRupiah(c.gross_debt) }}
                    </span>
                </div>

                <!-- Sisa Kewajiban Bersih -->
                <div>
                    <span class="block text-xs font-bold uppercase tracking-wider text-ink-soft">
                        Sisa Kewajiban Bersih
                    </span>
                    <span
                        class="block font-display text-2xl font-black tabular-nums"
                        :class="c.net_debt > 0 ? 'text-danger-ink' : 'text-emerald-700 dark:text-emerald-400'"
                    >
                        {{ formatRupiah(c.net_debt) }}
                    </span>
                    <span v-if="c.surplus > 0" class="block text-xs font-bold text-emerald-700 dark:text-emerald-400">
                        (Surplus saldo deposit: +{{ formatRupiah(c.surplus) }})
                    </span>
                </div>
            </div>

            <!-- Quick Action Row -->
            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-line pt-3">
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        class="pressable inline-flex items-center gap-1.5 rounded-xl bg-primary-soft px-3 py-1.5 text-sm font-bold text-primary-ink hover:opacity-90"
                        @click="openDeposit(c)"
                    >
                        <Plus :size="16" aria-hidden="true" />
                        Tambah Titip Uang
                    </button>
                    <button
                        v-if="c.deposit_balance > 0 && c.gross_debt > 0"
                        type="button"
                        class="pressable inline-flex items-center gap-1.5 rounded-xl bg-amber-100 px-3 py-1.5 text-sm font-bold text-amber-800 dark:bg-amber-950/60 dark:text-amber-300"
                        @click="openDeduct(c)"
                    >
                        <Receipt :size="16" aria-hidden="true" />
                        Potong Utang Nota
                    </button>
                    <button
                        v-if="c.deposit_balance > 0"
                        type="button"
                        class="pressable inline-flex items-center gap-1.5 rounded-xl border border-line bg-surface px-3 py-1.5 text-sm font-bold text-ink hover:bg-surface-2"
                        @click="openWithdraw(c)"
                    >
                        <MinusCircle :size="16" aria-hidden="true" />
                        Tarik Saldo
                    </button>
                </div>

                <button
                    type="button"
                    class="pressable inline-flex items-center gap-1.5 text-sm font-bold text-info-ink underline"
                    @click="openHistory(c)"
                >
                    <History :size="16" aria-hidden="true" />
                    Riwayat Mutasi ({{ c.recent_deposits.length }}+)
                </button>
            </div>
        </div>
    </div>

    <!-- BOTTOM SHEET 1: TAMBAH TITIP UANG -->
    <BottomSheet v-model:open="depositSheetOpen" title="Catat Titip Uang Pelanggan">
        <form class="flex flex-col gap-4" @submit.prevent="submitDeposit">
            <p class="text-base text-ink-soft">
                Pelanggan mengirim atau menyetorkan uang sebelum jatuh tempo / berlebih untuk disimpan di gudang.
            </p>

            <label class="block">
                <span class="mb-2 block text-lg font-bold text-ink">Pelanggan</span>
                <select
                    v-model="depositForm.customer_id"
                    class="min-h-touch w-full rounded-2xl border-2 border-line bg-surface px-4 text-lg text-ink"
                >
                    <option :value="null" disabled>Pilih pelanggan…</option>
                    <option v-for="c in allCustomers" :key="c.id" :value="c.id">
                        {{ c.name }} (Saldo saat ini: {{ formatRupiah(c.deposit_balance) }})
                    </option>
                </select>
                <span v-if="depositForm.errors.customer_id" class="mt-2 block text-base font-bold text-danger-ink">
                    {{ depositForm.errors.customer_id }}
                </span>
            </label>

            <MoneyInput
                v-model="depositForm.amount"
                label="Nominal titip uang"
                placeholder="Contoh: 500.000"
                :error="depositForm.errors.amount"
            />

            <BigInput
                v-model="depositForm.notes"
                label="Catatan / Keterangan"
                optional
                placeholder="Contoh: Titipan transfer BCA untuk pakan minggu depan"
                :error="depositForm.errors.notes"
            />

            <BigButton type="submit" block size="large" :loading="depositForm.processing">
                Simpan Titip Uang
            </BigButton>
        </form>
    </BottomSheet>

    <!-- BOTTOM SHEET 2: POTONG UTANG DARI DEPOSIT -->
    <BottomSheet v-model:open="deductSheetOpen" title="Potong Utang Nota dari Saldo Deposit">
        <form v-if="selectedCustomer" class="flex flex-col gap-4" @submit.prevent="submitDeduct">
            <div class="rounded-2xl bg-surface-2 p-4">
                <p class="text-base font-bold text-ink">{{ selectedCustomer.name }}</p>
                <div class="mt-2 grid grid-cols-2 gap-2 text-sm">
                    <div>
                        <span class="text-ink-soft">Saldo Deposit:</span>
                        <span class="block font-bold text-emerald-700 dark:text-emerald-400">
                            {{ formatRupiah(selectedCustomer.deposit_balance) }}
                        </span>
                    </div>
                    <div>
                        <span class="text-ink-soft">Total Utang Nota:</span>
                        <span class="block font-bold text-danger-ink">
                            {{ formatRupiah(selectedCustomer.gross_debt) }}
                        </span>
                    </div>
                </div>
            </div>

            <MoneyInput
                v-model="deductForm.amount"
                label="Jumlah yang dipotongkan ke nota"
                :hint="`Maksimal ${formatRupiah(Math.min(selectedCustomer.deposit_balance, selectedCustomer.gross_debt))}`"
                :error="deductForm.errors.amount"
            />

            <BigInput
                v-model="deductForm.notes"
                label="Catatan"
                optional
                placeholder="Contoh: Pelunasan otomatis nota pakan ayam"
                :error="deductForm.errors.notes"
            />

            <BigButton type="submit" block size="large" :loading="deductForm.processing">
                Terapkan Pemotongan Utang
            </BigButton>
        </form>
    </BottomSheet>

    <!-- BOTTOM SHEET 3: TARIK SALDO DEPOSIT -->
    <BottomSheet v-model:open="withdrawSheetOpen" title="Tarik Saldo Titip Uang">
        <form v-if="selectedCustomer" class="flex flex-col gap-4" @submit.prevent="submitWithdraw">
            <p class="text-base text-ink-soft">
                Pengembalian dana titip uang kepada pelanggan {{ selectedCustomer.name }}.
            </p>

            <MoneyInput
                v-model="withdrawForm.amount"
                label="Nominal penarikan"
                :hint="`Saldo tersedia ${formatRupiah(selectedCustomer.deposit_balance)}`"
                :error="withdrawForm.errors.amount"
            />

            <BigInput
                v-model="withdrawForm.notes"
                label="Alasan / Keterangan"
                optional
                placeholder="Contoh: Penarikan sisa lebih transfer"
                :error="withdrawForm.errors.notes"
            />

            <BigButton type="submit" block size="large" variant="danger" :loading="withdrawForm.processing">
                Tarik Saldo
            </BigButton>
        </form>
    </BottomSheet>

    <!-- BOTTOM SHEET 4: RIWAYAT MUTASI -->
    <BottomSheet v-model:open="historySheetOpen" :title="`Riwayat Mutasi: ${selectedCustomer?.name ?? ''}`">
        <div v-if="historyLoading" class="flex items-center justify-center p-8">
            <span class="text-base font-bold text-ink-soft">Memuat riwayat…</span>
        </div>

        <div v-else-if="!customerHistory.deposits.length" class="p-6 text-center text-ink-soft">
            Belum ada riwayat mutasi titip uang.
        </div>

        <div v-else class="flex flex-col gap-3">
            <div class="rounded-2xl bg-emerald-500/10 p-4">
                <span class="text-sm font-bold text-emerald-800 dark:text-emerald-300">Saldo Deposit Saat Ini</span>
                <span class="block font-display text-2xl font-black text-emerald-800 dark:text-emerald-300 tabular-nums">
                    {{ formatRupiah(customerHistory.customer.deposit_balance) }}
                </span>
            </div>

            <ul class="divide-y divide-line rounded-2xl border border-line bg-surface">
                <li
                    v-for="item in customerHistory.deposits"
                    :key="item.id"
                    class="flex items-center justify-between gap-3 px-4 py-3"
                >
                    <div class="min-w-0">
                        <span class="flex items-center gap-1.5 text-base font-bold text-ink">
                            <ArrowDownRight
                                v-if="item.type === 'deposit' || item.type === 'overpayment'"
                                :size="18"
                                class="text-emerald-600"
                            />
                            <ArrowUpRight v-else :size="18" class="text-danger-ink" />
                            {{ item.type_label }}
                        </span>
                        <span class="block text-xs text-ink-soft">
                            {{ item.date }} · Oleh: {{ item.created_by || 'Sistem' }}
                        </span>
                        <span v-if="item.notes" class="block text-xs text-ink">
                            {{ item.notes }}
                        </span>
                    </div>

                    <div class="shrink-0 text-right">
                        <span
                            class="block font-display text-base font-extrabold tabular-nums"
                            :class="item.type === 'deposit' || item.type === 'overpayment' ? 'text-emerald-700 dark:text-emerald-400' : 'text-danger-ink'"
                        >
                            {{ item.type === 'deposit' || item.type === 'overpayment' ? '+' : '-' }}{{ formatRupiah(item.amount) }}
                        </span>
                        <span class="block text-xs text-ink-soft">
                            Sisa {{ formatRupiah(item.balance_after) }}
                        </span>
                    </div>
                </li>
            </ul>
        </div>
    </BottomSheet>
</template>
