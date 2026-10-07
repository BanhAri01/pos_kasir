<script setup>
/** Detail satu transaksi + batalkan / kembalikan barang / buka struk. */
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Bike, ExternalLink, FileText, Repeat, RotateCcw, XCircle } from 'lucide-vue-next';
import { useAuth } from '@/composables/useAuth';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import BigInput from '@/Components/ui/BigInput.vue';
import BottomSheet from '@/Components/ui/BottomSheet.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import QtyInput from '@/Components/ui/QtyInput.vue';
import ToggleSwitch from '@/Components/ui/ToggleSwitch.vue';
import { formatRupiah } from '@/composables/useRupiah';

defineOptions({ layout: AppLayout });

const props = defineProps({
    sale: { type: Object, required: true },
    refunds: { type: Array, required: true },
    paymentMethods: { type: Array, required: true },
    can: { type: Object, required: true },
});

const { hasModule } = useAuth();
const voidOpen = ref(false);
const refundOpen = ref(false);
const voidForm = useForm({ reason: '' });
const refundForm = useForm({
    reason: '',
    restock: true,
    payment_method_id: props.paymentMethods[0]?.id ?? null,
    qty: Object.fromEntries(props.sale.items.map((i) => [i.id, ''])),
});

const isVoid = computed(() => props.sale.status === 'void');

function submitVoid() {
    voidForm.post(route('sales.void', props.sale.uuid), { preserveScroll: true, onSuccess: () => (voidOpen.value = false) });
}

function submitRefund() {
    refundForm
        .transform((d) => ({
            reason: d.reason,
            restock: d.restock,
            payment_method_id: d.payment_method_id,
            items: Object.entries(d.qty)
                .map(([id, q]) => ({ sale_item_id: Number(id), qty: parseFloat(String(q).replace(',', '.')) || 0 }))
                .filter((i) => i.qty > 0),
        }))
        .post(route('sales.refund', props.sale.uuid), { preserveScroll: true, onSuccess: () => (refundOpen.value = false) });
}

const row = 'flex justify-between gap-3 text-lg';
</script>

