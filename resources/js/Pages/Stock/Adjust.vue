<script setup>
/** Stok masuk (barang datang) atau stok keluar (rusak, hilang, dipakai sendiri). */
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import BigInput from '@/Components/ui/BigInput.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import StockItemsEditor from '@/Components/StockItemsEditor.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    type: { type: String, required: true }, // in | out
    reasons: { type: Array, required: true },
});

const isIn = computed(() => props.type === 'in');

const form = useForm({
    type: props.type,
    reason: props.reasons[0]?.value,
    items: [],
    note: '',
});

function submit() {
    form
        .transform((data) => ({
            ...data,
            items: data.items.map((i) => ({ product_id: i.product_id, qty: i.qty, unit_cost: isIn.value ? i.unit_cost : null })),
        }))
        .post(route('stock.adjust.store'));
}
</script>

<template>
    <Head :title="isIn ? 'Stok Masuk' : 'Stok Keluar'" />

    <div class="mx-auto max-w-2xl">
        <PageHeader
            :title="isIn ? 'Stok Masuk' : 'Stok Keluar'"
            :subtitle="isIn ? 'Catat barang yang baru datang.' : 'Catat barang yang rusak, hilang, atau dipakai sendiri.'"
            :back-href="route('stock.index')"
            help="stock-adjust"
        />

        <form class="flex flex-col gap-4" @submit.prevent="submit">
            <section class="card p-5 sm:p-6">
                <h2 class="mb-3 text-xl font-extrabold text-ink">Alasan</h2>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="r in reasons"
                        :key="r.value"
                        type="button"
                        class="pressable flex min-h-12 items-center rounded-full border-2 px-4 text-base font-bold"
                        :class="form.reason === r.value ? 'border-primary bg-primary-soft text-primary-ink' : 'border-line text-ink'"
                        @click="form.reason = r.value"
                    >
                        {{ r.label }}
                    </button>
                </div>
            </section>

            <section class="card p-5 sm:p-6">
                <h2 class="mb-3 text-xl font-extrabold text-ink">Barang</h2>
                <StockItemsEditor v-model="form.items" :with-cost="isIn" :errors="form.errors" />
            </section>

            <section class="card p-5 sm:p-6">
                <BigInput v-model="form.note" label="Catatan" optional placeholder="Contoh: Dari Toko Grosir Maju" />
            </section>

            <BigButton type="submit" size="large" block :loading="form.processing" :disabled="!form.items.length" :variant="isIn ? 'primary' : 'danger'">
                {{ isIn ? 'Simpan Stok Masuk' : 'Simpan Stok Keluar' }}
                <template v-if="form.items.length"> ({{ form.items.length }} barang)</template>
            </BigButton>
        </form>
    </div>
</template>
