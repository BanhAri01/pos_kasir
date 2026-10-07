<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronRight, Plus, Search, UserRound } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { formatRupiah } from '@/composables/useRupiah';

defineOptions({ layout: AppLayout });

const props = defineProps({
    customers: { type: Array, required: true },
    pagination: { type: Object, required: true },
    q: { type: String, default: '' },
});

const search = ref(props.q);
let timer = null;
watch(search, (v) => {
    clearTimeout(timer);
    timer = setTimeout(() => router.get(route('customers.index'), { q: v || undefined }, { preserveState: true, replace: true }), 350);
});
</script>

<template>
    <Head title="Pelanggan" />
    <PageHeader title="Pelanggan" :subtitle="`${pagination.total} pelanggan`" :back-href="route('more')" help="customers">
        <template #action>
            <BigButton :href="route('customers.create')" class="hidden md:inline-flex"><Plus :size="24" aria-hidden="true" /> Tambah Pelanggan</BigButton>
        </template>
    </PageHeader>

    <label class="relative mb-4 block">
        <span class="sr-only">Cari pelanggan</span>
        <Search :size="24" class="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-ink-soft" aria-hidden="true" />
        <input
            v-model="search"
            type="search"
            placeholder="Cari nama atau no HP"
            class="min-h-touch w-full rounded-2xl border-2 border-line bg-surface pr-4 pl-13 text-lg text-ink focus:border-focus focus:outline-none"
        />
    </label>

    <ul v-if="customers.length" class="card divide-y divide-line overflow-hidden">
        <li v-for="c in customers" :key="c.id">
            <Link :href="route('customers.show', c.id)" class="flex min-h-touch-lg items-center gap-3 px-4 py-3 hover:bg-surface-2">
                <span class="flex size-12 shrink-0 items-center justify-center rounded-full bg-primary-soft text-primary-ink">
                    <UserRound :size="24" aria-hidden="true" />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-lg font-bold text-ink">{{ c.name }}</span>
                    <span class="block text-base text-ink-soft">{{ c.phone || 'Tanpa no HP' }} · {{ c.sales_count }}x belanja</span>
                </span>
                <span class="text-right font-display text-lg font-extrabold text-ink tabular-nums">{{ formatRupiah(c.sales_total) }}</span>
                <ChevronRight :size="24" class="shrink-0 text-ink-soft" aria-hidden="true" />
            </Link>
        </li>
    </ul>
    <EmptyState v-else :title="q ? 'Pelanggan tidak ditemukan' : 'Belum ada pelanggan'" message="Pelanggan juga bisa ditambahkan langsung dari layar kasir.">
        <template #icon><UserRound :size="48" /></template>
    </EmptyState>

    <BigButton :href="route('customers.create')" block size="large" class="mt-6 md:hidden"><Plus :size="28" aria-hidden="true" /> Tambah Pelanggan</BigButton>
</template>