<template>
    <Head :title="`Nota ${sale.number}`" />

    <div class="mx-auto max-w-2xl">
        <PageHeader :title="`Nota ${sale.number}`" :subtitle="`${sale.date}, ${sale.time} · Kasir ${sale.cashier}`" :back-href="route('sales.index')" />

        <div v-if="isVoid" class="mb-4 rounded-2xl bg-danger-soft p-4 text-lg font-bold text-danger-ink">
            Transaksi ini dibatalkan. Alasan: {{ sale.void_reason }}
        </div>

        <section class="card p-5">
            <p v-if="sale.customer" class="mb-3 text-lg text-ink-soft">Pelanggan: <strong class="text-ink">{{ sale.customer.name }}</strong></p>
            <ul class="flex flex-col gap-3">
                <li v-for="item in sale.items" :key="item.id">
                    <div :class="row">
                        <span class="font-bold text-ink">{{ item.name }}</span>
                        <span class="tabular-nums">{{ formatRupiah(item.subtotal) }}</span>
                    </div>
                    <p class="text-base text-ink-soft">
                        {{ item.qty }}{{ item.unit ? ` ${item.unit}` : '' }} × {{ formatRupiah(item.unit_price) }}
                        <template v-if="item.discount_amount"> · diskon {{ formatRupiah(item.discount_amount) }}</template>
                        <template v-if="item.refunded_qty"> · {{ item.refunded_qty }} dikembalikan</template>
                    </p>
                    <p v-if="item.note" class="text-base font-bold text-accent-ink">* {{ item.note }}</p>
                </li>
            </ul>
            <div class="mt-4 flex flex-col gap-1 border-t-2 border-line pt-3">
                <p :class="row"><span>Subtotal</span><span class="tabular-nums">{{ formatRupiah(sale.subtotal) }}</span></p>
                <p v-if="sale.discount_amount" :class="row"><span>Diskon</span><span class="tabular-nums">-{{ formatRupiah(sale.discount_amount) }}</span></p>
                <p v-if="sale.service_charge_amount" :class="row"><span>Biaya layanan</span><span class="tabular-nums">{{ formatRupiah(sale.service_charge_amount) }}</span></p>
                <p v-if="sale.tax_amount" :class="row"><span>Pajak</span><span class="tabular-nums">{{ formatRupiah(sale.tax_amount) }}</span></p>
                <p :class="row" class="text-2xl font-extrabold text-ink"><span>Total</span><span class="tabular-nums">{{ formatRupiah(sale.total) }}</span></p>
                <p v-for="p in sale.payments" :key="p.name" :class="row" class="text-ink-soft"><span>{{ p.name }}</span><span class="tabular-nums">{{ formatRupiah(p.amount) }}</span></p>
                <p v-if="sale.change_amount" :class="row" class="text-ink-soft"><span>Kembalian</span><span class="tabular-nums">{{ formatRupiah(sale.change_amount) }}</span></p>
                <p v-if="sale.refunded_amount" :class="row" class="font-bold text-danger-ink"><span>Dikembalikan</span><span class="tabular-nums">-{{ formatRupiah(sale.refunded_amount) }}</span></p>
            </div>
        </section>

        <section v-if="refunds.length" class="card mt-4 p-5">
            <h2 class="mb-2 text-xl font-extrabold text-ink">Pengembalian</h2>
            <p v-for="r in refunds" :key="r.number" class="text-lg">{{ r.number }}: {{ formatRupiah(r.amount) }} · {{ r.reason }} ({{ r.user }})</p>
        </section>

        <div class="mt-4 flex flex-col gap-3">
            <a
                :href="sale.receipt_url"
                target="_blank"
                rel="noopener"
                class="pressable flex min-h-touch items-center justify-center gap-3 rounded-2xl border-2 border-line bg-surface px-6 text-lg font-bold text-ink hover:border-ink-soft"
            >
                <ExternalLink :size="22" aria-hidden="true" /> Buka Struk
            </a>
            <div class="grid gap-3 sm:grid-cols-2">
                <a
                    :href="route('sales.invoice', sale.uuid)"
                    target="_blank"
                    class="pressable flex min-h-touch items-center justify-center gap-3 rounded-2xl border-2 border-line bg-surface px-6 text-lg font-bold text-ink hover:border-ink-soft"
                >
                    <FileText :size="22" aria-hidden="true" /> Faktur A4
                </a>
                <BigButton v-if="!isVoid && hasModule('delivery')" variant="secondary" :href="route('deliveries.create', sale.uuid)">
                    <Bike :size="22" aria-hidden="true" /> Kirim Barang
                </BigButton>
            </div>
            <div v-if="!isVoid" class="grid gap-3 sm:grid-cols-2">
                <BigButton v-if="can.refund && hasModule('returns_exchange') && sale.payment_status !== 'refunded'" variant="secondary" :href="route('sales.exchange', sale.uuid)">
                    <Repeat :size="22" aria-hidden="true" /> Tukar Barang
                </BigButton>
                <BigButton v-if="can.refund && sale.payment_status !== 'refunded'" variant="secondary" @click="refundOpen = true">
                    <RotateCcw :size="22" aria-hidden="true" /> Kembalikan Barang
                </BigButton>
                <BigButton v-if="can.void && !sale.refunded_amount" variant="ghost" class="text-danger-ink" @click="voidOpen = true">
                    <XCircle :size="22" aria-hidden="true" /> Batalkan Transaksi
                </BigButton>
            </div>
        </div>
    </div>

    <BottomSheet v-model:open="voidOpen" title="Batalkan transaksi?">
        <div class="flex flex-col gap-4">
            <p class="text-lg text-ink-soft">Seluruh transaksi dibatalkan dan stok barang dikembalikan.</p>
            <BigInput v-model="voidForm.reason" label="Alasan" placeholder="Contoh: salah input" :error="voidForm.errors.reason || voidForm.errors.sale" />
            <BigButton variant="danger" block :loading="voidForm.processing" @click="submitVoid">Ya, Batalkan</BigButton>
            <BigButton variant="secondary" block @click="voidOpen = false">Tidak Jadi</BigButton>
        </div>
    </BottomSheet>

    <BottomSheet v-model:open="refundOpen" title="Kembalikan Barang">
        <div class="flex flex-col gap-4">
            <div v-for="item in sale.items.filter((i) => i.refundable_qty > 0)" :key="item.id" class="rounded-2xl border-2 border-line p-3">
                <p class="mb-2 text-lg font-bold text-ink">{{ item.name }} <span class="font-normal text-ink-soft">(dibeli {{ item.qty }})</span></p>
                <QtyInput v-model="refundForm.qty[item.id]" :label="`Jumlah ${item.name} dikembalikan`" compact :unit="item.unit" />
            </div>
            <p v-if="refundForm.errors.items" role="alert" class="text-lg font-bold text-danger-ink">{{ refundForm.errors.items }}</p>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                <span class="min-w-48 flex-1 text-lg text-ink">Barang dikembalikan ke stok</span>
                <ToggleSwitch v-model="refundForm.restock" label="Kembalikan ke stok" class="ml-auto" />
            </div>
            <BigInput v-model="refundForm.reason" label="Alasan" placeholder="Contoh: barang rusak" :error="refundForm.errors.reason" />
            <BigButton block :loading="refundForm.processing" @click="submitRefund">Simpan Pengembalian</BigButton>
        </div>
    </BottomSheet>
</template>
