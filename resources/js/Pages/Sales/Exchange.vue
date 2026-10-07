<script setup>
/**
 * Tukar barang (misalnya tukar ukuran), langkah demi langkah:
 *   1. Barang yang dikembalikan pembeli
 *   2. Barang penggantinya (tombol cepat: ukuran/warna lain dari model yang sama)
 *   3. Selisih: pembeli menambah, atau uang dikembalikan
 * Stok barang lama kembali, stok barang baru berkurang, otomatis.
 */
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Repeat, Search, X } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import BigInput from '@/Components/ui/BigInput.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import QtyInput from '@/Components/ui/QtyInput.vue';
import { formatRupiah } from '@/composables/useRupiah';
import { num } from '@/composables/useQty';

defineOptions({ layout: AppLayout });

const props = defineProps({
    sale: { type: Object, required: true },
    products: { type: Array, required: true },
    paymentMethods: { type: Array, required: true },
});

const returnQty = ref(Object.fromEntries(props.sale.items.map((i) => [i.id, props.sale.items.length === 1 ? String(i.refundable_qty).replace('.', ',') : '0'])));
const replacements = ref([]);
const query = ref('');

const form = useForm({ returns: [], items: [], payment_method_id: props.paymentMethods.find((m) => m.type === 'cash')?.id ?? props.paymentMethods[0]?.id ?? null, reason: '' });

const returning = computed(() => props.sale.items.filter((i) => num(returnQty.value[i.id]) > 0));
const returnValue = computed(() => returning.value.reduce((s, i) => s + Math.round(i.unit_value * Math.min(num(returnQty.value[i.id]), i.refundable_qty)), 0));
const productById = (id) => props.products.find((p) => p.id === id);
const newValue = computed(() => replacements.value.reduce((s, r) => s + Math.round(productById(r.product_id).price * num(r.qty)), 0));
const difference = computed(() => newValue.value - returnValue.value);

/** Ukuran / warna lain dari model yang sama dengan barang yang dikembalikan. */
const siblings = computed(() => {
    const parents = [...new Set(returning.value.map((i) => i.parent_id).filter(Boolean))];
    return props.products.filter((p) => p.parent_id && parents.includes(p.parent_id) && !returning.value.some((i) => i.name === p.name));
});

const matches = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return [];
    return props.products.filter((p) => p.name.toLowerCase().includes(q) || p.barcode === q || p.code?.toLowerCase() === q).slice(0, 8);
});

function addReplacement(product) {
    const existing = replacements.value.find((r) => r.product_id === product.id);
    if (existing) existing.qty = String(num(existing.qty) + 1);
    else replacements.value.push({ product_id: product.id, qty: '1' });
    query.value = '';
}

function save() {
    form.transform((data) => ({
        ...data,
        returns: returning.value.map((i) => ({ sale_item_id: i.id, qty: returnQty.value[i.id] })),
        items: replacements.value.map((r) => ({ product_id: r.product_id, qty: r.qty })),
    })).post(route('sales.exchange.store', props.sale.uuid));
}

const chip = 'pressable flex min-h-touch flex-col items-start justify-center rounded-2xl border-2 border-line px-4 py-2 text-left hover:border-primary';
</script>

