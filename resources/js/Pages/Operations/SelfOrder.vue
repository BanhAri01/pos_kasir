<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Copy, ExternalLink, Printer, RefreshCw } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import BigInput from '@/Components/ui/BigInput.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import ToggleSwitch from '@/Components/ui/ToggleSwitch.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    tables: { type: Array, required: true },
    payment: { type: Object, required: true },
    fee: { type: Object, default: null },
});

const form = useForm({ mode: props.payment.mode, server_key: '', production: props.payment.production });
const regenerating = ref(null);
const copied = ref(null);

function save() {
    form.put(route('self-orders.payment'), { preserveScroll: true, onSuccess: () => form.reset('server_key') });
}

async function copy(text, key) {
    try {
        await navigator.clipboard.writeText(text);
        copied.value = key;
    } catch {
        copied.value = null;
    }
}
</script>

<template>
    <Head title="Pesan Lewat QR" />
    <div class="mx-auto max-w-3xl">
        <PageHeader title="Pesan Lewat QR" subtitle="Pelanggan scan QR di meja, pilih menu, pesanan masuk ke kasir." :back-href="route('more')" />

        <section class="card mb-6 flex flex-col gap-4 p-5">
            <h2 class="text-xl font-extrabold text-ink">1. Cetak QR untuk setiap meja</h2>
            <p class="text-lg text-ink-soft">Tempel QR di meja. Setiap QR sudah berisi nomor mejanya, jadi pesanan langsung tahu dari meja mana.</p>
            <BigButton :href="route('self-orders.print')" external block><Printer :size="22" aria-hidden="true" /> Cetak QR Semua Meja</BigButton>
            <p v-if="!tables.length" class="rounded-2xl bg-surface-2 p-4 text-lg text-ink">Belum ada meja. Tambahkan meja dulu di menu Meja.</p>
            <ul v-else class="divide-y divide-line rounded-2xl border border-line">
                <li v-for="t in tables" :key="t.id" class="flex flex-wrap items-center gap-2 px-4 py-3">
                    <span class="min-w-0 flex-1 text-lg font-bold text-ink">{{ t.name }}<span v-if="t.area" class="font-normal text-ink-soft"> · {{ t.area }}</span></span>
                    <a :href="t.url" target="_blank" rel="noopener" class="pressable flex min-h-12 items-center gap-1 rounded-2xl px-3 text-base font-bold text-ink"><ExternalLink :size="18" aria-hidden="true" /> Coba</a>
                    <button type="button" class="pressable flex min-h-12 items-center gap-1 rounded-2xl px-3 text-base font-bold text-ink" @click="copy(t.url, t.id)"><Copy :size="18" aria-hidden="true" /> {{ copied === t.id ? 'Tersalin' : 'Salin link' }}</button>
                    <a :href="route('self-orders.print', { meja: t.id })" target="_blank" rel="noopener" class="pressable flex min-h-12 items-center gap-1 rounded-2xl px-3 text-base font-bold text-ink"><Printer :size="18" aria-hidden="true" /> Cetak</a>
                    <button type="button" class="pressable flex min-h-12 items-center gap-1 rounded-2xl px-3 text-base font-bold text-ink-soft" @click="regenerating = t"><RefreshCw :size="18" aria-hidden="true" /> Ganti QR</button>
                </li>
            </ul>
        </section>

        <section class="card mb-6 flex flex-col gap-4 p-5">
            <h2 class="text-xl font-extrabold text-ink">2. Cara pelanggan membayar</h2>
            <SegmentedControl v-model="form.mode" label="Cara bayar pesanan QR" :options="[{ value: 'cashier', label: 'Bayar di kasir' }, { value: 'online', label: 'Bisa bayar QRIS dari HP' }]" />
            <p v-if="form.mode === 'cashier'" class="rounded-2xl bg-primary-soft p-4 text-lg text-primary-ink">Pesanan masuk ke kasir dan dicatat di meja. Pelanggan membayar seperti biasa setelah selesai.</p>
            <template v-else>
                <p class="rounded-2xl bg-surface-2 p-4 text-lg text-ink">
                    Uang masuk langsung ke akun Midtrans milik usaha Anda. Biaya QRIS ({{ (fee?.percent_bp ?? 70) / 100 }}% + PPN) <b>ditambahkan ke tagihan pembeli</b>, jadi Anda menerima harga menu utuh.
                </p>
                <BigInput
                    v-model="form.server_key"
                    label="Server key Midtrans"
                    type="password"
                    :hint="payment.has_keys ? 'Key sudah tersimpan. Kosongkan kalau tidak ingin mengganti.' : 'Ada di dashboard Midtrans: Settings > Access Keys.'"
                    :error="form.errors.server_key"
                />
                <div class="flex items-center justify-between gap-3">
                    <span class="text-lg font-bold text-ink">Uang sungguhan (bukan uji coba)</span>
                    <ToggleSwitch v-model="form.production" label="Uang sungguhan" />
                </div>
                <div class="rounded-2xl bg-surface-2 p-4">
                    <p class="text-base font-bold text-ink">Alamat notifikasi pembayaran (isi di Midtrans: Settings > Payment > Notification URL)</p>
                    <p class="my-1 text-base break-all text-ink">{{ payment.webhook_url }}</p>
                    <button type="button" class="pressable flex min-h-12 items-center gap-1 rounded-2xl text-base font-bold text-primary-ink" @click="copy(payment.webhook_url, 'webhook')"><Copy :size="18" aria-hidden="true" /> {{ copied === 'webhook' ? 'Tersalin' : 'Salin alamat' }}</button>
                </div>
            </template>
            <BigButton block :loading="form.processing" @click="save">Simpan</BigButton>
        </section>

        <section class="card p-5">
            <h2 class="mb-2 text-xl font-extrabold text-ink">3. Terima pesanan di kasir</h2>
            <p class="text-lg text-ink-soft">Di layar kasir muncul tombol <b>Pesanan QR</b> dengan bunyi saat ada pesanan baru. Ketuk Terima: pesanan bayar di kasir masuk ke meja, pesanan yang sudah lunas QRIS langsung tercatat sebagai penjualan dan dikirim ke dapur.</p>
        </section>

        <ConfirmDialog
            :open="!!regenerating"
            :title="`Ganti QR ${regenerating?.name ?? ''}?`"
            message="QR lama di meja ini tidak bisa dipakai lagi. Pakai ini kalau QR difoto dan disebar orang. Setelah ini, cetak dan tempel QR yang baru."
            confirm-text="Ya, Ganti QR"
            @update:open="(v) => !v && (regenerating = null)"
            @confirm="router.post(route('self-orders.regenerate', regenerating.id), {}, { preserveScroll: true }); regenerating = null"
        />
    </div>
</template>
