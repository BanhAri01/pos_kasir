<script setup>
/**
 * Resep kemas / olah: barang hasil + jumlah hasil per 1 kali, dan bahan-bahannya.
 * Contoh kemas: 1 Jagung Karung 25 kg = 25 kg Jagung Pipil + 1 Karung Kosong 25 kg + 0,02 roll benang.
 */
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Plus, X } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import BigInput from '@/Components/ui/BigInput.vue';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import QtyInput from '@/Components/ui/QtyInput.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    formula: { type: Object, default: null },
    kind: { type: String, required: true },
    kinds: { type: Array, required: true },
    products: { type: Array, required: true },
});

const form = useForm({
    kind: props.formula?.kind ?? props.kind,
    name: props.formula?.name ?? '',
    output_product_id: props.formula?.output_product_id ?? null,
    output_qty: props.formula?.output_qty ?? '1',
    cost_per_batch: props.formula?.cost_per_batch || null,
    note: props.formula?.note ?? '',
    items: props.formula?.items.map((i) => ({ ...i })) ?? [],
});

const isRepack = computed(() => form.kind === 'repack');
const productById = (id) => props.products.find((p) => p.id === id);
const output = computed(() => productById(form.output_product_id));
const adding = ref(null);

const KIND_HELP = {
    production: 'Membersihkan, menggiling, atau meracik. Contoh: jagung kotor jadi jagung bersih.',
    repack: 'Barang curah dimasukkan ke karung. Contoh: 50 kg jagung bersih + 1 karung = 1 karung jual.',
};
const available = computed(() => props.products.filter((p) => p.id !== form.output_product_id && !form.items.some((i) => i.product_id === p.id)));

function addItem() {
    if (!adding.value) return;
    form.items.push({ product_id: adding.value, qty: '1' });
    adding.value = null;
}

function suggestName() {
    if (!form.name && output.value) form.name = `${isRepack.value ? 'Kemas' : 'Olah'} ${output.value.name}`;
}

function save() {
    props.formula ? form.put(route('warehouse.formulas.update', props.formula.id)) : form.post(route('warehouse.formulas.store'));
}

const selectClass = 'min-h-touch w-full rounded-2xl border-2 border-line bg-surface px-4 text-lg text-ink';
</script>

