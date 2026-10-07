<script setup>
/**
 * Alur kerja gudang dalam 5 langkah, dengan tombol besar dan bahasa sederhana:
 * Terima Barang -> Olah / Giling -> Kemas Ulang -> Jual -> Cek Stok.
 * Langkah yang modulnya mati tidak ditampilkan.
 */
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Boxes, Calculator, Factory, Package, Truck } from 'lucide-vue-next';
import { useAuth } from '@/composables/useAuth';

const { can, hasModule } = useAuth();

const steps = computed(() =>
    [
        { key: 'receive', label: 'Terima Barang', text: 'Timbang & catat barang datang', href: route('purchases.create'), icon: Truck, show: hasModule('supplier_purchase') && can('manage_stock') },
        { key: 'produce', label: 'Olah / Giling', text: 'Giling atau racik pakan', href: route('warehouse.production'), icon: Factory, show: hasModule('production') && can('manage_stock') },
        { key: 'repack', label: 'Kemas Ulang', text: 'Curah jadi karungan', href: route('warehouse.repack'), icon: Package, show: hasModule('repack') && can('manage_stock') },
        { key: 'sell', label: 'Jual', text: 'Buka layar kasir', href: route('pos.show'), icon: Calculator, external: true, show: can('use_pos') },
        // Dari Beranda: ke ringkasan semua gudang. Dari Beranda Gudang: ke daftar stok gudang ini.
        route().current('warehouse.dashboard')
            ? { key: 'stock', label: 'Cek Stok', text: 'Stok & hitung stok', href: route('stock.index'), icon: Boxes, show: can('manage_stock') }
            : { key: 'stock', label: 'Cek Stok', text: 'Stok di semua gudang', href: route('warehouse.dashboard'), icon: Boxes, show: can('manage_stock') },
    ].filter((s) => s.show),
);
</script>

<template>
    <ol class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5" aria-label="Alur kerja gudang">
        <li v-for="(step, i) in steps" :key="step.key">
            <component
                :is="step.external ? 'a' : Link"
                :href="step.href"
                class="card pressable flex h-full min-h-touch-lg flex-col gap-2 p-4 hover:ring-2 hover:ring-primary/30"
            >
                <span class="flex items-center gap-3">
                    <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-primary-soft text-primary-ink">
                        <component :is="step.icon" :size="26" aria-hidden="true" />
                    </span>
                    <span class="font-display text-2xl font-extrabold text-ink-soft" aria-hidden="true">{{ i + 1 }}</span>
                </span>
                <span class="font-display text-lg leading-tight font-extrabold text-ink">{{ step.label }}</span>
                <span class="text-base text-ink-soft">{{ step.text }}</span>
            </component>
        </li>
    </ol>
</template>
