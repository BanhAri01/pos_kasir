<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import BigInput from '@/Components/ui/BigInput.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import ToggleSwitch from '@/Components/ui/ToggleSwitch.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    methods: { type: Array, required: true },
    types: { type: Array, required: true },
});

const adding = ref(false);
const form = useForm({ name: '', type: 'ewallet' });
const typeLabel = (value) => props.types.find((t) => t.value === value)?.label ?? value;

function toggle(method, value) {
    router.put(route('payment-methods.update', method.id), { is_active: value }, { preserveScroll: true });
}

function add() {
    form.post(route('payment-methods.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            adding.value = false;
        },
    });
}
</script>

<template>
    <Head title="Cara Bayar" />
    <div class="mx-auto max-w-2xl">
        <PageHeader title="Cara Bayar" subtitle="Pilihan pembayaran yang muncul di layar kasir." :back-href="route('more')" />

        <ul class="flex flex-col gap-3">
            <li v-for="m in methods" :key="m.id" class="card flex flex-wrap items-center gap-x-4 gap-y-2 p-4">
                <div class="min-w-40 flex-1">
                    <p class="font-display text-lg font-extrabold text-ink">{{ m.name }}</p>
                    <p class="text-base text-ink-soft">{{ typeLabel(m.type) }}</p>
                </div>
                <ToggleSwitch :model-value="m.is_active" :label="m.name" class="ml-auto" @update:model-value="(v) => toggle(m, v)" />
            </li>
        </ul>

        <form v-if="adding" class="card mt-4 flex flex-col gap-4 p-5" @submit.prevent="add">
            <BigInput v-model="form.name" label="Nama cara bayar" placeholder="Contoh: GoPay, BCA, DANA" :error="form.errors.name" />
            <label class="block">
                <span class="mb-2 block text-lg font-bold text-ink">Jenis</span>
                <select v-model="form.type" class="min-h-touch w-full rounded-2xl border-2 border-line bg-surface px-4 text-lg text-ink focus:border-focus focus:outline-none">
                    <option v-for="t in types" :key="t.value" :value="t.value">{{ t.label }}</option>
                </select>
            </label>
            <BigButton type="submit" block :loading="form.processing">Tambah</BigButton>
        </form>
        <BigButton v-else variant="soft" block class="mt-4" @click="adding = true"><Plus :size="24" aria-hidden="true" /> Tambah Cara Bayar</BigButton>
    </div>
</template>
