<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import BigInput from '@/Components/ui/BigInput.vue';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    customer: { type: Object, default: null },
    priceLevels: { type: Array, default: () => [] },
    showCreditLimit: { type: Boolean, default: false },
});

const form = useForm({
    name: props.customer?.name ?? '',
    phone: props.customer?.phone ?? '',
    address: props.customer?.address ?? '',
    notes: props.customer?.notes ?? '',
    price_level_id: props.customer?.price_level_id ?? null,
    credit_limit: props.customer?.credit_limit ?? null,
});

function submit() {
    // Isian modul yang tidak aktif tidak dikirim, supaya nilainya tidak terhapus.
    form.transform((data) => {
        const payload = { ...data };
        if (!props.priceLevels.length) delete payload.price_level_id;
        if (!props.showCreditLimit) delete payload.credit_limit;
        return payload;
    });
    if (props.customer) form.put(route('customers.update', props.customer.id));
    else form.post(route('customers.store'));
}
</script>

<template>
    <Head :title="customer ? 'Ubah Pelanggan' : 'Tambah Pelanggan'" />
    <div class="mx-auto max-w-2xl">
        <PageHeader :title="customer ? 'Ubah Pelanggan' : 'Tambah Pelanggan'" :back-href="customer ? route('customers.show', customer.id) : route('customers.index')" />
        <form class="card flex flex-col gap-5 p-5 sm:p-8" @submit.prevent="submit">
            <BigInput v-model="form.name" label="Nama" placeholder="Contoh: Pak Joko" :error="form.errors.name" />
            <BigInput v-model="form.phone" label="No HP (WhatsApp)" type="tel" inputmode="tel" optional placeholder="0812 3456 7890" hint="Untuk kirim struk & pengingat lewat WhatsApp." :error="form.errors.phone" />
            <BigInput v-model="form.address" label="Alamat" optional :error="form.errors.address" />
            <BigInput v-model="form.notes" label="Catatan" optional placeholder="Contoh: suka kopi tanpa gula" :error="form.errors.notes" />
            <label v-if="priceLevels.length" class="block">
                <span class="mb-2 block text-lg font-bold text-ink">Tipe harga</span>
                <select v-model="form.price_level_id" class="min-h-touch w-full rounded-2xl border-2 border-line bg-surface px-4 text-lg text-ink">
                    <option :value="null">Harga biasa</option>
                    <option v-for="l in priceLevels" :key="l.id" :value="l.id">{{ l.name }}</option>
                </select>
                <span class="mt-2 block text-base text-ink-soft">Kasir otomatis memakai harga ini saat pelanggan dipilih.</span>
                <span v-if="form.errors.price_level_id" class="mt-2 block text-base font-bold text-danger-ink">{{ form.errors.price_level_id }}</span>
            </label>
            <MoneyInput v-if="showCreditLimit" v-model="form.credit_limit" label="Batas kasbon" optional hint="Kosongkan kalau tidak dibatasi." :error="form.errors.credit_limit" />
            <BigButton type="submit" size="large" block :loading="form.processing">Simpan</BigButton>
        </form>
    </div>
</template>
