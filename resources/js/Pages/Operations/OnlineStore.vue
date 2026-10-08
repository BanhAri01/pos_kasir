<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Copy, ExternalLink, MessageCircle, Printer, RefreshCw } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import ToggleSwitch from '@/Components/ui/ToggleSwitch.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    outlet: { type: Object, required: true },
    paymentOnline: { type: Boolean, default: false },
    hasQrOrder: { type: Boolean, default: false },
});

const form = useForm({
    online_open: props.outlet.online_open,
    online_pickup: props.outlet.online_pickup,
    online_delivery: props.outlet.online_delivery,
    delivery_fee: props.outlet.delivery_fee,
    online_min_order: props.outlet.online_min_order,
});
const copied = ref(false);
const confirmRegenerate = ref(false);

const shareUrl = computed(() => `https://wa.me/?text=${encodeURIComponent(`Sekarang bisa pesan dari HP! Pilih menu lalu ambil sendiri${props.outlet.online_delivery ? ' atau diantar' : ''}: ${props.outlet.url}`)}`);

async function copy() {
    try {
        await navigator.clipboard.writeText(props.outlet.url);
        copied.value = true;
    } catch {
        copied.value = false;
    }
}

function save() {
    form.put(route('online-orders.update'), { preserveScroll: true });
}
</script>

<template>
    <Head title="Toko Online" />
    <div class="mx-auto max-w-3xl">
        <PageHeader title="Toko Online" :subtitle="`Link pesan antar & ambil sendiri untuk ${outlet.name}.`" :back-href="route('more')" />

        <section class="card mb-6 flex flex-col gap-4 p-5">
            <h2 class="text-xl font-extrabold text-ink">Link toko Anda</h2>
            <p class="rounded-2xl bg-surface-2 p-4 text-lg break-all text-ink">{{ outlet.url }}</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <BigButton variant="secondary" @click="copy"><Copy :size="22" aria-hidden="true" /> {{ copied ? 'Tersalin' : 'Salin Link' }}</BigButton>
                <BigButton :href="shareUrl" external><MessageCircle :size="22" aria-hidden="true" /> Bagikan ke WhatsApp</BigButton>
                <BigButton :href="outlet.url" external variant="secondary"><ExternalLink :size="22" aria-hidden="true" /> Lihat Toko</BigButton>
                <BigButton :href="route('online-orders.poster')" external variant="secondary"><Printer :size="22" aria-hidden="true" /> Cetak Poster QR</BigButton>
            </div>
            <p class="text-base text-ink-soft">Tempel link di bio Instagram, status WhatsApp, atau Google Maps. Pesanan masuk ke tombol <b>Pesanan QR</b> di layar kasir.</p>
        </section>

        <section class="card mb-6 flex flex-col gap-5 p-5">
            <h2 class="text-xl font-extrabold text-ink">Pengaturan</h2>
            <div class="flex items-center justify-between gap-3">
                <span><span class="block text-lg font-bold text-ink">Toko buka</span><span class="block text-base text-ink-soft">Matikan saat tutup atau sedang ramai.</span></span>
                <ToggleSwitch v-model="form.online_open" label="Toko buka" />
            </div>
            <div class="flex items-center justify-between gap-3">
                <span class="text-lg font-bold text-ink">Ambil sendiri</span>
                <ToggleSwitch v-model="form.online_pickup" label="Ambil sendiri" />
            </div>
            <div class="flex items-center justify-between gap-3">
                <span class="text-lg font-bold text-ink">Diantar</span>
                <ToggleSwitch v-model="form.online_delivery" label="Diantar" />
            </div>
            <p v-if="form.errors.online_pickup" class="text-lg font-bold text-danger-ink">{{ form.errors.online_pickup }}</p>
            <div class="grid gap-4 sm:grid-cols-2">
                <MoneyInput v-if="form.online_delivery" v-model="form.delivery_fee" label="Ongkos kirim" hint="Isi 0 kalau gratis ongkir." :error="form.errors.delivery_fee" />
                <MoneyInput v-model="form.online_min_order" label="Minimal pesanan" optional hint="Isi 0 kalau tanpa minimal." :error="form.errors.online_min_order" />
            </div>
            <p v-if="!outlet.has_phone" class="rounded-2xl bg-warn-soft p-4 text-lg text-warn-ink">
                Isi no HP outlet supaya pelanggan bisa menghubungi toko. <Link :href="route('outlets.index')" class="font-bold underline">Atur outlet</Link>
            </p>
            <p class="rounded-2xl bg-surface-2 p-4 text-lg text-ink">
                {{ paymentOnline ? 'Pelanggan bisa bayar QRIS dari HP (biaya ditanggung pembeli).' : 'Pelanggan membayar saat mengambil atau saat barang diantar.' }}
                <Link :href="route('self-orders.settings')" class="font-bold text-primary-ink underline">Atur bayar QRIS</Link>
            </p>
            <BigButton block :loading="form.processing" @click="save">Simpan</BigButton>
        </section>

        <BigButton variant="ghost" block class="text-ink-soft" @click="confirmRegenerate = true"><RefreshCw :size="20" aria-hidden="true" /> Ganti link toko</BigButton>

        <ConfirmDialog
            v-model:open="confirmRegenerate"
            title="Ganti link toko?"
            message="Link dan poster QR lama tidak bisa dipakai lagi. Bagikan dan cetak ulang yang baru."
            confirm-text="Ya, Ganti Link"
            @confirm="confirmRegenerate = false; router.post(route('online-orders.regenerate'), {}, { preserveScroll: true })"
        />
    </div>
</template>
