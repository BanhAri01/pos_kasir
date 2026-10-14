<script setup>
/**
 * Pembayaran: total besar, pilih cara bayar, tombol uang cepat, kembalian otomatis.
 * Bisa dibayar dengan beberapa cara sekaligus (sebagian tunai, sebagian QRIS).
 */
import { computed, onUnmounted, ref, watch } from 'vue';
import { Banknote, CheckCircle2, CreditCard, Landmark, Plus, QrCode, RefreshCw, Smartphone, Trash2, X } from 'lucide-vue-next';
import BigButton from '@/Components/ui/BigButton.vue';
import BottomSheet from '@/Components/ui/BottomSheet.vue';
import MoneyDisplay from '@/Components/ui/MoneyDisplay.vue';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import { formatRupiah } from '@/composables/useRupiah';
import { canPayLater, hasModule, store, submitSale, totals } from '../store';
import ToggleSwitch from '@/Components/ui/ToggleSwitch.vue';
import { quickCashOptions } from '../lib/calculator';
import { showToast } from '../lib/toast';
import { api } from '../lib/api';

const open = defineModel('open', { type: Boolean, default: false });
const emit = defineEmits(['paid', 'customer']);

const icons = { cash: Banknote, qris: QrCode, transfer: Landmark, ewallet: Smartphone, card: CreditCard };

const payments = ref([]); // [{ method, amount }]
const saving = ref(false);
const payLater = ref(false); // kasbon / tempo / bayar saat ambil / DP
const dueDate = ref('');
const sendWhatsapp = ref(false);

// State QRIS Dinamis
const showQrisModal = ref(false);
const generatingQris = ref(false);
const checkingQris = ref(false);
const qrisData = ref(null);
let qrisPollTimer = null;

const total = computed(() => totals.value.total);
const methods = computed(() => store.boot.payment_methods);
const paidSum = computed(() => payments.value.reduce((s, p) => s + (p.amount ?? 0), 0));
const remaining = computed(() => Math.max(0, total.value - paidSum.value));
const change = computed(() => Math.max(0, paidSum.value - total.value));
const cashSum = computed(() => payments.value.filter((p) => p.method.type === 'cash').reduce((s, p) => s + (p.amount ?? 0), 0));
const changeValid = computed(() => change.value <= cashSum.value);
const ready = computed(() =>
    payLater.value ? !!store.customer && changeValid.value : payments.value.length > 0 && remaining.value === 0 && changeValid.value,
);
const canSendWhatsapp = computed(() => hasModule('whatsapp_notification') && !!store.customer?.phone);

/** Kasbon: cek batas utang pelanggan (kalau diatur). */
const overLimit = computed(() => {
    const c = store.boot.customers.find((x) => x.uuid === store.customer?.uuid);
    if (!payLater.value || !c?.credit_limit) return false;
    return (c.balance ?? 0) + remaining.value > c.credit_limit;
});
const active = computed(() => payments.value.at(-1));

watch(open, (value) => {
    if (value) {
        const cash = methods.value.find((m) => m.type === 'cash') ?? methods.value[0];
        payments.value = [{ method: cash, amount: null }];
        payLater.value = false;
        dueDate.value = '';
        sendWhatsapp.value = canSendWhatsapp.value;
    } else {
        closeQrisModal();
    }
});

onUnmounted(() => {
    closeQrisModal();
});

async function generateDynamicQris() {
    const amt = active.value?.amount || remaining.value || total.value;
    if (amt <= 0) {
        showToast('Nominal QRIS harus lebih dari Rp 0', 'error');
        return;
    }

    generatingQris.value = true;
    try {
        const res = await api.post('/kasir/api/qris/generate', { amount: amt });
        qrisData.value = res;
        showQrisModal.value = true;
        startQrisPolling(res.order_id);
    } catch (e) {
        showToast(e.message, 'error');
    } finally {
        generatingQris.value = false;
    }
}

function startQrisPolling(orderId) {
    stopQrisPolling();
    qrisPollTimer = setInterval(async () => {
        await checkQrisStatus(orderId, false);
    }, 2500);
}

function stopQrisPolling() {
    if (qrisPollTimer) {
        clearInterval(qrisPollTimer);
        qrisPollTimer = null;
    }
}

