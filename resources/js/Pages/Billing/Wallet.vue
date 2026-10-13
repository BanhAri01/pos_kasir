<script setup>
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatRupiah } from '@/composables/useRupiah';
import { 
    Wallet, ArrowDownLeft, ArrowUpRight, Building2, 
    CreditCard, CheckCircle2, Clock, XCircle, AlertCircle, 
    PlusCircle, Landmark, ShieldCheck 
} from 'lucide-vue-next';

const props = defineProps({
    wallet: { type: Object, required: true },
    mutations: { type: Array, default: () => [] },
    withdrawals: { type: Array, default: () => [] },
});

const activeTab = ref('mutations');
const showBankModal = ref(false);
const showWithdrawModal = ref(false);

const bankForm = useForm({
    bank_name: props.wallet.bank_name || 'BCA',
    bank_account_number: props.wallet.bank_account_number || '',
    bank_account_holder: props.wallet.bank_account_holder || '',
});

const withdrawForm = useForm({
    amount: null,
    notes: '',
});

function submitBank() {
    bankForm.post(route('wallet.bank'), {
        preserveScroll: true,
        onSuccess: () => {
            showBankModal.value = false;
        },
    });
}

function submitWithdraw() {
    withdrawForm.post(route('wallet.withdraw'), {
        preserveScroll: true,
        onSuccess: () => {
            showWithdrawModal.value = false;
            withdrawForm.reset();
        },
    });
}

function setQuickAmount(amt) {
    withdrawForm.amount = Math.min(amt, props.wallet.balance);
}
</script>

