<script setup>
/** Daftar pengiriman: belum berangkat / dalam perjalanan / sampai. Buat baru dari halaman transaksi. */
import { Head, Link, router } from '@inertiajs/vue3';
import { Bike, MapPin, Printer } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    deliveries: { type: Array, required: true },
    statuses: { type: Object, required: true },
    filter: { type: String, default: '' },
});

const NEXT = { pending: 'on_the_way', on_the_way: 'delivered' };
const NEXT_LABEL = { pending: 'Berangkat', on_the_way: 'Sudah Sampai' };
const TONE = { pending: 'bg-surface-2 text-ink', on_the_way: 'bg-accent-soft text-accent-ink', delivered: 'bg-primary-soft text-primary-ink', failed: 'bg-danger-soft text-danger-ink' };

const tabs = [{ value: '', label: 'Belum selesai' }, { value: 'delivered', label: 'Sudah sampai' }, { value: 'failed', label: 'Gagal' }];

function setStatus(delivery, status) {
    router.put(route('deliveries.update', delivery.uuid), { status }, { preserveScroll: true });
}
</script>

<template>
    <Head title="Pengiriman" />
    <PageHeader title="Pengiriman" subtitle="Buat pengiriman dari halaman Transaksi → pilih nota → “Kirim Barang”." />

    <div class="-mx-4 mb-4 flex gap-2 overflow-x-auto px-4 pb-1" role="tablist">
        <Link
            v-for="t in tabs"
            :key="t.value"
            :href="route('deliveries.index', t.value ? { status: t.value } : {})"
            role="tab"
            :aria-selected="filter === t.value"
            preserve-state
            class="pressable flex min-h-touch shrink-0 items-center rounded-2xl px-4 text-lg font-bold"
            :class="filter === t.value ? 'bg-primary text-on-primary' : 'bg-surface-2 text-ink'"
        >
            {{ t.label }}
        </Link>
    </div>

    <EmptyState v-if="!deliveries.length" title="Tidak ada pengiriman" message="Pengiriman yang dibuat dari transaksi akan muncul di sini.">
        <template #icon><Bike :size="48" aria-hidden="true" /></template>
    </EmptyState>

    <ul class="grid gap-3 md:grid-cols-2">
        <li v-for="d in deliveries" :key="d.uuid" class="card flex min-w-0 flex-col gap-2 p-4">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="truncate text-xl font-extrabold text-ink">{{ d.recipient_name }}</p>
                    <p class="text-base text-ink-soft">{{ d.number }} · nota {{ d.sale_number }}</p>
                </div>
                <span class="shrink-0 rounded-full px-3 py-1 text-sm font-bold" :class="TONE[d.status]">{{ statuses[d.status] }}</span>
            </div>
            <p class="flex gap-2 text-base text-ink"><MapPin :size="20" class="mt-0.5 shrink-0 text-ink-soft" aria-hidden="true" /> {{ d.address }}</p>
            <p class="text-base text-ink-soft">{{ d.items }}</p>
            <p v-if="d.scheduled || d.driver_name" class="text-base text-ink-soft">
                <template v-if="d.scheduled">{{ d.scheduled }}</template><template v-if="d.driver_name"> · Sopir {{ d.driver_name }}</template>
            </p>
            <div class="mt-1 grid grid-cols-2 gap-2">
                <a :href="route('deliveries.print', d.uuid)" target="_blank" class="pressable flex min-h-touch items-center justify-center gap-2 rounded-2xl border-2 border-line text-lg font-bold text-ink">
                    <Printer :size="20" aria-hidden="true" /> Surat Jalan
                </a>
                <BigButton v-if="NEXT[d.status]" @click="setStatus(d, NEXT[d.status])">{{ NEXT_LABEL[d.status] }}</BigButton>
            </div>
            <button v-if="d.status === 'on_the_way'" type="button" class="self-start text-base font-bold text-danger-ink underline" @click="setStatus(d, 'failed')">Gagal / barang kembali</button>
        </li>
    </ul>
</template>
