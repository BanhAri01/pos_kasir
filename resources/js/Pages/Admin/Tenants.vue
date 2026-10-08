<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronRight, Search } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import { formatRupiah } from '@/composables/useRupiah';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    tenants: { type: Object, required: true },
    filters: { type: Object, required: true },
    stats: { type: Object, required: true },
});

const q = ref(props.filters.q);
const status = ref(props.filters.status);
let timer = null;

function reload() {
    router.get(route('admin.tenants.index'), { q: q.value || undefined, status: status.value === 'semua' ? undefined : status.value }, { preserveState: true, replace: true });
}

watch(q, () => {
    clearTimeout(timer);
    timer = setTimeout(reload, 350);
});
watch(status, reload);

function badge(t) {
    if (t.suspended) return ['Dibekukan', 'bg-danger-soft text-danger-ink'];
    if (!t.has_access) return ['Habis', 'bg-danger-soft text-danger-ink'];
    if (t.in_grace) return ['Masa tenggang', 'bg-warn-soft text-warn-ink'];
    if (t.unlimited) return ['Tanpa batas', 'bg-primary-soft text-primary-ink'];
    if (t.on_trial) return [`Coba · ${t.days_left} hari`, 'bg-accent-soft text-accent-ink'];
    return [`${t.plan_label} · ${t.days_left} hari`, 'bg-primary-soft text-primary-ink'];
}
</script>

<template>
    <Head title="Admin" />
    <h1 class="mb-4 font-display text-3xl font-extrabold text-ink">Semua usaha</h1>

    <div class="mb-6 grid grid-cols-2 gap-3 md:grid-cols-4">
        <div class="card p-4"><p class="text-base text-ink-soft">Total usaha</p><p class="font-display text-3xl font-extrabold text-ink">{{ stats.total }}</p></div>
        <div class="card p-4"><p class="text-base text-ink-soft">Sedang coba</p><p class="font-display text-3xl font-extrabold text-ink">{{ stats.trial }}</p></div>
        <div class="card p-4"><p class="text-base text-ink-soft">Berlangganan</p><p class="font-display text-3xl font-extrabold text-ink">{{ stats.active }}</p></div>
        <div class="card p-4"><p class="text-base text-ink-soft">Pendapatan bulan ini</p><p class="font-display text-2xl font-extrabold text-ink">{{ formatRupiah(stats.revenue_month) }}</p></div>
    </div>

    <div class="mb-4 flex flex-col gap-3">
        <label class="relative block">
            <span class="sr-only">Cari usaha</span>
            <Search :size="22" class="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-ink-soft" aria-hidden="true" />
            <input v-model="q" type="search" placeholder="Cari nama usaha, pemilik, atau no HP" class="min-h-touch w-full rounded-2xl border-2 border-line bg-surface pr-4 pl-12 text-lg text-ink" />
        </label>
        <SegmentedControl
            v-model="status"
            label="Saring"
            :options="[{ value: 'semua', label: 'Semua' }, { value: 'coba', label: 'Coba' }, { value: 'aktif', label: 'Aktif' }, { value: 'beku', label: 'Beku' }]"
        />
    </div>

    <p v-if="!tenants.data.length" class="card p-5 text-lg text-ink-soft">Tidak ada usaha yang cocok.</p>
    <ul v-else class="card divide-y divide-line">
        <li v-for="t in tenants.data" :key="t.id">
            <Link :href="route('admin.tenants.show', t.id)" class="pressable flex items-center gap-3 px-4 py-3">
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-lg font-extrabold text-ink">{{ t.name }}</span>
                    <span class="block truncate text-base text-ink-soft">{{ t.type }} · {{ t.owner }} · {{ t.phone }}</span>
                </span>
                <span class="shrink-0 rounded-full px-3 py-1 text-sm font-bold" :class="badge(t)[1]">{{ badge(t)[0] }}</span>
                <ChevronRight :size="24" class="shrink-0 text-ink-soft" aria-hidden="true" />
            </Link>
        </li>
    </ul>

    <nav v-if="tenants.last_page > 1" class="mt-4 flex flex-wrap gap-2" aria-label="Halaman">
        <template v-for="link in tenants.links" :key="link.label">
            <Link
                v-if="link.url"
                :href="link.url"
                class="pressable min-h-touch min-w-touch rounded-2xl px-4 py-2 text-lg font-bold"
                :class="link.active ? 'bg-primary text-on-primary' : 'bg-surface text-ink'"
                preserve-scroll
            ><span v-html="link.label" /></Link>
        </template>
    </nav>
</template>
