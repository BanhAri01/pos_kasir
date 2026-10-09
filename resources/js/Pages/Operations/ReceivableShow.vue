<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { BellRing, CalendarClock, Check, Copy, ExternalLink, FileText, Link2, Wallet, X } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import BottomSheet from '@/Components/ui/BottomSheet.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import MoneyDisplay from '@/Components/ui/MoneyDisplay.vue';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { formatRupiah } from '@/composables/useRupiah';

defineOptions({ layout: AppLayout });

const props = defineProps({
    customer: { type: Object, required: true },
    balance: { type: Number, required: true },
    receivables: { type: Array, required: true },
    payments: { type: Array, required: true },
    paymentMethods: { type: Array, required: true },
    billing: { type: Object, required: true },
    proofs: { type: Array, required: true },
});

const pendingProofs = computed(() => props.proofs.filter((p) => p.status === 'pending'));
const doneProofs = computed(() => props.proofs.filter((p) => p.status !== 'pending'));
const dayForm = useForm({ billing_day: props.billing.day ?? '' });
const copied = ref(false);
const confirmReset = ref(false);
const approving = ref(null);
const rejecting = ref(null);
const approveForm = useForm({ amount: null, payment_method_id: props.paymentMethods.find((m) => !/tunai|cash/i.test(m.name))?.id ?? props.paymentMethods[0]?.id ?? null });
const rejectForm = useForm({ reason: '' });
const REASONS = ['Jumlah transfer tidak sesuai', 'Bukti tidak terbaca / buram', 'Dana belum masuk ke rekening'];

function saveDay() {
    dayForm.transform((d) => ({ billing_day: d.billing_day === '' ? null : Number(d.billing_day) })).put(route('receivables.billing-day', props.customer.id), { preserveScroll: true });
}

