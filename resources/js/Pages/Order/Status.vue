<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { CheckCircle2, ChefHat, Clock, QrCode, XCircle } from 'lucide-vue-next';
import BigButton from '@/Components/ui/BigButton.vue';
import { formatRupiah } from '@/composables/useRupiah';

const props = defineProps({
    order: { type: Object, required: true },
    business: { type: String, required: true },
    menuUrl: { type: String, default: null },
    storePhone: { type: String, default: null },
});

const storeWa = computed(() => (props.storePhone ? `https://wa.me/${props.storePhone}?text=${encodeURIComponent(`Halo, saya mau tanya pesanan ${props.order.code}.`)}` : null));

const current = ref(props.order);
let timer = null;

const state = computed(() => {
    const o = current.value;
    if (o.status === 'rejected') return { icon: XCircle, tone: 'bg-danger-soft text-danger-ink', title: 'Pesanan tidak bisa diproses', text: o.reject_reason || 'Silakan tanya pelayan.' };
    if (o.pay_method === 'online' && o.payment_status === 'pending') return { icon: QrCode, tone: 'bg-warn-soft text-warn-ink', title: 'Menunggu pembayaran', text: 'Selesaikan pembayaran QRIS supaya pesanan dikirim ke kasir.' };
    if (o.status === 'new') return { icon: Clock, tone: 'bg-accent-soft text-accent-ink', title: 'Pesanan terkirim', text: 'Kasir sedang mengecek pesanan Anda. Halaman ini akan berubah sendiri.' };
    return { icon: ChefHat, tone: 'bg-primary-soft text-primary-ink', title: 'Pesanan diterima', text: o.order_type === 'delivery' ? 'Pesanan sedang disiapkan lalu diantar ke alamat Anda.' : o.order_type === 'take_away' ? 'Pesanan sedang disiapkan. Silakan ambil di toko.' : 'Pesanan sedang dibuatkan. Silakan tunggu di meja.' };
});

const finished = computed(() => current.value.status !== 'new' || ['expired', 'failed'].includes(current.value.payment_status));

async function poll() {
    if (finished.value || document.hidden) return;
    try {
        const res = await fetch(route('self-order.poll', current.value.uuid), { headers: { Accept: 'application/json' } });
        if (res.ok) current.value = await res.json();
    } catch {
        return;
    }
}

onMounted(() => {
    timer = setInterval(poll, 6000);
});
onBeforeUnmount(() => clearInterval(timer));
</script>

<template>
    <Head :title="`Pesanan ${order.code}`" />
    <div class="min-h-dvh bg-page px-4 pt-safe pb-10">
        <main class="mx-auto flex max-w-xl flex-col gap-4 pt-6">
            <p class="text-center text-lg font-bold text-ink-soft">{{ business }}<template v-if="current.table"> · {{ current.table }}</template></p>
            <p v-if="current.address" class="text-center text-base text-ink-soft">Diantar ke: {{ current.address }}</p>

            <section class="flex flex-col items-center gap-2 rounded-3xl p-6 text-center" :class="state.tone">
                <component :is="state.icon" :size="48" aria-hidden="true" />
                <h1 class="text-2xl font-extrabold">{{ state.title }}</h1>
                <p class="text-lg">{{ state.text }}</p>
                <p class="mt-2 text-base">Kode pesanan</p>
                <p class="font-display text-4xl font-extrabold tracking-widest">{{ current.code }}</p>
            </section>

            <BigButton v-if="current.payment_url" :href="current.payment_url" external block size="large"><QrCode :size="24" aria-hidden="true" /> Bayar sekarang</BigButton>
            <p v-if="current.payment_status === 'paid'" class="flex items-center justify-center gap-2 text-lg font-bold text-primary-ink"><CheckCircle2 :size="24" aria-hidden="true" /> Sudah dibayar lewat QRIS</p>

            <section class="card p-5">
                <h2 class="mb-2 text-xl font-extrabold text-ink">Pesanan {{ current.customer_name }}</h2>
                <ul class="divide-y divide-line">
                    <li v-for="(l, i) in current.items" :key="i" class="flex justify-between gap-3 py-2 text-lg">
                        <span class="text-ink">
                            {{ l.qty }}× {{ l.name }}
                            <span v-if="l.modifiers.length" class="block text-base text-ink-soft">{{ l.modifiers.join(', ') }}</span>
                            <span v-if="l.note" class="block text-base text-ink-soft">{{ l.note }}</span>
                        </span>
                        <span class="shrink-0 font-bold text-ink">{{ formatRupiah(l.subtotal) }}</span>
                    </li>
                </ul>
                <dl class="mt-2 border-t border-line pt-2 text-lg">
                    <div v-if="current.service_charge_amount" class="flex justify-between"><dt class="text-ink-soft">Biaya layanan</dt><dd class="text-ink">{{ formatRupiah(current.service_charge_amount) }}</dd></div>
                    <div v-if="current.tax_amount" class="flex justify-between"><dt class="text-ink-soft">Pajak</dt><dd class="text-ink">{{ formatRupiah(current.tax_amount) }}</dd></div>
                    <div v-if="current.delivery_fee" class="flex justify-between"><dt class="text-ink-soft">Ongkos kirim</dt><dd class="text-ink">{{ formatRupiah(current.delivery_fee) }}</dd></div>
                    <div v-if="current.fee_amount" class="flex justify-between"><dt class="text-ink-soft">Biaya bayar QRIS</dt><dd class="text-ink">{{ formatRupiah(current.fee_amount) }}</dd></div>
                    <div class="flex justify-between font-extrabold"><dt class="text-ink">Total</dt><dd class="text-ink">{{ formatRupiah(current.total + current.fee_amount) }}</dd></div>
                </dl>
            </section>

            <BigButton v-if="storeWa" :href="storeWa" external variant="secondary" block>Hubungi Toko</BigButton>
            <BigButton v-if="menuUrl" :href="menuUrl" external variant="secondary" block>Pesan lagi</BigButton>
        </main>
    </div>
</template>