<template>
    <AppLayout title="Dompet & Tarik Saldo">
        <Head title="Dompet & Tarik Saldo" />

        <div class="mx-auto max-w-6xl space-y-6 p-4 sm:p-6">
            <!-- Header -->
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-ink sm:text-3xl">Dompet & Tarik Saldo</h1>
                    <p class="text-base text-ink-soft">
                        Pendapatan transaksi non-tunai (QRIS Midtrans) otomatis masuk ke dompet ini dan dapat dicairkan ke rekening bank Anda.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="showBankModal = true"
                        class="inline-flex items-center gap-2 rounded-xl border border-line bg-surface px-4 py-2.5 text-sm font-semibold text-ink shadow-sm hover:bg-surface-soft transition"
                    >
                        <Building2 class="h-4 w-4 text-ink-soft" />
                        {{ wallet.has_bank ? 'Ubah Rekening Bank' : 'Atur Rekening Bank' }}
                    </button>
                    <button
                        type="button"
                        :disabled="!wallet.has_bank || wallet.balance < 20000"
                        @click="showWithdrawModal = true"
                        class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-hover disabled:opacity-50 transition"
                    >
                        <ArrowUpRight class="h-4 w-4" />
                        Tarik Saldo
                    </button>
                </div>
            </div>

            <!-- Warning if bank not setup -->
            <div v-if="!wallet.has_bank" class="flex items-start gap-3 rounded-2xl border border-warn-line bg-warn-soft p-4 text-warn-ink">
                <AlertCircle class="h-5 w-5 shrink-0 mt-0.5" />
                <div class="text-sm">
                    <p class="font-bold">Rekening bank pencairan belum diatur.</p>
                    <p class="mt-0.5 text-warn-ink/80">Silakan masukkan rekening bank Anda agar saldo QRIS dapat ditarik kapan saja.</p>
                    <button type="button" @click="showBankModal = true" class="mt-2 text-sm font-bold underline">
                        Isi Rekening Sekarang &rarr;
                    </button>
                </div>
            </div>

            <!-- Stat Cards -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="relative overflow-hidden rounded-2xl border border-line bg-surface p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-ink-soft">Saldo Tersedia</span>
                        <div class="rounded-xl bg-primary-soft p-2.5 text-primary">
                            <Wallet class="h-5 w-5" />
                        </div>
                    </div>
                    <div class="mt-3 text-3xl font-extrabold text-ink">
                        {{ formatRupiah(wallet.balance) }}
                    </div>
                    <p class="mt-1 text-xs text-ink-soft">Dapat ditarik kapan saja (min. Rp 20.000)</p>
                </div>

                <div class="relative overflow-hidden rounded-2xl border border-line bg-surface p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-ink-soft">Total Pendapatan QRIS</span>
                        <div class="rounded-xl bg-emerald-500/10 p-2.5 text-emerald-600">
                            <ArrowDownLeft class="h-5 w-5" />
                        </div>
                    </div>
                    <div class="mt-3 text-3xl font-extrabold text-ink">
                        {{ formatRupiah(wallet.total_qris_income) }}
                    </div>
                    <p class="mt-1 text-xs text-ink-soft">Akumulasi seluruh pembayaran QRIS</p>
                </div>

                <div class="relative overflow-hidden rounded-2xl border border-line bg-surface p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-ink-soft">Sedang Diproses</span>
                        <div class="rounded-xl bg-amber-500/10 p-2.5 text-amber-600">
                            <Clock class="h-5 w-5" />
                        </div>
                    </div>
                    <div class="mt-3 text-3xl font-extrabold text-ink">
                        {{ formatRupiah(wallet.pending_withdrawal) }}
                    </div>
                    <p class="mt-1 text-xs text-ink-soft">Menunggu pencairan dana ke rekening</p>
                </div>
            </div>

            <!-- Rekening Bank Info -->
            <div v-if="wallet.has_bank" class="rounded-2xl border border-line bg-surface p-5 shadow-sm">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl bg-blue-500/10 p-3 text-blue-600">
                            <Landmark class="h-6 w-6" />
                        </div>
                        <div>
                            <div class="text-xs uppercase tracking-wider text-ink-soft font-semibold">Rekening Bank Pencairan</div>
                            <div class="text-lg font-bold text-ink">
                                {{ wallet.bank_name }} - {{ wallet.bank_account_number }}
                            </div>
                            <div class="text-sm text-ink-soft">a.n. {{ wallet.bank_account_holder }}</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 text-xs font-medium text-emerald-600 bg-emerald-50 border border-emerald-200 px-3 py-1.5 rounded-full w-fit">
                        <ShieldCheck class="h-4 w-4" /> Terdaftar & Siap Terima Dana
                    </div>
                </div>
            </div>

            <!-- Tabs: Mutasi vs Penarikan -->
            <div class="rounded-2xl border border-line bg-surface shadow-sm overflow-hidden">
                <div class="flex border-b border-line bg-surface-soft/50">
                    <button
                        type="button"
                        @click="activeTab = 'mutations'"
                        :class="[
                            'px-5 py-3.5 text-sm font-semibold border-b-2 transition -mb-px',
                            activeTab === 'mutations' ? 'border-primary text-primary bg-surface' : 'border-transparent text-ink-soft hover:text-ink'
                        ]"
                    >
                        Riwayat Mutasi Saldo
                    </button>
                    <button
                        type="button"
                        @click="activeTab = 'withdrawals'"
                        :class="[
                            'px-5 py-3.5 text-sm font-semibold border-b-2 transition -mb-px',
                            activeTab === 'withdrawals' ? 'border-primary text-primary bg-surface' : 'border-transparent text-ink-soft hover:text-ink'
                        ]"
                    >
                        Riwayat Penarikan Dana
                    </button>
                </div>

                <!-- Tab 1: Mutasi Saldo -->
                <div v-if="activeTab === 'mutations'" class="p-0">
                    <div v-if="mutations.length === 0" class="p-8 text-center text-ink-soft">
                        <Wallet class="h-10 w-10 mx-auto text-ink-soft/40 mb-2" />
                        <p class="font-medium">Belum ada mutasi saldo dompet.</p>
                        <p class="text-xs mt-1">Transaksi pembayaran QRIS dari kasir akan tercatat otomatis di sini.</p>
                    </div>
                    <div v-else class="divide-y divide-line overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-surface-soft/40 text-xs text-ink-soft uppercase font-semibold">
                                <tr>
                                    <th class="px-5 py-3">Tanggal</th>
                                    <th class="px-5 py-3">Keterangan</th>
                                    <th class="px-5 py-3 text-right">Nominal Kotor</th>
                                    <th class="px-5 py-3 text-right">Potongan MDR</th>
                                    <th class="px-5 py-3 text-right">Bersih Masuk/Keluar</th>
                                    <th class="px-5 py-3 text-right">Sisa Saldo</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                <tr v-for="m in mutations" :key="m.id" class="hover:bg-surface-soft/20">
                                    <td class="px-5 py-3.5 text-ink-soft whitespace-nowrap text-xs">{{ m.created_at }}</td>
                                    <td class="px-5 py-3.5">
                                        <div class="font-semibold text-ink">{{ m.description }}</div>
                                        <div class="text-xs text-ink-soft font-mono">{{ m.reference || '-' }}</div>
                                    </td>
                                    <td class="px-5 py-3.5 text-right font-medium text-ink">{{ formatRupiah(m.amount) }}</td>
                                    <td class="px-5 py-3.5 text-right text-xs text-warn-ink font-medium">
                                        {{ m.fee > 0 ? '-' + formatRupiah(m.fee) : '-' }}
                                    </td>
                                    <td :class="['px-5 py-3.5 text-right font-bold whitespace-nowrap', m.type === 'credit' ? 'text-emerald-600' : 'text-rose-600']">
                                        {{ m.type === 'credit' ? '+' : '-' }}{{ formatRupiah(m.net_amount) }}
                                    </td>
                                    <td class="px-5 py-3.5 text-right font-semibold text-ink whitespace-nowrap">
                                        {{ formatRupiah(m.balance_after) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tab 2: Riwayat Penarikan Dana -->
                <div v-if="activeTab === 'withdrawals'" class="p-0">
                    <div v-if="withdrawals.length === 0" class="p-8 text-center text-ink-soft">
                        <Clock class="h-10 w-10 mx-auto text-ink-soft/40 mb-2" />
                        <p class="font-medium">Belum ada pengajuan penarikan dana.</p>
                    </div>
                    <div v-else class="divide-y divide-line overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-surface-soft/40 text-xs text-ink-soft uppercase font-semibold">
                                <tr>
                                    <th class="px-5 py-3">Tanggal Pengajuan</th>
                                    <th class="px-5 py-3">Rekening Tujuan</th>
                                    <th class="px-5 py-3 text-right">Jumlah Tarik</th>
                                    <th class="px-5 py-3 text-center">Status</th>
                                    <th class="px-5 py-3">Keterangan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                <tr v-for="w in withdrawals" :key="w.id" class="hover:bg-surface-soft/20">
                                    <td class="px-5 py-3.5 text-ink-soft whitespace-nowrap text-xs">{{ w.created_at }}</td>
                                    <td class="px-5 py-3.5">
                                        <div class="font-semibold text-ink">{{ w.bank_name }} - {{ w.bank_account_number }}</div>
                                        <div class="text-xs text-ink-soft">a.n. {{ w.bank_account_holder }}</div>
                                    </td>
                                    <td class="px-5 py-3.5 text-right font-bold text-ink whitespace-nowrap">
                                        {{ formatRupiah(w.amount) }}
                                    </td>
                                    <td class="px-5 py-3.5 text-center whitespace-nowrap">
                                        <span v-if="w.status === 'pending'" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800">
                                            <Clock class="h-3 w-3" /> Diproses
                                        </span>
                                        <span v-else-if="w.status === 'approved'" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                                            <CheckCircle2 class="h-3 w-3" /> Ditransfer
                                        </span>
                                        <span v-else class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-800">
                                            <XCircle class="h-3 w-3" /> Ditolak
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5 text-xs text-ink-soft">
                                        <div v-if="w.rejection_reason" class="text-rose-600 font-medium">Alasan: {{ w.rejection_reason }}</div>
                                        <div v-else-if="w.processed_at" class="text-ink-soft">Selesai: {{ w.processed_at }}</div>
                                        <div v-else class="text-ink-soft">-</div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Atur Rekening Bank -->
        <div v-if="showBankModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
            <div class="w-full max-w-md rounded-2xl bg-surface p-6 shadow-xl border border-line space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-ink">Rekening Bank Pencairan</h3>
                    <button type="button" @click="showBankModal = false" class="text-ink-soft hover:text-ink">
                        <XCircle class="h-5 w-5" />
                    </button>
                </div>
                <p class="text-xs text-ink-soft">
                    Pastikan nomor rekening dan nama pemilik sesuai dengan buku tabungan Anda untuk menghindari kegagalan transfer.
                </p>

                <form @submit.prevent="submitBank" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold uppercase text-ink-soft mb-1">Nama Bank</label>
                        <select v-model="bankForm.bank_name" class="w-full rounded-xl border border-line bg-surface p-2.5 text-sm text-ink focus:border-primary focus:ring-1 focus:ring-primary">
                            <option value="BCA">BCA (Bank Central Asia)</option>
                            <option value="Mandiri">Bank Mandiri</option>
                            <option value="BRI">BRI (Bank Rakyat Indonesia)</option>
                            <option value="BNI">BNI (Bank Negara Indonesia)</option>
                            <option value="BSI">BSI (Bank Syariah Indonesia)</option>
                            <option value="CIMB">CIMB Niaga</option>
                            <option value="Permata">Bank Permata</option>
                            <option value="Jago">Bank Jago</option>
                            <option value="SeaBank">SeaBank</option>
                            <option value="Lainnya">Bank Lainnya</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase text-ink-soft mb-1">Nomor Rekening</label>
                        <input
                            v-model="bankForm.bank_account_number"
                            type="text"
                            placeholder="Contoh: 1234567890"
                            required
                            class="w-full rounded-xl border border-line bg-surface p-2.5 text-sm text-ink focus:border-primary focus:ring-1 focus:ring-primary"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase text-ink-soft mb-1">Nama Pemilik Rekening</label>
                        <input
                            v-model="bankForm.bank_account_holder"
                            type="text"
                            placeholder="Sesuai nama di buku tabungan"
                            required
                            class="w-full rounded-xl border border-line bg-surface p-2.5 text-sm text-ink focus:border-primary focus:ring-1 focus:ring-primary"
                        />
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button
                            type="button"
                            @click="showBankModal = false"
                            class="rounded-xl border border-line px-4 py-2 text-sm font-semibold text-ink hover:bg-surface-soft"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="bankForm.processing"
                            class="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-hover disabled:opacity-50"
                        >
                            Simpan Rekening
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Tarik Saldo -->
        <div v-if="showWithdrawModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
            <div class="w-full max-w-md rounded-2xl bg-surface p-6 shadow-xl border border-line space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-ink">Tarik Saldo Dompet</h3>
                    <button type="button" @click="showWithdrawModal = false" class="text-ink-soft hover:text-ink">
                        <XCircle class="h-5 w-5" />
                    </button>
                </div>

                <div class="rounded-xl bg-surface-soft p-3 text-xs text-ink-soft">
                    Dana akan ditransfer ke rekening:
                    <div class="mt-1 font-bold text-ink text-sm">
                        {{ wallet.bank_name }} - {{ wallet.bank_account_number }} ({{ wallet.bank_account_holder }})
                    </div>
                </div>

                <form @submit.prevent="submitWithdraw" class="space-y-4">
                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <label class="text-xs font-bold uppercase text-ink-soft">Jumlah Penarikan (Rp)</label>
                            <span class="text-xs text-ink-soft">Maks: {{ formatRupiah(wallet.balance) }}</span>
                        </div>
                        <input
                            v-model.number="withdrawForm.amount"
                            type="number"
                            min="20000"
                            :max="wallet.balance"
                            placeholder="Minimal Rp 20.000"
                            required
                            class="w-full rounded-xl border border-line bg-surface p-2.5 text-base font-bold text-ink focus:border-primary focus:ring-1 focus:ring-primary"
                        />
                        <div v-if="withdrawForm.errors.amount" class="text-xs text-rose-600 mt-1 font-medium">
                            {{ withdrawForm.errors.amount }}
                        </div>

                        <!-- Quick chips -->
                        <div class="flex flex-wrap gap-1.5 mt-2">
                            <button
                                v-for="amt in [50000, 100000, 250000, 500000]"
                                :key="amt"
                                type="button"
                                @click="setQuickAmount(amt)"
                                class="text-xs px-2.5 py-1 rounded-lg border border-line bg-surface hover:bg-surface-soft text-ink font-medium"
                            >
                                {{ formatRupiah(amt) }}
                            </button>
                            <button
                                type="button"
                                @click="setQuickAmount(wallet.balance)"
                                class="text-xs px-2.5 py-1 rounded-lg border border-primary/30 bg-primary-soft text-primary font-bold"
                            >
                                Tarik Semua
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase text-ink-soft mb-1">Catatan Tambahan (Opsional)</label>
                        <input
                            v-model="withdrawForm.notes"
                            type="text"
                            placeholder="Contoh: Tarik omset minggu pertama"
                            class="w-full rounded-xl border border-line bg-surface p-2.5 text-sm text-ink focus:border-primary focus:ring-1 focus:ring-primary"
                        />
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button
                            type="button"
                            @click="showWithdrawModal = false"
                            class="rounded-xl border border-line px-4 py-2 text-sm font-semibold text-ink hover:bg-surface-soft"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="withdrawForm.processing || !withdrawForm.amount || withdrawForm.amount < 20000"
                            class="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-hover disabled:opacity-50"
                        >
                            Kirim Pengajuan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
