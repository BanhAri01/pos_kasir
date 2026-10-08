<script setup>
import { reactive, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { CheckCircle2, Copy, MessageCircle, Truck } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { formatRupiah } from '@/composables/useRupiah';

defineOptions({ layout: AppLayout });

const props = defineProps({
    groups: { type: Array, default: () => [] },
    business: { type: String, required: true },
    outlet: { type: String, default: '' },
});

const qty = reactive(Object.fromEntries(props.groups.flatMap((g) => g.items.map((i) => [i.product_id, i.suggest]))));
const copied = ref(null);

function lines(group) {
    return group.items.filter((i) => Number(qty[i.product_id]) > 0).map((i) => `• ${i.name}: ${qty[i.product_id]} ${i.unit}`.trim());
}

function message(group) {
    const greeting = group.supplier_id ? `Halo ${group.supplier}, ` : '';
    return `${greeting}${props.business}${props.outlet ? ` (${props.outlet})` : ''} mau pesan:\n${lines(group).join('\n')}\n\nMohon info total dan kapan bisa dikirim. Terima kasih 🙏`;
}

function estimate(group) {
    return group.items.reduce((s, i) => s + (Number(qty[i.product_id]) || 0) * i.unit_cost, 0);
}

const waUrl = (group) => `https://wa.me/${group.phone}?text=${encodeURIComponent(message(group))}`;

async function copy(group, index) {
    try {
        await navigator.clipboard.writeText(message(group));
        copied.value = index;
    } catch {
        copied.value = null;
    }
}
</script>

<template>
    <Head title="Daftar Belanja" />
    <div class="mx-auto max-w-3xl">
        <PageHeader title="Daftar Belanja" subtitle="Barang yang stoknya sudah di bawah batas minimal, dikelompokkan per pemasok." :back-href="route('stock.index')" />

        <section v-if="!groups.length" class="card flex flex-col items-center gap-3 p-8 text-center">
            <CheckCircle2 :size="44" class="text-primary-ink" aria-hidden="true" />
            <h2 class="text-2xl font-extrabold text-ink">Stok aman</h2>
            <p class="text-lg text-ink-soft">Belum ada barang yang stoknya menipis. Isi <b>stok minimal</b> di data barang supaya aplikasi bisa mengingatkan.</p>
        </section>

        <section v-for="(group, gi) in groups" :key="gi" class="card mb-6 p-5">
            <div class="mb-3 flex items-center gap-3">
                <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-accent-soft text-accent-ink"><Truck :size="24" aria-hidden="true" /></span>
                <span class="min-w-0 flex-1">
                    <span class="block text-xl font-extrabold text-ink">{{ group.supplier }}</span>
                    <span class="block text-base text-ink-soft">{{ group.items.length }} barang · perkiraan {{ formatRupiah(estimate(group)) }}</span>
                </span>
            </div>

            <ul class="mb-4 divide-y divide-line">
                <li v-for="item in group.items" :key="item.product_id" class="flex items-center gap-3 py-3">
                    <span class="min-w-0 flex-1">
                        <span class="block text-lg font-bold text-ink">{{ item.name }}</span>
                        <span class="block text-base text-danger-ink">Sisa {{ item.stock }} {{ item.unit }} (minimal {{ item.min }})</span>
                    </span>
                    <label class="flex items-center gap-2">
                        <span class="sr-only">Jumlah pesan {{ item.name }}</span>
                        <input v-model.number="qty[item.product_id]" type="number" min="0" inputmode="numeric" class="min-h-touch w-24 rounded-2xl border-2 border-line bg-surface px-3 text-right text-lg font-bold text-ink" />
                        <span class="w-14 text-base text-ink-soft">{{ item.unit }}</span>
                    </label>
                </li>
            </ul>

            <div class="grid gap-3 sm:grid-cols-2">
                <a v-if="group.phone" :href="waUrl(group)" target="_blank" rel="noopener" class="pressable flex min-h-touch items-center justify-center gap-2 rounded-2xl bg-primary px-4 text-lg font-bold text-on-primary">
                    <MessageCircle :size="22" aria-hidden="true" /> Pesan lewat WhatsApp
                </a>
                <button type="button" class="pressable flex min-h-touch items-center justify-center gap-2 rounded-2xl border-2 border-line px-4 text-lg font-bold text-ink" @click="copy(group, gi)">
                    <Copy :size="22" aria-hidden="true" /> {{ copied === gi ? 'Tersalin' : 'Salin daftar' }}
                </button>
            </div>
            <p v-if="!group.supplier_id" class="mt-3 text-base text-ink-soft">
                Pemasok diambil dari belanja terakhir. Catat belanja di
                <Link :href="route('purchases.index')" class="font-bold text-primary-ink underline">Belanja ke Pemasok</Link> supaya daftar ini terkelompok otomatis.
            </p>
        </section>
    </div>
</template>