<template>
    <Head :title="formula ? 'Ubah Resep' : 'Resep Baru'" />
    <div class="mx-auto max-w-2xl">
        <PageHeader :title="formula ? 'Ubah Resep' : 'Resep Baru'" :back-href="route('warehouse.formulas')" />

        <form class="flex flex-col gap-5" @submit.prevent="save">
            <section class="card flex flex-col gap-4 p-5">
                <div v-if="kinds.length > 1 && !formula">
                    <span class="mb-2 block text-lg font-bold text-ink">Mau membuat resep apa?</span>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <button
                            v-for="k in kinds"
                            :key="k.value"
                            type="button"
                            class="pressable flex min-h-touch-lg flex-col items-start gap-1 rounded-2xl border-2 p-4 text-left"
                            :class="form.kind === k.value ? 'border-primary bg-primary-soft' : 'border-line'"
                            :aria-pressed="form.kind === k.value"
                            @click="form.kind = k.value"
                        >
                            <span class="text-lg font-extrabold text-ink">{{ k.label }}</span>
                            <span class="text-base text-ink-soft">{{ KIND_HELP[k.value] }}</span>
                        </button>
                    </div>
                </div>
                <label class="block">
                    <span class="mb-2 block text-lg font-bold text-ink">{{ isRepack ? 'Barang kemasan yang dibuat' : 'Barang hasil olah' }} <span class="font-semibold text-ink-soft">{{ isRepack ? '(contoh: Jagung Bersih Karung 50 kg)' : '(contoh: Jagung Bersih)' }}</span></span>
                    <select v-model="form.output_product_id" :class="selectClass" @change="suggestName">
                        <option :value="null" disabled>Pilih barang</option>
                        <option v-for="p in products.filter((p) => !p.is_packaging)" :key="p.id" :value="p.id">{{ p.name }} ({{ p.unit }})</option>
                    </select>
                    <span v-if="form.errors.output_product_id" role="alert" class="mt-2 block text-base font-bold text-danger-ink">{{ form.errors.output_product_id }}</span>
                    <span class="mt-2 block text-base text-ink-soft">Belum ada barangnya? Tambahkan dulu di menu Barang.</span>
                </label>
                <QtyInput v-model="form.output_qty" :label="isRepack ? 'Jumlah hasil per 1 kali kemas' : 'Jumlah hasil per 1 kali olah'" :unit="output?.unit" decimal :error="form.errors.output_qty" />
                <BigInput v-model="form.name" label="Nama resep" placeholder="Contoh: Kemas Jagung 25 kg" :error="form.errors.name" />
            </section>

            <section class="card flex flex-col gap-4 p-5">
                <div>
                    <h2 class="text-xl font-extrabold text-ink">Bahan per 1 kali {{ isRepack ? 'kemas' : 'olah' }}</h2>
                    <p class="text-base text-ink-soft">{{ isRepack ? 'Barang curah + karung kosong + benang/label.' : 'Bahan yang diolah. Contoh membersihkan: Jagung Pipil (kotor) 100 kg.' }}</p>
                </div>
                <p v-if="form.errors.items" role="alert" class="text-base font-bold text-danger-ink">{{ form.errors.items }}</p>
                <ul class="flex flex-col divide-y divide-line">
                    <li v-for="(item, i) in form.items" :key="item.product_id" class="flex items-end gap-2 py-3">
                        <div class="min-w-0 flex-1">
                            <p class="mb-2 text-lg font-bold text-ink">
                                {{ productById(item.product_id)?.name }}
                                <span v-if="productById(item.product_id)?.is_packaging" class="text-base font-semibold text-ink-soft">· bahan kemas</span>
                            </p>
                            <QtyInput v-model="item.qty" :label="`Jumlah ${productById(item.product_id)?.name}`" :unit="productById(item.product_id)?.unit" decimal compact :error="form.errors[`items.${i}.qty`]" />
                        </div>
                        <button type="button" class="pressable flex size-12 shrink-0 items-center justify-center rounded-xl text-danger-ink" :aria-label="`Hapus ${productById(item.product_id)?.name}`" @click="form.items.splice(i, 1)"><X :size="24" /></button>
                    </li>
                </ul>
                <p v-if="!form.items.length" class="text-lg text-ink-soft">
                    Belum ada bahan. Pilih di bawah, bahan langsung masuk ke daftar.
                </p>
                <label v-if="available.length" class="block">
                    <span class="mb-2 flex items-center gap-2 text-lg font-bold text-ink"><Plus :size="22" aria-hidden="true" /> Tambah bahan</span>
                    <!-- Langsung masuk daftar begitu dipilih (tidak perlu tombol tambahan). -->
                    <select v-model="adding" :class="selectClass" @change="addItem">
                        <option :value="null" disabled>Pilih bahan</option>
                        <option v-for="p in available" :key="p.id" :value="p.id">{{ p.name }} ({{ p.unit }}){{ p.is_packaging ? ' · bahan kemas' : '' }}</option>
                    </select>
                </label>
                <p v-else class="rounded-2xl bg-warn-soft px-4 py-3 text-base font-bold text-warn-ink">
                    Belum ada barang lain yang bisa dijadikan bahan. Bahan tidak boleh sama dengan barang hasilnya.
                    Contoh: untuk membersihkan jagung, buat dulu barang "Jagung Pipil" (kotor) dan "Jagung Bersih" di menu Barang.
                </p>
            </section>

            <section v-if="!isRepack" class="card flex flex-col gap-4 p-5">
                <MoneyInput v-model="form.cost_per_batch" label="Perkiraan biaya per 1 kali olah" optional hint="Upah, listrik, mesin. Hanya sebagai pengingat saat mencatat olah." :error="form.errors.cost_per_batch" />
                <BigInput v-model="form.note" label="Catatan" optional :error="form.errors.note" />
            </section>

            <BigButton type="submit" block size="large" :loading="form.processing">Simpan Resep</BigButton>
        </form>
    </div>
</template>
