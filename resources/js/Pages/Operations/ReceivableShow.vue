<script setup>
/** Rincian utang satu pelanggan: terima bayar (cicil / lunas), kirim pengingat WhatsApp. */
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { BellRing, Wallet } from 'lucide-vue-next';
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
});

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
                <BigButton size="large" variant="soft" :disabled="balance <= 0 || !customer.has_phone" @click="confirmRemind = true"><BellRing :size="24" aria-hidden="true" /> Ingatkan via WA</BigButton>
            </div>
            <p v-if="!customer.has_phone" class="text-base text-ink-soft">Tambahkan no HP pelanggan supaya bisa dikirimi pengingat.</p>
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

    <ConfirmDialog
        v-model:open="confirmRemind"
        title="Kirim pengingat?"
        :message="`Pesan sopan berisi sisa utang ${formatRupiah(balance)} akan dikirim ke WhatsApp ${customer.name}.`"
        confirm-text="Ya, Kirim"
        @confirm="remind"
    />
</template>
