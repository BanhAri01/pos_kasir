<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { Store } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import BigInput from '@/Components/ui/BigInput.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import StockItemsEditor from '@/Components/StockItemsEditor.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    fromOutlet: { type: Object, required: true },
    outlets: { type: Array, required: true },
});

const form = useForm({
    to_outlet_id: props.outlets.length === 1 ? props.outlets[0].id : null,
    items: [],
    note: '',
});

function submit() {
    form.transform((d) => ({ ...d, items: d.items.map((i) => ({ product_id: i.product_id, qty: i.qty })) })).post(route('stock.transfers.store'));
}
</script>

<template>
    <Head title="Kirim Stok Baru" />

    <div class="mx-auto max-w-2xl">
        <PageHeader title="Kirim Stok Baru" :subtitle="`Dari ${fromOutlet.name}`" :back-href="route('stock.transfers')" help="stock-transfer" />

        <form class="flex flex-col gap-4" @submit.prevent="submit">
            <section class="card p-5 sm:p-6">
                <h2 class="mb-3 text-xl font-extrabold text-ink">Kirim ke outlet</h2>
                <div class="flex flex-col gap-2">
                    <button
                        v-for="o in outlets"
                        :key="o.id"
                        type="button"
                        class="pressable flex min-h-touch items-center gap-3 rounded-2xl border-2 px-4 text-left text-lg font-bold"
                        :class="form.to_outlet_id === o.id ? 'border-primary bg-primary-soft text-ink' : 'border-line text-ink'"
                        @click="form.to_outlet_id = o.id"
                    >
                        <Store :size="24" aria-hidden="true" /> {{ o.name }}
                    </button>
                </div>
                <p v-if="form.errors.to_outlet_id" role="alert" class="mt-2 text-base font-bold text-danger-ink">{{ form.errors.to_outlet_id }}</p>
            </section>

            <section class="card p-5 sm:p-6">
                <h2 class="mb-3 text-xl font-extrabold text-ink">Barang yang dikirim</h2>
                <StockItemsEditor v-model="form.items" :errors="form.errors" />
            </section>

            <section class="card p-5 sm:p-6">
                <BigInput v-model="form.note" label="Catatan" optional placeholder="Contoh: Dibawa Pak Budi naik motor" />
            </section>

            <BigButton type="submit" size="large" block :loading="form.processing" :disabled="!form.items.length || !form.to_outlet_id">
                Kirim Sekarang
            </BigButton>
        </form>
    </div>
</template>