async function checkQrisStatus(orderId, manual = true) {
    if (checkingQris.value) return;
    if (manual) checkingQris.value = true;

    try {
        const res = await api.get(`/kasir/api/qris/check/${orderId}`);
        if (res.status === 'paid') {
            stopQrisPolling();
            showToast('Pembayaran QRIS Berhasil Diterima!', 'success');
            if (active.value) {
                active.value.amount = qrisData.value?.amount ?? active.value.amount;
            }
            showQrisModal.value = false;
            pay();
        } else if (manual) {
            showToast('Pembayaran belum diterima. Menunggu pelanggan scan...', 'info');
        }
    } catch {
        // Silently continue polling on minor network glitch
    } finally {
        if (manual) checkingQris.value = false;
    }
}

function closeQrisModal() {
    stopQrisPolling();
    showQrisModal.value = false;
    qrisData.value = null;
}

async function simulateQrisPayment(orderId) {
    if (!orderId) return;
    try {
        await api.post(`/kasir/api/qris/simulate/${orderId}`);
        await checkQrisStatus(orderId, false);
    } catch (e) {
        showToast(e.message, 'error');
    }
}


function chooseMethod(method) {
    const current = active.value;
    current.method = method;
    // Non-tunai biasanya pas: langsung isi sisa yang harus dibayar.
    if (method.type !== 'cash') current.amount = Math.max(0, total.value - (paidSum.value - (current.amount ?? 0)));
}

function addPayment() {
    const other = methods.value.find((m) => m.id !== active.value.method.id) ?? methods.value[0];
    payments.value.push({ method: other, amount: other.type === 'cash' ? null : remaining.value });
}

function removePayment(index) {
    payments.value.splice(index, 1);
}

const quick = computed(() => {
    const before = paidSum.value - (active.value?.amount ?? 0);
    return quickCashOptions(Math.max(0, total.value - before));
});

