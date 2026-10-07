<script setup>
/**
 * Stok di outlet aktif. Barang habis (merah) & menipis (kuning) tampil paling atas.
 */
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowDownToLine, ArrowUpFromLine, ClipboardCheck, Package, Search, Truck } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import CatalogTabs from '@/Components/CatalogTabs.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    products: { type: Array, required: true },
    summary: { type: Object, required: true },
    q: { type: String, default: '' },
});

const search = ref(props.q);
let timer = null;
watch(search, (value) => {
    clearTimeout(timer);
    timer = setTimeout(() => router.get(route('stock.index'), { q: value || undefined }, { preserveState: true, replace: true }), 350);
});

const actions = [
    { label: 'Stok Masuk', text: 'Barang datang', href: route('stock.adjust', 'masuk'), icon: ArrowDownToLine, tone: 'bg-primary-soft text-primary-ink' },
    { label: 'Stok Keluar', text: 'Rusak / hilang', href: route('stock.adjust', 'keluar'), icon: ArrowUpFromLine, tone: 'bg-danger-soft text-danger-ink' },
    { label: 'Hitung Stok', text: 'Cocokkan dengan rak', href: route('stock.opname'), icon: ClipboardCheck, tone: 'bg-info-soft text-info-ink' },
    { label: 'Kirim Stok', text: 'Ke cabang / gudang lain', href: route('stock.transfers'), icon: Truck, tone: 'bg-accent-soft text-accent-ink' },
];
</script>

<template>
    <Head title="Stok" />
    <PageHeader title="Stok" :subtitle="`${summary.total} barang dihitung stoknya`" help="stock" />
    <CatalogTabs />

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <Link v-for="a in actions" :key="a.label" :href="a.href" class="card pressable flex flex-col gap-2 p-4 hover:ring-2 hover:ring-primary/30">
            <span class="flex size-12 items-center justify-center rounded-2xl" :class="a.tone">
                <component :is="a.icon" :size="26" aria-hidden="true" />
            </span>
            <span class="font-display text-lg leading-tight font-extrabold text-ink">{{ a.label }}</span>
            <span class="text-base text-ink-soft">{{ a.text }}</span>
        </Link>
    </div>

    <div v-if="summary.out || summary.low" class="mb-5 flex flex-wrap gap-2">
        <span v-if="summary.out" class="rounded-full bg-danger-soft px-4 py-2 text-lg font-bold text-danger-ink">{{ summary.out }} barang habis</span>
        <span v-if="summary.low" class="rounded-full bg-warn-soft px-4 py-2 text-lg font-bold text-warn-ink">{{ summary.low }} barang menipis</span>
    </div>

    <label class="relative mb-4 block">
        <span class="sr-only">Cari barang</span>
        <Search :size="24" class="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-ink-soft" aria-hidden="true" />
        <input
            v-model="search"
            type="search"
            placeholder="Cari barang"
            class="min-h-touch w-full rounded-2xl border-2 border-line bg-surface pr-4 pl-13 text-lg text-ink placeholder:text-ink-soft/60 focus:border-focus focus:outline-none"
        />
    </label>

    <ul v-if="products.length" class="card divide-y divide-line overflow-hidden">
        <li v-for="product in products" :key="product.id">
            <Link :href="route('stock.history', product.id)" class="flex min-h-touch-lg items-center gap-3 px-4 py-3 hover:bg-surface-2">
                <span
                    class="size-3 shrink-0 rounded-full"
                    :class="product.is_out_of_stock ? 'bg-danger' : product.is_low_stock ? 'bg-accent' : 'bg-primary'"
                    aria-hidden="true"
                />
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-lg font-bold text-ink">{{ product.name }}</span>
                    <span class="block text-base text-ink-soft">
                        {{ product.is_out_of_stock ? 'Habis' : product.is_low_stock ? 'Menipis' : 'Aman' }}
                        <template v-if="product.min_stock"> · tanda di {{ product.min_stock }}</template>
                        <template v-if="product.location"> · {{ product.location }}</template>
                    </span>
                </span>
                <span
                    class="font-display text-2xl font-extrabold tabular-nums"
                    :class="product.is_out_of_stock ? 'text-danger-ink' : product.is_low_stock ? 'text-warn-ink' : 'text-ink'"
                >
                    {{ product.stock }}
                </span>
                <span class="w-20 text-base text-ink-soft">
                    {{ product.unit }}
                    <span v-if="product.pack_equivalent !== null" class="block leading-tight">≈ {{ product.pack_equivalent }} {{ product.pack_name }}</span>
                </span>
            </Link>
        </li>
    </ul>

    <EmptyState v-else title="Belum ada barang yang dihitung stoknya" message="Nyalakan 'Hitung stok' di form barang supaya stoknya tercatat di sini.">
        <template #icon><Package :size="48" /></template>
    </EmptyState>
</template>
