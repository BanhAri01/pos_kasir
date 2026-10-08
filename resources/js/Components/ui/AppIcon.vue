<script setup>
/**
 * Ikon berdasarkan nama (dipakai untuk ikon dari database: jenis usaha & modul).
 * Hanya ikon yang didaftarkan di sini yang ikut ter-bundle, supaya aplikasi tetap ringan.
 */
import { computed } from 'vue';
import {
    Armchair, Bike, Boxes, Calculator, Calendar, ChartColumn, ChefHat, Clipboard, Clock, Coffee,
    Dumbbell, FileClock, Hammer, HandCoins, IdCard, LayoutGrid, ListChecks, ListOrdered,
    MessageCircle, Monitor, Notebook, Package, QrCode, Ruler, ScanBarcode, Scissors, Shirt,
    SlidersHorizontal, Sparkles, Store, Tags, Truck, Users, UtensilsCrossed, Wallet,
    CalendarClock, Factory, Gift, ShoppingBag, Grid3x3, PackageOpen, Printer, Repeat, Scale, TrendingDown, Warehouse,
} from 'lucide-vue-next';

const props = defineProps({
    name: { type: String, required: true },
    size: { type: [Number, String], default: 28 },
});

const icons = {
    armchair: Armchair, bike: Bike, boxes: Boxes, calculator: Calculator, calendar: Calendar,
    'bar-chart': ChartColumn, 'chef-hat': ChefHat, clipboard: Clipboard, clock: Clock, coffee: Coffee,
    dumbbell: Dumbbell, 'file-clock': FileClock, hammer: Hammer, 'hand-coins': HandCoins,
    'id-card': IdCard, 'layout-grid': LayoutGrid, 'list-checks': ListChecks,
    'list-ordered': ListOrdered, 'message-circle': MessageCircle, monitor: Monitor,
    notebook: Notebook, package: Package, 'qr-code': QrCode, ruler: Ruler,
    'scan-barcode': ScanBarcode, scissors: Scissors, shirt: Shirt, sliders: SlidersHorizontal,
    sparkles: Sparkles, store: Store, tags: Tags, truck: Truck, users: Users,
    utensils: UtensilsCrossed, wallet: Wallet,
    'calendar-clock': CalendarClock, factory: Factory, grid: Grid3x3, 'package-open': PackageOpen,
    gift: Gift, 'shopping-bag': ShoppingBag, printer: Printer, repeat: Repeat, scale: Scale, 'trending-down': TrendingDown, warehouse: Warehouse,
};

const component = computed(() => icons[props.name] ?? LayoutGrid);
</script>

<template>
    <component :is="component" :size="size" :stroke-width="2.25" aria-hidden="true" />
</template>
