<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Wallet } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import BottomSheet from '@/Components/ui/BottomSheet.vue';
import MoneyDisplay from '@/Components/ui/MoneyDisplay.vue';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { formatRupiah } from '@/composables/useRupiah';

defineOptions({ layout: AppLayout });

const props = defineProps({
    purchase: { type: Object, required: true },
    paymentMethods: { type: Array, required: true },
});

const paying = ref(false);
const form = useForm({ amount: props.purchase.due, payment_method_id: props.paymentMethods[0]?.id ?? null });

function pay() {
    form.post(route('purchases.pay', props.purchase.uuid), { preserveScroll: true, onSuccess: () => (paying.value = false) });
}
</script>

<template>
    <Head :title="`Belanja ${purchase.number}`" />
    <div class="mx-auto max-w-2xl">
        <PageHeader :title="purchase.supplier || 'Belanja'" :subtitle="`${purchase.number} · ${purchase.date}`" :back-href="route('purchases.index')" />

        <div class="card mb-4 grid gap-4 p-5 sm:grid-cols-2">
            <MoneyDisplay :amount="purchase.total" size="lg" label="Total belanja" />
            <MoneyDisplay :amount="purchase.due" size="lg" :tone="purchase.due ? 'negative' : 'positive'" :label="purchase.due ? 'Sisa utang' : 'Lunas'" />
            <p v-if="purchase.due_date && purchase.due" class="text-lg text-ink sm:col-span-2">Jatuh tempo: <strong>{{ purchase.due_date }}</strong></p>
            <BigButton v-if="purchase.due" size="large" class="sm:col-span-2" @click="paying = true"><Wallet :size="24" aria-hidden="true" /> Bayar Utang</BigButton>
        </div>

        <h2 class="mb-3 text-xl font-extrabold text-ink">Barang</h2>
        <ul class="card mb-4 divide-y divide-line">
            <li v-for="(item, i) in purchase.items" :key="i" class="flex min-h-touch items-center justify-between gap-3 px-4 py-3">
                <span class="min-w-0">
                    <span class="block text-lg font-bold text-ink">{{ item.name }}</span>
                    <span class="block text-base text-ink-soft">{{ item.qty }} {{ item.unit }} × {{ formatRupiah(item.unit_cost) }}</span>
                    <span v-if="item.pack_count" class="block text-base text-ink-soft">{{ item.pack_count }} karung × {{ item.pack_weight }} {{ item.base_unit }} (nota)</span>
                    <span v-if="item.received_qty !== null" class="block text-base text-ink-soft">Timbangan asli: <strong class="text-ink">{{ item.received_qty }} {{ item.base_unit }}</strong></span>
                    <span v-if="item.weight_diff" class="mt-1 inline-block rounded-lg px-2 text-base font-bold" :class="item.weight_short ? 'bg-danger-soft text-danger-ink' : 'bg-primary-soft text-primary-ink'">
                        Selisih timbang {{ item.weight_short ? '' : '+' }}{{ item.weight_diff }} {{ item.base_unit }}
                    </span>
                    <span v-if="item.extra_cost || item.weight_diff" class="block text-base text-ink-soft">
                        Modal jadi <strong class="text-ink">{{ formatRupiah(item.landed_unit_cost) }}/{{ item.base_unit }}</strong>
                        <template v-if="item.extra_cost"> (termasuk biaya {{ formatRupiah(item.extra_cost) }})</template>
                    </span>
                </span>
                <span class="font-display text-lg font-extrabold text-ink tabular-nums">{{ formatRupiah(item.subtotal) }}</span>
            </li>
        </ul>

        <template v-if="Object.keys(purchase.extra_costs).length">
            <h2 class="mb-3 text-xl font-extrabold text-ink">Biaya tambahan</h2>
            <p class="mb-2 text-base text-ink-soft">Masuk ke modal barang, bukan utang ke pemasok.</p>
            <ul class="card mb-4 divide-y divide-line">
                <li v-for="(amount, label) in purchase.extra_costs" :key="label" class="flex min-h-touch items-center justify-between gap-3 px-4 py-3 text-lg">
                    <span class="text-ink">{{ label }}</span>
                    <span class="font-bold text-ink tabular-nums">{{ formatRupiah(amount) }}</span>
                </li>
            </ul>
        </template>

        <template v-if="purchase.payments.length">
            <h2 class="mb-3 text-xl font-extrabold text-ink">Pembayaran</h2>
            <ul class="card mb-4 divide-y divide-line">
                <li v-for="(p, i) in purchase.payments" :key="i" class="flex min-h-touch items-center justify-between gap-3 px-4 py-3 text-lg">
                    <span class="text-ink">{{ p.date }}</span>
                    <span class="font-display font-extrabold text-primary-ink tabular-nums">{{ formatRupiah(p.amount) }}</span>
                </li>
            </ul>
        </template>

        <p class="text-base text-ink-soft">
            <template v-if="purchase.supplier_invoice_no">No nota pemasok: {{ purchase.supplier_invoice_no }} · </template>
            Dicatat oleh {{ purchase.user }}<template v-if="purchase.note"> · {{ purchase.note }}</template>
        </p>
    </div>

    <BottomSheet v-model:open="paying" title="Bayar Utang ke Pemasok">
        <form class="flex flex-col gap-4" @submit.prevent="pay">
            <MoneyInput v-model="form.amount" label="Jumlah dibayar" :hint="`Sisa utang ${formatRupiah(purchase.due)}`" :error="form.errors.amount" />
            <label class="block">
                <span class="mb-2 block text-lg font-bold text-ink">Dibayar dengan</span>
                <select v-model="form.payment_method_id" class="min-h-touch w-full rounded-2xl border-2 border-line bg-surface px-4 text-lg text-ink">
                    <option v-for="m in paymentMethods" :key="m.id" :value="m.id">{{ m.name }}</option>
                </select>
            </label>
            <BigButton type="submit" block size="large" :loading="form.processing">Simpan Pembayaran</BigButton>
        </form>
    </BottomSheet>
</template>
