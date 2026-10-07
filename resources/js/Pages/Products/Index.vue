<script setup>
/**
 * Daftar barang: cari, saring per kategori / stok, lalu ketuk untuk mengubah.
 */
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Package, Plus, Search, Sparkles, Trash2 } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import CatalogTabs from '@/Components/CatalogTabs.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { formatRupiah } from '@/composables/useRupiah';
import { useAuth } from '@/composables/useAuth';

defineOptions({ layout: AppLayout });

const props = defineProps({
    products: { type: Array, required: true },
    pagination: { type: Object, required: true },
    categories: { type: Array, required: true },
    filters: { type: Object, required: true },
    hasSamples: { type: Boolean, default: false },
    variantSummary: { type: [Object, Array], default: () => ({}) },
});

const { hasModule } = useAuth();
const search = ref(props.filters.q ?? '');
const confirmSamples = ref(false);
const deletingSamples = ref(false);
let timer = null;

function apply(changes) {
    router.get(
        route('products.index'),
        { ...props.filters, ...changes, page: undefined },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

watch(search, (value) => {
    clearTimeout(timer);
    timer = setTimeout(() => apply({ q: value || undefined }), 350);
});

const stockFilters = computed(() => [
    { value: null, label: 'Semua' },
    { value: 'low', label: 'Stok menipis' },
    { value: 'habis', label: 'Stok habis' },
    ...(hasModule('recipe') ? [{ value: 'bahan', label: 'Bahan baku' }] : []),
]);

function deleteSamples() {
    deletingSamples.value = true;
    router.delete(route('products.samples.destroy'), {
        onFinish: () => {
            deletingSamples.value = false;
            confirmSamples.value = false;
        },
    });
}

function goToPage(page) {
    router.get(route('products.index'), { ...props.filters, page }, { preserveState: true });
}

const chip = (active) => [
    'pressable flex min-h-12 items-center rounded-full border-2 px-4 text-base font-bold transition-colors',
    active ? 'border-primary bg-primary text-on-primary' : 'border-line bg-surface text-ink hover:border-ink-soft',
];
</script>

<template>
    <Head title="Barang" />
    <PageHeader title="Barang" :subtitle="`${pagination.total} barang & layanan`" help="products">
        <template #action>
            <BigButton :href="route('products.create')" class="hidden md:inline-flex">
                <Plus :size="24" aria-hidden="true" />
                Tambah Barang
            </BigButton>
        </template>
    </PageHeader>

    <CatalogTabs />

    <!-- Data contoh -->
    <div v-if="hasSamples" class="mb-5 flex flex-wrap items-center gap-3 rounded-3xl bg-accent-soft p-4">
        <Sparkles :size="28" class="shrink-0 text-accent-ink" aria-hidden="true" />
        <p class="min-w-48 flex-1 text-lg text-accent-ink">
            <strong>Ini data contoh</strong> supaya Anda bisa langsung mencoba. Hapus kalau sudah siap memasukkan barang sendiri.
        </p>
        <BigButton variant="secondary" @click="confirmSamples = true">
            <Trash2 :size="22" aria-hidden="true" />
            Hapus Data Contoh
        </BigButton>
    </div>

    <!-- Cari & saring -->
    <label class="relative mb-4 block">
        <span class="sr-only">Cari barang</span>
        <Search :size="24" class="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-ink-soft" aria-hidden="true" />
        <input
            v-model="search"
            type="search"
            placeholder="Cari nama, kode, atau barcode"
            class="min-h-touch w-full rounded-2xl border-2 border-line bg-surface pr-4 pl-13 text-lg text-ink placeholder:text-ink-soft/60 focus:border-focus focus:ring-4 focus:ring-focus/20 focus:outline-none"
        />
    </label>

    <div class="mb-3 flex flex-wrap gap-2" role="group" aria-label="Saring kategori">
        <button type="button" :class="chip(!filters.category)" @click="apply({ category: undefined })">Semua kategori</button>
        <button
            v-for="category in categories"
            :key="category.id"
            type="button"
            :class="chip(filters.category === category.id)"
            @click="apply({ category: category.id })"
        >
            {{ category.name }}
        </button>
    </div>
    <div class="mb-6 flex flex-wrap gap-2" role="group" aria-label="Saring stok">
        <button
            v-for="f in stockFilters"
            :key="String(f.value)"
            type="button"
            :class="chip((filters.filter ?? null) === f.value)"
            @click="apply({ filter: f.value ?? undefined })"
        >
            {{ f.label }}
        </button>
    </div>

    <!-- Daftar -->
    <ul v-if="products.length" class="grid gap-3 sm:grid-cols-2 2xl:grid-cols-3">
        <li v-for="product in products" :key="product.id">
            <Link :href="route('products.edit', product.id)" class="card pressable flex h-full items-center gap-4 p-3 hover:ring-2 hover:ring-primary/30">
                <img v-if="product.image_url" :src="product.image_url" alt="" loading="lazy" class="size-20 shrink-0 rounded-2xl object-cover" />
                <span v-else class="flex size-20 shrink-0 items-center justify-center rounded-2xl bg-surface-2 text-ink-soft">
                    <Package :size="32" aria-hidden="true" />
                </span>
                <span class="min-w-0 flex-1">
                    <!-- Nama boleh 2 baris: lebih mudah dibaca daripada terpotong "..." -->
                    <span class="line-clamp-2 font-display text-lg leading-snug font-extrabold wrap-break-word text-ink">{{ product.name }}</span>
                    <span class="flex flex-wrap items-center gap-x-2 text-base text-ink-soft">
                        {{ product.category ?? 'Tanpa kategori' }}
                        <span v-if="product.is_sample" class="rounded-full bg-accent-soft px-2 font-bold text-accent-ink">Contoh</span>
                    </span>
                    <span class="mt-1 flex flex-wrap items-center gap-2">
                        <span class="font-display text-lg font-extrabold text-primary-ink tabular-nums">
                            {{ formatRupiah(product.price) }}<span v-if="product.pricing_mode === 'per_weight'" class="text-base font-bold">/{{ product.unit }}</span>
                        </span>
                        <span
                            v-if="product.track_stock"
                            class="rounded-full px-2.5 text-base font-bold"
                            :class="product.is_out_of_stock ? 'bg-danger-soft text-danger-ink' : product.is_low_stock ? 'bg-warn-soft text-warn-ink' : 'bg-surface-2 text-ink-soft'"
                        >
                            {{ product.is_out_of_stock ? 'Habis' : `Stok ${product.stock}` }}
                        </span>
                        <span v-if="product.type === 'service'" class="rounded-full bg-info-soft px-2.5 text-base font-bold text-info-ink">Layanan</span>
                        <span v-if="product.sold_out_today" class="rounded-full bg-danger-soft px-2.5 text-base font-bold text-danger-ink">Habis hari ini</span>
                        <template v-if="variantSummary[product.id]">
                            <span class="rounded-full bg-surface-2 px-2.5 text-base font-bold text-ink-soft">{{ variantSummary[product.id].count }} varian · stok {{ variantSummary[product.id].stock }}</span>
                            <span v-if="variantSummary[product.id].low" class="rounded-full bg-warn-soft px-2.5 text-base font-bold text-warn-ink">{{ variantSummary[product.id].low }} menipis</span>
                        </template>
                    </span>
                </span>
                <ChevronRight :size="24" class="shrink-0 text-ink-soft" aria-hidden="true" />
            </Link>
        </li>
    </ul>

    <EmptyState
        v-else
        :title="filters.q || filters.category || filters.filter ? 'Tidak ada barang yang cocok' : 'Belum ada barang'"
        :message="filters.q || filters.category || filters.filter ? 'Coba kata pencarian lain atau ketuk Semua.' : 'Tambahkan barang pertama Anda supaya bisa mulai jualan.'"
    >
        <template #icon><Package :size="48" /></template>
        <BigButton :href="route('products.create')">
            <Plus :size="24" aria-hidden="true" />
            Tambah Barang
        </BigButton>
    </EmptyState>

    <!-- Halaman -->
    <div v-if="pagination.last > 1" class="mt-6 flex items-center justify-between gap-3">
        <BigButton variant="secondary" :disabled="pagination.current <= 1" @click="goToPage(pagination.current - 1)">
            <ChevronLeft :size="24" aria-hidden="true" /> Sebelumnya
        </BigButton>
        <span class="text-lg font-bold text-ink-soft">{{ pagination.current }} / {{ pagination.last }}</span>
        <BigButton variant="secondary" :disabled="pagination.current >= pagination.last" @click="goToPage(pagination.current + 1)">
            Berikutnya <ChevronRight :size="24" aria-hidden="true" />
        </BigButton>
    </div>

    <BigButton :href="route('products.create')" block size="large" class="mt-6 md:hidden">
        <Plus :size="28" aria-hidden="true" />
        Tambah Barang
    </BigButton>

    <ConfirmDialog
        v-model:open="confirmSamples"
        title="Hapus semua data contoh?"
        message="Semua barang dan kategori contoh akan dihapus. Barang yang sudah Anda ubah sendiri tidak ikut terhapus."
        confirm-text="Ya, Hapus Data Contoh"
        danger
        :loading="deletingSamples"
        @confirm="deleteSamples"
    />
</template>
