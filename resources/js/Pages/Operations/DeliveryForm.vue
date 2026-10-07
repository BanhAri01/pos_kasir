<script setup>
/** Buat pengiriman dari nota. Jumlah per barang bisa dikurangi untuk kirim sebagian. */
import { useForm } from '@inertiajs/vue3';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import BigInput from '@/Components/ui/BigInput.vue';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import QtyInput from '@/Components/ui/QtyInput.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    sale: { type: Object, required: true },
    customer: { type: Object, required: true },
    items: { type: Array, required: true },
});

const form = useForm({
    type: 'delivery',
    recipient_name: props.customer.name ?? '',
    phone: props.customer.phone ?? '',
    address: props.customer.address ?? '',
    scheduled_at: '',
    driver_name: '',
    vehicle: '',
    fee: null,
    note: '',
    items: props.items.map((i) => ({ sale_item_id: i.id, qty: i.remaining })),
});

function save() {
    form.post(route('deliveries.store', props.sale.uuid));
}
</script>

<template>
    <Head title="Kirim Barang" />
    <div class="mx-auto max-w-2xl">
        <PageHeader title="Kirim Barang" :subtitle="`Nota ${sale.number}`" :back-href="route('sales.show', sale.uuid)" />

        <form class="flex flex-col gap-5" @submit.prevent="save">
            <div class="card flex flex-col gap-4 p-5">
                <SegmentedControl v-model="form.type" label="Jenis" :options="[{ value: 'delivery', label: 'Diantar' }, { value: 'pickup', label: 'Diambil sendiri' }]" />
                <BigInput v-model="form.recipient_name" label="Nama penerima" :error="form.errors.recipient_name" />
                <BigInput v-model="form.phone" label="No HP penerima" inputmode="tel" optional :error="form.errors.phone" />
                <label class="block">
                    <span class="mb-2 block text-lg font-bold text-ink">Alamat tujuan</span>
                    <textarea v-model="form.address" rows="3" class="w-full rounded-2xl border-2 border-line bg-surface p-4 text-lg text-ink focus:border-focus focus:outline-none" />
                    <span v-if="form.errors.address" class="mt-2 block text-base font-bold text-danger-ink">{{ form.errors.address }}</span>
                </label>
                <BigInput v-model="form.scheduled_at" label="Jadwal kirim" type="datetime-local" optional :error="form.errors.scheduled_at" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <BigInput v-model="form.driver_name" label="Nama sopir" optional :error="form.errors.driver_name" />
                    <BigInput v-model="form.vehicle" label="Kendaraan / plat" optional :error="form.errors.vehicle" />
                </div>
                <MoneyInput v-model="form.fee" label="Ongkos kirim" optional :error="form.errors.fee" />
            </div>

            <div class="card p-5">
                <h2 class="mb-1 text-xl font-extrabold text-ink">Barang yang dikirim</h2>
                <p class="mb-4 text-base text-ink-soft">Kurangi jumlahnya kalau dikirim sebagian. Isi 0 untuk tidak dikirim sekarang.</p>
                <ul class="flex flex-col gap-4">
                    <li v-for="(item, i) in items" :key="item.id" class="flex flex-wrap items-center justify-between gap-3">
                        <span class="min-w-0 flex-1">
                            <span class="block text-lg font-bold text-ink">{{ item.name }}</span>
                            <span class="block text-base text-ink-soft">Dibeli {{ item.qty }} {{ item.unit }} · belum dikirim {{ item.remaining }}</span>
                        </span>
                        <QtyInput v-model="form.items[i].qty" :label="`Jumlah ${item.name}`" :unit="item.unit || ''" decimal compact />
                    </li>
                </ul>
            </div>

            <BigInput v-model="form.note" label="Catatan untuk sopir" optional :error="form.errors.note" />
            <BigButton type="submit" block size="large" :loading="form.processing">Buat Surat Jalan</BigButton>
        </form>
    </div>
</template>
