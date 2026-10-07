<script setup>
/** Tab di atas halaman Barang / Stok / Kategori, supaya mudah berpindah. */
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useAuth } from '@/composables/useAuth';

const { can } = useAuth();

const tabs = computed(() =>
    [
        { label: 'Barang', href: route('products.index'), active: route().current('products.*'), show: can('manage_products') },
        { label: 'Stok', href: route('stock.index'), active: route().current('stock.*'), show: can('manage_stock') },
        { label: 'Kategori', href: route('categories.index'), active: route().current('categories.*'), show: can('manage_products') },
    ].filter((t) => t.show),
);
</script>

<template>
    <nav v-if="tabs.length > 1" class="mb-6 grid auto-cols-[minmax(0,1fr)] grid-flow-col gap-1.5 rounded-2xl bg-surface-2 p-1.5" aria-label="Bagian katalog">
        <Link
            v-for="tab in tabs"
            :key="tab.label"
            :href="tab.href"
            class="pressable flex min-h-touch items-center justify-center rounded-xl text-lg font-bold transition-colors"
            :class="tab.active ? 'bg-surface text-primary-ink shadow-card' : 'text-ink-soft hover:text-ink'"
            :aria-current="tab.active ? 'page' : undefined"
        >
            {{ tab.label }}
        </Link>
    </nav>
</template>
