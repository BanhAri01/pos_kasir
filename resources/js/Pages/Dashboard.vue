<script setup>
/**
 * Beranda: tombol "Mulai Jualan", ringkasan hari ini (pemilik/manajer), dan hal yang perlu perhatian.
 * Angka dimuat belakangan (deferred) supaya halaman langsung tampil walau internet lambat.
 */
import { computed } from 'vue';
import { Deferred, Head, Link } from '@inertiajs/vue3';
import { BellRing, Calculator, ChartColumn, ChevronRight, Palette, SlidersHorizontal, Store, TriangleAlert, Users } from 'lucide-vue-next';
import BarChart from '@/Components/BarChart.vue';
import StatCard from '@/Components/StatCard.vue';
import WarehouseFlow from '@/Components/WarehouseFlow.vue';
import CaduceusMark from '@/Components/ui/CaduceusMark.vue';
import GreekKey from '@/Components/ui/GreekKey.vue';
import { formatRupiah } from '@/composables/useRupiah';
import AppLayout from '@/Layouts/AppLayout.vue';
import HelpButton from '@/Components/ui/HelpButton.vue';
import WelcomeTour from '@/Components/WelcomeTour.vue';
import { useAuth } from '@/composables/useAuth';

defineOptions({ layout: AppLayout });

const props = defineProps({
    greeting: { type: String, required: true },
    today: { type: String, required: true },
    conflictCount: { type: Number, default: 0 },
    stats: { type: Object, default: null },
    mine: { type: Object, default: null },
    alerts: { type: Array, default: null },
});

const weekPoints = computed(() => (props.stats?.week ?? []).map((p) => ({ label: p.label, value: p.total, sub: `${p.transactions} transaksi` })));
const ALERT_TONE = { warn: 'bg-warn-soft text-warn-ink', info: 'bg-info-soft text-info-ink', danger: 'bg-danger-soft text-danger-ink' };

const { user, can, hasModule } = useAuth();

const shortcuts = computed(() =>
    [
        { label: 'Karyawan', text: 'Tambah karyawan & PIN', href: route('staff.index'), icon: Users, show: can('manage_staff') },
        { label: 'Outlet', text: 'Tempat usaha Anda', href: route('outlets.index'), icon: Store, show: can('manage_outlets') },
        { label: 'Atur Fitur', text: 'Pilih fitur yang dipakai', href: route('modules.index'), icon: SlidersHorizontal, show: can('manage_modules') },
        { label: 'Tampilan', text: 'Ukuran huruf & mode gelap', href: route('preferences.edit'), icon: Palette, show: true },
    ].filter((s) => s.show),
);
</script>