async function copyLink() {
    try {
        await navigator.clipboard.writeText(props.billing.link);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch {
        window.prompt('Salin link tagihan:', props.billing.link);
    }
}

function resetLink() {
    router.post(route('receivables.reset-link', props.customer.id), {}, { preserveScroll: true, onFinish: () => (confirmReset.value = false) });
}

function openApprove(proof) {
    approveForm.clearErrors();
    approveForm.amount = Math.min(proof.amount, props.balance);
    approving.value = proof;
}

function approve() {
    approveForm.post(route('receivables.proofs.approve', approving.value.id), { preserveScroll: true, onSuccess: () => (approving.value = null) });
}

function openReject(proof) {
    rejectForm.reset();
    rejectForm.clearErrors();
    rejecting.value = proof;
}

function reject() {
    rejectForm.post(route('receivables.proofs.reject', rejecting.value.id), { preserveScroll: true, onSuccess: () => (rejecting.value = null) });
}

const PROOF_STATUS = { approved: 'Diterima', rejected: 'Ditolak' };

const paying = ref(false);
const confirmRemind = ref(false);
const form = useForm({ amount: null, payment_method_id: props.paymentMethods[0]?.id ?? null });

function openPay() {
    form.amount = props.balance;
    paying.value = true;
}

function pay() {
    form.post(route('receivables.pay', props.customer.id), { preserveScroll: true, onSuccess: () => (paying.value = false) });
}

function remind() {
    router.post(route('receivables.remind', props.customer.id), {}, { preserveScroll: true, onFinish: () => (confirmRemind.value = false) });
}

const TYPE = { kasbon: 'Kasbon', tempo: 'Jual tempo' };
</script>

<template>
    <Head :title="`Kasbon ${customer.name}`" />
    <div class="mx-auto max-w-2xl">
        <PageHeader :title="customer.name" :subtitle="customer.phone || 'Tanpa no HP'" :back-href="route('receivables.index')" />

        <div class="card mb-4 flex flex-col gap-4 p-5">
            <MoneyDisplay :amount="balance" size="xl" :tone="balance > 0 ? 'negative' : 'positive'" label="Sisa utang" />
            <p v-if="customer.credit_limit" class="text-base text-ink-soft">Batas kasbon: {{ formatRupiah(customer.credit_limit) }}</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <BigButton size="large" :disabled="balance <= 0" @click="openPay"><Wallet :size="24" aria-hidden="true" /> Terima Bayar</BigButton>
                <BigButton size="large" variant="soft" :disabled="balance <= 0 || !customer.has_phone" @click="confirmRemind = true"><BellRing :size="24" aria-hidden="true" /> Kirim Tagihan WA</BigButton>
            </div>
            <p v-if="!customer.has_phone" class="text-base text-ink-soft">Tambahkan no HP pelanggan supaya bisa dikirimi tagihan.</p>
        </div>

        <template v-if="pendingProofs.length">
            <h2 class="mb-3 text-xl font-extrabold text-ink">Bukti bayar menunggu dicek ({{ pendingProofs.length }})</h2>
            <ul class="mb-6 flex flex-col gap-3">
                <li v-for="p in pendingProofs" :key="p.id" class="card flex flex-col gap-3 p-4 ring-2 ring-info-ink/30">
                    <a :href="p.file_url" target="_blank" rel="noopener" class="block overflow-hidden rounded-2xl bg-surface-2">
                        <span v-if="p.is_pdf" class="flex min-h-touch items-center justify-center gap-2 p-4 text-lg font-bold text-info-ink"><FileText :size="24" aria-hidden="true" /> Buka bukti (PDF)</span>
                        <img v-else :src="p.file_url" alt="Bukti pembayaran" loading="lazy" class="max-h-96 w-full object-contain" />
                    </a>
                    <div class="flex items-center justify-between gap-3">
                        <span class="min-w-0">
                            <span class="block text-base text-ink-soft">Dikirim {{ p.date }}</span>
                            <span v-if="p.note" class="block text-base text-ink">“{{ p.note }}”</span>
                        </span>
                        <span class="shrink-0 font-display text-2xl font-extrabold text-ink tabular-nums">{{ formatRupiah(p.amount) }}</span>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <BigButton variant="danger" @click="openReject(p)"><X :size="22" aria-hidden="true" /> Tolak</BigButton>
                        <BigButton @click="openApprove(p)"><Check :size="22" aria-hidden="true" /> Terima</BigButton>
                    </div>
                </li>
            </ul>
        </template>

        <div class="card mb-6 flex flex-col gap-4 p-5">
            <h2 class="flex items-center gap-2 text-xl font-extrabold text-ink"><CalendarClock :size="24" aria-hidden="true" /> Jadwal tagihan</h2>
            <form class="flex flex-col gap-3 sm:flex-row sm:items-end" @submit.prevent="saveDay">
                <label class="block flex-1">
                    <span class="mb-2 block text-lg font-bold text-ink">Jatuh tempo tiap tanggal</span>
                    <select v-model="dayForm.billing_day" class="min-h-touch w-full rounded-2xl border-2 border-line bg-surface px-4 text-lg text-ink">
                        <option value="">Tidak ada (ikut tanggal di nota)</option>
                        <option v-for="d in 28" :key="d" :value="d">Tanggal {{ d }} setiap bulan</option>
                    </select>
                </label>
                <BigButton type="submit" variant="soft" :loading="dayForm.processing" :disabled="String(dayForm.billing_day) === String(billing.day ?? '')">Simpan</BigButton>
            </form>
            <p v-if="dayForm.errors.billing_day" role="alert" class="text-base font-bold text-danger-ink">{{ dayForm.errors.billing_day }}</p>
            <p class="text-base text-ink-soft">
                Kasbon baru tanpa tanggal jatuh tempo otomatis jatuh tempo di tanggal ini.
                <template v-if="billing.auto">Tagihan dikirim otomatis ke WhatsApp pelanggan saat jatuh tempo, lalu diulang tiap 7 hari selama belum lunas.</template>
                <template v-else>Tagih otomatis sedang mati. Nyalakan di halaman Kasbon.</template>
            </p>

            <div class="rounded-2xl bg-surface-2 p-4">
                <span class="mb-1 flex items-center gap-2 text-lg font-bold text-ink"><Link2 :size="22" aria-hidden="true" /> Link tagihan pelanggan</span>
                <p class="mb-3 break-all text-base text-ink-soft">{{ billing.link }}</p>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    <BigButton variant="secondary" @click="copyLink"><Copy :size="20" aria-hidden="true" /> {{ copied ? 'Tersalin!' : 'Salin' }}</BigButton>
                    <BigButton variant="secondary" :href="billing.link" external><ExternalLink :size="20" aria-hidden="true" /> Buka</BigButton>
                    <BigButton variant="ghost" class="col-span-2 sm:col-span-1" @click="confirmReset = true">Ganti link</BigButton>
                </div>
            </div>
        </div>

        <h2 class="mb-3 text-xl font-extrabold text-ink">Catatan utang</h2>
        <ul class="card mb-6 divide-y divide-line">
            <li v-for="r in receivables" :key="r.id" class="flex min-h-touch items-center justify-between gap-3 px-4 py-3">
                <span class="min-w-0">
                    <span class="block text-lg font-bold text-ink">
                        <Link v-if="r.sale_uuid" :href="route('sales.show', r.sale_uuid)" class="underline">{{ TYPE[r.type] ?? 'Utang' }}</Link>
                        <template v-else>{{ TYPE[r.type] ?? 'Utang' }}</template>
                        · {{ r.date }}
                    </span>
                    <span class="block text-base text-ink-soft">
                        <template v-if="r.note">{{ r.note }} · </template>
                        <template v-if="r.due_date">Jatuh tempo {{ r.due_date }}</template>
                    </span>
                </span>
                <span class="text-right">
                    <span class="block font-display text-lg font-extrabold tabular-nums" :class="r.status === 'paid' ? 'text-ink-soft line-through' : 'text-ink'">{{ formatRupiah(r.amount) }}</span>
                    <span v-if="r.status === 'paid'" class="text-sm font-bold text-primary-ink">Lunas</span>
                    <span v-else-if="r.paid_amount" class="text-sm text-ink-soft">sudah dibayar {{ formatRupiah(r.paid_amount) }}</span>
                </span>
            </li>
        </ul>

        <template v-if="doneProofs.length">
            <h2 class="mb-3 text-xl font-extrabold text-ink">Bukti bayar yang sudah diproses</h2>
            <ul class="card mb-6 divide-y divide-line">
                <li v-for="p in doneProofs" :key="p.id" class="flex min-h-touch items-center justify-between gap-3 px-4 py-3">
                    <span class="min-w-0">
                        <span class="block text-lg font-bold" :class="p.status === 'approved' ? 'text-primary-ink' : 'text-danger-ink'">{{ PROOF_STATUS[p.status] }}</span>
                        <span class="block text-base text-ink-soft">{{ p.date }}<template v-if="p.reviewer"> · {{ p.reviewer }}</template><template v-if="p.reject_reason"> · {{ p.reject_reason }}</template></span>
                        <a :href="p.file_url" target="_blank" rel="noopener" class="text-base font-bold text-info-ink underline">Lihat bukti</a>
                    </span>
                    <span class="font-display text-lg font-extrabold text-ink tabular-nums">{{ formatRupiah(p.approved_amount ?? p.amount) }}</span>
                </li>
            </ul>
        </template>

        <template v-if="payments.length">
            <h2 class="mb-3 text-xl font-extrabold text-ink">Riwayat pembayaran</h2>
            <ul class="card divide-y divide-line">
                <li v-for="(p, i) in payments" :key="i" class="flex min-h-touch items-center justify-between gap-3 px-4 py-3">
                    <span class="text-lg text-ink">{{ p.date }}<span v-if="p.user" class="text-ink-soft"> · {{ p.user }}</span></span>
                    <span class="font-display text-lg font-extrabold text-primary-ink tabular-nums">{{ formatRupiah(p.amount) }}</span>
                </li>
            </ul>
        </template>
    </div>

    <BottomSheet v-model:open="paying" title="Terima Pembayaran Utang">
        <form class="flex flex-col gap-4" @submit.prevent="pay">
            <MoneyInput v-model="form.amount" label="Jumlah dibayar" :hint="`Sisa utang ${formatRupiah(balance)}. Boleh dicicil.`" :error="form.errors.amount" />
            <div class="grid grid-cols-2 gap-2">
                <button type="button" class="pressable min-h-touch rounded-2xl border-2 border-line text-lg font-bold" @click="form.amount = balance">Lunas semua</button>
                <button type="button" class="pressable min-h-touch rounded-2xl border-2 border-line text-lg font-bold" @click="form.amount = Math.ceil(balance / 2)">Setengah</button>
            </div>
            <label class="block">
                <span class="mb-2 block text-lg font-bold text-ink">Dibayar dengan</span>
                <select v-model="form.payment_method_id" class="min-h-touch w-full rounded-2xl border-2 border-line bg-surface px-4 text-lg text-ink">
                    <option v-for="m in paymentMethods" :key="m.id" :value="m.id">{{ m.name }}</option>
                </select>
            </label>
            <BigButton type="submit" block size="large" :loading="form.processing">Simpan Pembayaran</BigButton>
        </form>
    </BottomSheet>

    <BottomSheet :open="approving !== null" title="Terima Pembayaran" @update:open="(v) => !v && (approving = null)">
        <form v-if="approving" class="flex flex-col gap-4" @submit.prevent="approve">
            <p class="text-lg text-ink">Pastikan uangnya sudah masuk ke rekening sebelum menekan Terima.</p>
            <MoneyInput v-model="approveForm.amount" label="Jumlah yang diterima" :hint="`Di bukti: ${formatRupiah(approving.amount)}. Sisa utang ${formatRupiah(balance)}.`" :error="approveForm.errors.amount || approveForm.errors.proof" />
            <label class="block">
                <span class="mb-2 block text-lg font-bold text-ink">Diterima lewat</span>
                <select v-model="approveForm.payment_method_id" class="min-h-touch w-full rounded-2xl border-2 border-line bg-surface px-4 text-lg text-ink">
                    <option v-for="m in paymentMethods" :key="m.id" :value="m.id">{{ m.name }}</option>
                </select>
            </label>
            <BigButton type="submit" block size="large" :loading="approveForm.processing">Terima &amp; Kurangi Utang</BigButton>
        </form>
    </BottomSheet>

    <BottomSheet :open="rejecting !== null" title="Tolak Bukti Bayar" @update:open="(v) => !v && (rejecting = null)">
        <form v-if="rejecting" class="flex flex-col gap-4" @submit.prevent="reject">
            <div class="flex flex-wrap gap-2">
                <button v-for="r in REASONS" :key="r" type="button" class="pressable min-h-12 rounded-full border-2 px-4 text-base font-bold" :class="rejectForm.reason === r ? 'border-ink bg-ink text-surface' : 'border-line text-ink'" @click="rejectForm.reason = r">{{ r }}</button>
            </div>
            <label class="block">
                <span class="mb-2 block text-lg font-bold text-ink">Alasan (dikirim ke pelanggan)</span>
                <textarea v-model="rejectForm.reason" rows="2" maxlength="200" class="w-full rounded-2xl border-2 border-line bg-surface px-4 py-3 text-lg text-ink focus:border-focus focus:outline-none"></textarea>
            </label>
            <p v-if="rejectForm.errors.reason || rejectForm.errors.proof" role="alert" class="text-base font-bold text-danger-ink">{{ rejectForm.errors.reason || rejectForm.errors.proof }}</p>
            <BigButton type="submit" variant="danger" block size="large" :loading="rejectForm.processing" :disabled="!rejectForm.reason.trim()">Tolak &amp; Beri Tahu Pelanggan</BigButton>
        </form>
    </BottomSheet>

    <ConfirmDialog
        v-model:open="confirmRemind"
        title="Kirim tagihan?"
        :message="`Tagihan resmi berisi sisa utang ${formatRupiah(balance)} dan link rincian nota akan dikirim ke WhatsApp ${customer.name}.`"
        confirm-text="Ya, Kirim"
        @confirm="remind"
    />

    <ConfirmDialog
        v-model:open="confirmReset"
        title="Ganti link tagihan?"
        message="Link lama tidak bisa dibuka lagi. Pakai ini kalau link tersebar ke orang yang salah."
        confirm-text="Ya, Ganti"
        @confirm="resetLink"
    />
</template>
