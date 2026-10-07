<script setup>
/** Tab Transaksi / Buka-Tutup Kasir / Laporan. */
import { Link } from '@inertiajs/vue3';

const tabs = [
    { label: 'Transaksi', href: route('sales.index'), match: 'sales.*' },
    { label: 'Kasir', href: route('shifts.index'), match: 'shifts.*' },
    { label: 'Laporan', href: route('reports.index'), match: 'reports.*' },
];
</script>

<template>
    <nav class="mb-6 grid auto-cols-[minmax(0,1fr)] grid-flow-col gap-1.5 rounded-2xl bg-surface-2 p-1.5" aria-label="Bagian transaksi">
        <Link
            v-for="tab in tabs"
            :key="tab.label"
            :href="tab.href"
            class="pressable flex min-h-touch items-center justify-center rounded-xl px-2 text-center text-lg leading-tight font-bold"
            :class="route().current(tab.match) ? 'bg-surface text-primary-ink shadow-card' : 'text-ink-soft hover:text-ink'"
        >
            {{ tab.label }}
        </Link>
    </nav>
</template>