<template>
    <Head title="Beranda" />

    <section class="flex items-start justify-between gap-4">
        <div>
            <p class="text-lg text-ink-soft">{{ today }}</p>
            <h1 class="mt-1 text-3xl font-extrabold text-ink sm:text-4xl">{{ greeting }}, {{ user.name }}!</h1>
        </div>
        <HelpButton topic="dashboard" class="shrink-0" />
    </section>

    <!-- Catatan dari kasir offline -->
    <Link
        v-if="conflictCount > 0"
        :href="route('conflicts.index')"
        class="pressable mt-6 flex items-center gap-3 rounded-3xl bg-warn-soft p-5 text-warn-ink"
    >
        <TriangleAlert :size="30" class="shrink-0" aria-hidden="true" />
        <span class="flex-1 text-lg font-bold">Ada {{ conflictCount }} catatan dari kasir yang perlu dicek (misalnya stok minus).</span>
        <ChevronRight :size="26" aria-hidden="true" />
    </Link>

    <!-- Tombol utama: mulai jualan (bernuansa merek) -->
    <a
        v-if="can('use_pos')"
        :href="route('pos.show')"
        class="pressable relative mt-6 flex items-center gap-5 overflow-hidden rounded-3xl bg-brand p-6 text-white shadow-float sm:p-8"
    >
        <CaduceusMark class="absolute -right-6 -bottom-10 size-52 text-accent opacity-15" />
        <GreekKey class="absolute inset-x-0 bottom-0 text-accent/50" :height="10" />
        <span class="relative flex size-16 shrink-0 items-center justify-center rounded-2xl bg-white/15">
            <Calculator :size="36" aria-hidden="true" />
        </span>
        <span class="relative">
            <span class="block font-display text-2xl font-extrabold sm:text-3xl">Mulai Jualan</span>
            <span class="block text-lg text-white/85">Buka layar kasir</span>
        </span>
    </a>

    <!-- Alur kerja gudang (usaha gudang / distributor) -->
    <section v-if="can('manage_stock') && (hasModule('repack') || hasModule('production') || hasModule('weighed_receiving'))" class="mt-8">
        <div class="mb-3 flex items-center justify-between gap-3">
            <h2 class="text-xl font-extrabold text-ink">Kerja di gudang</h2>
            <Link :href="route('warehouse.dashboard')" class="inline-flex min-h-12 items-center gap-1 text-lg font-bold text-primary-ink">Beranda Gudang <ChevronRight :size="20" aria-hidden="true" /></Link>
        </div>
        <WarehouseFlow />
    </section>

    <!-- Penjualan saya (kasir) -->
    <section v-if="mine" class="mt-6 grid grid-cols-2 gap-3">
        <StatCard label="Penjualan saya hari ini" :value="mine.total" />
        <StatCard label="Transaksi saya" :value="mine.transactions" :money="false" />
    </section>

    <!-- Ringkasan hari ini (pemilik / manajer) -->
    <section v-if="can('view_reports')" class="mt-8">
        <div class="mb-3 flex items-center justify-between gap-3">
            <h2 class="text-xl font-extrabold text-ink">Hari ini</h2>
            <Link :href="route('reports.index')" class="inline-flex min-h-12 items-center gap-1 text-lg font-bold text-primary-ink">
                <ChartColumn :size="20" aria-hidden="true" /> Lihat laporan
            </Link>
        </div>
        <Deferred data="stats">
            <template #fallback>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3" aria-busy="true">
                    <div v-for="i in 3" :key="i" class="card h-28 animate-pulse bg-surface-2" />
                </div>
            </template>
            <div v-if="stats" class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <StatCard label="Uang masuk" :value="stats.summary.total_collected" :change="stats.summary.change.total_collected" hint="dari kemarin" big />
                <StatCard label="Transaksi" :value="stats.summary.transactions" :money="false" :hint="`rata-rata ${formatRupiah(stats.summary.average)}`" />
                <StatCard label="Untung kotor" :value="stats.summary.gross_profit" :tone="stats.summary.gross_profit < 0 ? 'negative' : 'default'" :hint="`margin ${stats.summary.margin}%`" />
            </div>
            <div v-if="stats" class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-[3fr_2fr]">
                <div class="card min-w-0 p-5">
                    <BarChart :points="weekPoints" label="Penjualan 7 hari terakhir" :height="150" highlight-last />
                </div>
                <div class="card min-w-0 p-5">
                    <p class="mb-3 text-base text-ink-soft">Paling laku hari ini</p>
                    <ol v-if="stats.topProducts.length" class="flex flex-col gap-2">
                        <li v-for="(p, i) in stats.topProducts" :key="p.product_id ?? p.name" class="flex items-center justify-between gap-3 text-lg">
                            <span class="min-w-0 truncate"><span class="text-ink-soft">{{ i + 1 }}.</span> <span class="font-bold text-ink">{{ p.name }}</span></span>
                            <span class="shrink-0 text-ink-soft">{{ Number(p.qty).toLocaleString('id-ID') }} {{ p.unit }}</span>
                        </li>
                    </ol>
                    <p v-else class="text-lg text-ink-soft">Belum ada penjualan hari ini. Semangat! 💪</p>
                </div>
            </div>
        </Deferred>
    </section>

    <!-- Perlu perhatian -->
    <Deferred data="alerts">
        <template #fallback><span /></template>
        <section v-if="alerts?.length" class="mt-8">
            <h2 class="mb-3 flex items-center gap-2 text-xl font-extrabold text-ink"><BellRing :size="22" aria-hidden="true" /> Perlu perhatian</h2>
            <ul class="grid gap-3 sm:grid-cols-2">
                <li v-for="a in alerts" :key="a.key">
                    <Link :href="a.href" class="pressable flex min-h-touch-lg items-center gap-3 rounded-2xl p-4 text-lg font-bold" :class="ALERT_TONE[a.tone]">
                        <span class="flex-1">{{ a.text }}</span>
                        <ChevronRight :size="24" aria-hidden="true" />
                    </Link>
                </li>
            </ul>
        </section>
    </Deferred>

    <section class="mt-8">
        <h2 class="mb-3 text-xl font-extrabold text-ink">Pengaturan cepat</h2>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <Link
                v-for="item in shortcuts"
                :key="item.label"
                :href="item.href"
                class="card pressable flex items-center gap-4 p-4 hover:ring-2 hover:ring-primary/30 lg:flex-col lg:items-start lg:p-5"
            >
                <span class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-primary-soft text-primary-ink">
                    <component :is="item.icon" :size="28" aria-hidden="true" />
                </span>
                <span class="flex-1">
                    <span class="block font-display text-lg font-extrabold text-ink">{{ item.label }}</span>
                    <span class="block text-base text-ink-soft">{{ item.text }}</span>
                </span>
                <ChevronRight :size="26" class="text-ink-soft lg:hidden" aria-hidden="true" />
            </Link>
        </div>
    </section>

    <WelcomeTour :user-name="user.name" />
</template>