async function pay() {
    if (!ready.value) return;
    saving.value = true;
    try {
        const paid = payments.value.filter((p) => (p.amount ?? 0) > 0).map((p) => ({ payment_method_id: p.method.id, amount: p.amount ?? 0 }));
        const sale = await submitSale(paid, { payLater: payLater.value && remaining.value > 0, dueDate: dueDate.value || null, sendWhatsapp: sendWhatsapp.value && canSendWhatsapp.value });
        open.value = false;
        emit('paid', sale);
    } catch (e) {
        showToast(e.message, 'error');
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <BottomSheet v-model:open="open" title="Pembayaran" :persistent="saving">
        <div class="flex flex-col gap-5">
            <div class="rounded-3xl bg-surface-2 p-5 text-center">
                <MoneyDisplay :amount="total" size="xl" label="Total belanja" />
            </div>

            <div v-for="(payment, index) in payments" :key="index" class="flex flex-col gap-3" :class="index < payments.length - 1 ? 'rounded-2xl border-2 border-line p-3' : ''">
                <template v-if="index < payments.length - 1">
                    <div class="flex items-center justify-between">
                        <p class="text-lg font-bold text-ink">{{ payment.method.name }}: {{ formatRupiah(payment.amount ?? 0) }}</p>
                        <button type="button" class="flex size-11 items-center justify-center rounded-xl text-danger-ink hover:bg-danger-soft" aria-label="Hapus cara bayar ini" @click="removePayment(index)">
                            <Trash2 :size="22" aria-hidden="true" />
                        </button>
                    </div>
                </template>
                <template v-else>
                    <div>
                        <p class="mb-2 text-lg font-bold text-ink">{{ payments.length > 1 ? 'Sisanya dibayar dengan' : 'Cara bayar' }}</p>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            <button
                                v-for="m in methods"
                                :key="m.id"
                                type="button"
                                class="pressable flex min-h-touch-lg flex-col items-center justify-center gap-1 rounded-2xl border-2 text-base font-bold"
                                :class="payment.method.id === m.id ? 'border-primary bg-primary-soft text-primary-ink' : 'border-line text-ink'"
                                :aria-pressed="payment.method.id === m.id"
                                @click="chooseMethod(m)"
                            >
                                <component :is="icons[m.type] ?? CreditCard" :size="28" aria-hidden="true" />
                                {{ m.name }}
                            </button>
                        </div>
                    </div>

                    <template v-if="payment.method.type === 'cash'">
                        <div>
                            <p class="mb-2 text-lg font-bold text-ink">Uang yang diterima</p>
                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                <button
                                    v-for="(q, i) in quick"
                                    :key="q"
                                    type="button"
                                    class="pressable min-h-touch rounded-2xl border-2 font-display text-lg font-extrabold tabular-nums"
                                    :class="payment.amount === q ? 'border-primary bg-primary text-on-primary' : 'border-line text-ink'"
                                    @click="payment.amount = q"
                                >
                                    {{ i === 0 ? 'Uang Pas' : formatRupiah(q) }}
                                </button>
                            </div>
                        </div>
                        <MoneyInput v-model="payment.amount" label="Atau ketik jumlahnya" />
                    </template>
                    <template v-else-if="payment.method.type === 'qris'">
                        <MoneyInput v-model="payment.amount" :label="`Jumlah dibayar lewat ${payment.method.name}`" />
                        <button
                            type="button"
                            class="pressable mt-2 flex min-h-touch w-full items-center justify-center gap-2 rounded-2xl border-2 border-primary bg-primary-soft text-base font-bold text-primary-ink hover:bg-primary/20"
                            :disabled="generatingQris || (payment.amount ?? remaining) <= 0"
                            @click="generateDynamicQris"
                        >
                            <QrCode :size="22" />
                            {{ generatingQris ? 'Membuat QRIS...' : 'Tampilkan QRIS Dinamis (Auto Cek Bayar)' }}
                        </button>
                    </template>
                    <MoneyInput v-else v-model="payment.amount" :label="`Jumlah dibayar lewat ${payment.method.name}`" />
                </template>
            </div>

            <button
                v-if="remaining > 0 && paidSum > 0 && payments.length < 4"
                type="button"
                class="pressable flex min-h-touch items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-line text-lg font-bold text-ink"
                @click="addPayment"
            >
                <Plus :size="22" aria-hidden="true" /> Bayar sisanya dengan cara lain
            </button>

            <!-- Kembalian / kurang -->
            <div v-if="paidSum > 0" class="rounded-3xl p-5 text-center" :class="remaining > 0 || !changeValid ? 'bg-warn-soft' : 'bg-primary-soft'">
                <template v-if="remaining > 0">
                    <p class="text-lg font-bold text-warn-ink">Masih kurang</p>
                    <p class="font-display text-4xl font-extrabold text-warn-ink tabular-nums">{{ formatRupiah(remaining) }}</p>
                </template>
                <template v-else-if="!changeValid">
                    <p class="text-lg font-bold text-warn-ink">Pembayaran non-tunai melebihi total. Kembalian hanya bisa dari uang tunai.</p>
                </template>
                <template v-else>
                    <p class="text-lg font-bold text-primary-ink">Kembalian</p>
                    <p class="font-display text-[clamp(2.25rem,12vw,3rem)] leading-tight font-extrabold text-primary-ink tabular-nums">{{ formatRupiah(change) }}</p>
                </template>
            </div>

            <!-- Bayar nanti / sebagian (kasbon, tempo, DP, laundry bayar saat ambil) -->
            <div v-if="canPayLater()" class="rounded-2xl border-2 p-4" :class="payLater ? 'border-accent bg-accent-soft' : 'border-line'">
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                    <div class="min-w-48 flex-1">
                        <p class="text-lg font-extrabold text-ink">Bayar nanti / sebagian</p>
                        <p class="text-base text-ink-soft">Sisanya dicatat sebagai utang pelanggan (kasbon / tempo / uang muka).</p>
                    </div>
                    <ToggleSwitch v-model="payLater" label="Bayar nanti" class="ml-auto" />
                </div>
                <template v-if="payLater">
                    <button
                        v-if="!store.customer"
                        type="button"
                        class="pressable mt-3 flex min-h-touch w-full items-center justify-center rounded-2xl bg-surface text-lg font-bold text-danger-ink"
                        @click="emit('customer')"
                    >
                        Pilih pelanggan dulu (wajib untuk catat utang)
                    </button>
                    <p v-else class="mt-3 text-lg text-ink">
                        Utang atas nama <strong>{{ store.customer.name }}</strong>: <strong>{{ formatRupiah(remaining) }}</strong>
                    </p>
                    <p v-if="overLimit" class="mt-2 font-bold text-danger-ink">Melebihi batas kasbon pelanggan ini. Minta bayar sebagian dulu.</p>
                    <label v-if="hasModule('credit_sales')" class="mt-3 block">
                        <span class="mb-1 block text-lg font-bold text-ink">Jatuh tempo <span class="font-semibold text-ink-soft">(boleh dikosongkan)</span></span>
                        <input v-model="dueDate" type="date" class="min-h-touch w-full rounded-2xl border-2 border-line bg-surface px-4 text-lg text-ink focus:border-focus focus:outline-none" />
                    </label>
                </template>
            </div>

            <div v-if="canSendWhatsapp" class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-2xl border-2 border-line p-4">
                <span class="min-w-48 flex-1 text-lg text-ink">Kirim struk ke WhatsApp {{ store.customer.name }}</span>
                <ToggleSwitch v-model="sendWhatsapp" label="Kirim struk WhatsApp" class="ml-auto" />
            </div>

            <BigButton size="large" block :disabled="!ready || overLimit" :loading="saving" @click="pay">
                {{ ready ? (payLater && remaining > 0 ? 'Simpan (Sisa Jadi Utang)' : 'Selesai, Simpan Pembayaran') : payLater ? 'Pilih pelanggan dulu' : 'Pilih uang yang diterima' }}
            </BigButton>
        </div>

        <!-- Modal Tampilan QRIS Dinamis -->
        <div
            v-if="showQrisModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
        >
            <div class="flex w-full max-w-sm flex-col items-center rounded-3xl bg-surface p-6 text-center shadow-2xl">
                <div class="mb-2 flex w-full items-center justify-between">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-primary-soft px-3 py-1 text-xs font-bold text-primary-ink">
                        <QrCode :size="14" /> QRIS Otomatis
                    </span>
                    <button
                        type="button"
                        class="flex size-9 items-center justify-center rounded-xl text-ink-soft hover:bg-ink/5"
                        aria-label="Tutup QRIS"
                        @click="closeQrisModal"
                    >
                        <X :size="20" />
                    </button>
                </div>

                <h3 class="text-xl font-extrabold text-ink">Scan untuk Membayar</h3>
                <p class="mt-1 font-display text-3xl font-extrabold text-primary-ink">
                    {{ formatRupiah(qrisData?.amount ?? 0) }}
                </p>

                <!-- QR Display -->
                <div class="my-4 flex size-56 items-center justify-center rounded-2xl border-2 border-line bg-white p-3 shadow-inner">
                    <img
                        v-if="qrisData?.qr_url"
                        :src="qrisData.qr_url"
                        alt="QRIS Code"
                        class="size-full object-contain"
                    />
                    <div v-else class="flex flex-col items-center justify-center p-2 text-ink">
                        <QrCode :size="90" class="mb-2 text-primary" />
                        <span class="break-all font-mono text-xs font-bold">{{ qrisData?.order_id }}</span>
                    </div>
                </div>

                <!-- Polling Status Badge -->
                <div class="flex items-center gap-2 text-sm font-bold text-ink-soft">
                    <span class="size-2.5 animate-ping rounded-full bg-primary" />
                    <span>Menunggu pembayaran pelanggan...</span>
                </div>

                <div class="mt-5 flex w-full flex-col gap-2">
                    <button
                        v-if="qrisData?.is_mock"
                        type="button"
                        class="pressable flex min-h-12 w-full items-center justify-center gap-2 rounded-xl border-2 border-dashed border-primary bg-primary-soft text-sm font-bold text-primary-ink hover:bg-primary/20"
                        @click="simulateQrisPayment(qrisData?.order_id)"
                    >
                        <CheckCircle2 :size="18" /> Simulasikan Pembeli Bayar (Testing)
                    </button>
                    <button
                        type="button"
                        class="pressable flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-primary text-base font-bold text-on-primary hover:opacity-90"
                        :disabled="checkingQris"
                        @click="checkQrisStatus(qrisData?.order_id, true)"
                    >

                        <RefreshCw :size="18" :class="{ 'animate-spin': checkingQris }" />
                        {{ checkingQris ? 'Mengecek...' : 'Cek Status Sekarang' }}
                    </button>
                    <button
                        type="button"
                        class="pressable flex min-h-11 w-full items-center justify-center rounded-xl text-base font-bold text-ink-soft hover:bg-ink/5"
                        @click="closeQrisModal"
                    >
                        Tutup & Kembali
                    </button>
                </div>
            </div>
        </div>
    </BottomSheet>
</template>