<template>
    <Head title="Tukar Barang" />
    <div class="mx-auto max-w-3xl">
        <PageHeader title="Tukar Barang" :subtitle="`Nota ${sale.number}${sale.customer ? ` · ${sale.customer}` : ''}`" :back-href="route('sales.show', sale.uuid)" />

        <form class="flex flex-col gap-5" @submit.prevent="save">
            <!-- 1 -->
            <section class="card flex flex-col gap-3 p-5">
                <h2 class="text-xl font-extrabold text-ink"><span class="text-ink-soft">1.</span> Barang yang dikembalikan</h2>
                <div v-for="item in sale.items" :key="item.id" class="rounded-2xl border-2 border-line p-3">
                    <p class="mb-2 text-lg font-bold text-ink">{{ item.name }} <span class="font-semibold text-ink-soft">(maks {{ item.refundable_qty }})</span></p>
                    <QtyInput v-model="returnQty[item.id]" :label="`Jumlah ${item.name} dikembalikan`" :unit="item.unit" compact />
                </div>
                <p v-if="form.errors.returns || form.errors.items" role="alert" class="text-lg font-bold text-danger-ink">{{ form.errors.returns || form.errors.items }}</p>
            </section>

            <!-- 2 -->
            <section class="card flex flex-col gap-3 p-5">
                <h2 class="text-xl font-extrabold text-ink"><span class="text-ink-soft">2.</span> Barang pengganti</h2>

                <div v-if="siblings.length">
                    <p class="mb-2 text-lg text-ink-soft">Ukuran / warna lain:</p>
                    <div class="flex flex-wrap gap-2">
                        <button v-for="p in siblings" :key="p.id" type="button" :class="chip" @click="addReplacement(p)">
                            <span class="text-lg font-extrabold text-ink">{{ p.name }}</span>
                            <span class="text-base text-ink-soft">{{ formatRupiah(p.price) }}<template v-if="p.stock !== null"> · stok {{ p.stock }}</template></span>
                        </button>
                    </div>
                </div>

                <div class="relative">
                    <label class="flex min-h-touch items-center gap-3 rounded-2xl border-2 border-line bg-surface px-4 focus-within:border-focus">
                        <Search :size="22" class="shrink-0 text-ink-soft" aria-hidden="true" />
                        <span class="sr-only">Cari barang lain</span>
                        <input v-model="query" type="search" placeholder="Cari barang lain atau scan barcode" class="min-w-0 flex-1 bg-transparent text-lg text-ink focus:outline-none" />
                    </label>
                    <ul v-if="matches.length" class="absolute inset-x-0 top-full z-10 mt-1 overflow-hidden rounded-2xl border-2 border-line bg-surface shadow-float">
                        <li v-for="p in matches" :key="p.id">
                            <button type="button" class="flex min-h-touch w-full items-center justify-between gap-3 px-4 text-left text-lg hover:bg-surface-2" @click="addReplacement(p)">
                                <span class="min-w-0 truncate font-bold text-ink">{{ p.name }}</span>
                                <span class="shrink-0 text-base text-ink-soft">{{ formatRupiah(p.price) }}</span>
                            </button>
                        </li>
                    </ul>
                </div>

                <ul v-if="replacements.length" class="divide-y divide-line">
                    <li v-for="(r, i) in replacements" :key="r.product_id" class="flex items-end gap-2 py-3">
                        <div class="min-w-0 flex-1">
                            <p class="mb-2 text-lg font-bold text-ink">{{ productById(r.product_id).name }} · {{ formatRupiah(productById(r.product_id).price) }}</p>
                            <QtyInput v-model="r.qty" :label="`Jumlah ${productById(r.product_id).name}`" :min="1" compact />
                        </div>
                        <button type="button" class="pressable flex size-12 shrink-0 items-center justify-center rounded-xl text-danger-ink" :aria-label="`Hapus ${productById(r.product_id).name}`" @click="replacements.splice(i, 1)"><X :size="24" /></button>
                    </li>
                </ul>
                <p v-else class="text-lg text-ink-soft">Belum ada barang pengganti.</p>
            </section>

            <!-- 3 -->
            <section class="card flex flex-col gap-3 p-5">
                <h2 class="text-xl font-extrabold text-ink"><span class="text-ink-soft">3.</span> Selisih</h2>
                <p class="flex justify-between text-lg text-ink"><span>Nilai barang dikembalikan</span><span class="tabular-nums">{{ formatRupiah(returnValue) }}</span></p>
                <p class="flex justify-between text-lg text-ink"><span>Harga barang pengganti</span><span class="tabular-nums">{{ formatRupiah(newValue) }}</span></p>
                <p class="rounded-2xl px-4 py-3 text-xl font-extrabold" :class="difference > 0 ? 'bg-warn-soft text-warn-ink' : difference < 0 ? 'bg-info-soft text-info-ink' : 'bg-primary-soft text-primary-ink'">
                    {{ difference > 0 ? `Pembeli menambah ${formatRupiah(difference)}` : difference < 0 ? `Kembalikan uang ${formatRupiah(-difference)}` : 'Harga sama, tidak ada selisih' }}
                </p>
                <p class="text-base text-ink-soft">Angka pastinya dihitung ulang saat disimpan (termasuk pajak & promo yang berlaku).</p>
                <label v-if="difference > 0" class="block">
                    <span class="mb-2 block text-lg font-bold text-ink">Kekurangan dibayar dengan</span>
                    <select v-model="form.payment_method_id" class="min-h-touch w-full rounded-2xl border-2 border-line bg-surface px-4 text-lg text-ink">
                        <option v-for="m in paymentMethods" :key="m.id" :value="m.id">{{ m.name }}</option>
                    </select>
                </label>
                <BigInput v-model="form.reason" label="Alasan" optional placeholder="Contoh: ukuran kekecilan" :error="form.errors.reason" />
                <p v-if="form.errors.shift" role="alert" class="rounded-2xl bg-danger-soft p-4 text-lg font-bold text-danger-ink">{{ form.errors.shift }}</p>
            </section>

            <BigButton type="submit" block size="large" :loading="form.processing" :disabled="!returning.length || !replacements.length">
                <Repeat :size="24" aria-hidden="true" /> Simpan Tukar Barang
            </BigButton>
        </form>
    </div>
</template>
