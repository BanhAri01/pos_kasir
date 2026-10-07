<script setup>
/**
 * Layar kasir.
 *  - HP          : daftar barang memenuhi layar, tombol BAYAR menempel di bawah,
 *                  keranjang dibuka dari bawah.
 *  - Tablet/PC   : dua kolom: barang di kiri, keranjang di kanan.
 */
import { computed, onMounted, ref } from 'vue';
import { ArrowLeft, Banknote, ChevronUp, History, Lock, Menu, Printer, Repeat, RefreshCw, Store as StoreIcon } from 'lucide-vue-next';
import AppLogo from '@/Components/ui/AppLogo.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import BottomSheet from '@/Components/ui/BottomSheet.vue';
import OfflineBanner from '@/Components/ui/OfflineBanner.vue';
import { formatRupiah } from '@/composables/useRupiah';
import CartPanel from './components/CartPanel.vue';
import CashSheet from './components/CashSheet.vue';
import Catalog from './components/Catalog.vue';
import CloseShiftSheet from './components/CloseShiftSheet.vue';
import CustomerSheet from './components/CustomerSheet.vue';
import DiscountSheet from './components/DiscountSheet.vue';
import HistorySheet from './components/HistorySheet.vue';
import ItemSheet from './components/ItemSheet.vue';
import VariantSheet from './components/VariantSheet.vue';
import OpenShift from './components/OpenShift.vue';
import PaymentSheet from './components/PaymentSheet.vue';
import PosToast from './components/PosToast.vue';
import PrinterSheet from './components/PrinterSheet.vue';
import SuccessScreen from './components/SuccessScreen.vue';
import SyncStatus from './components/SyncStatus.vue';
import { addToCart, cartCount, hasModule, loadBootstrap, store, totals } from './store';
import KasbonPaySheet from './components/KasbonPaySheet.vue';
import TablesSheet from './components/TablesSheet.vue';
import { api } from './lib/api';
import { syncState } from './lib/sync';

const sheets = ref({ variant: false, tables: false, kasbon: false, cart: false, pay: false, item: false, customer: false, discount: false, history: false, cash: false, close: false, printer: false, menu: false });
const editing = ref({ line: null, product: null });
const variantModel = ref(null);
const kasbonCustomer = ref(null);

function openKasbon(customer) {
    kasbonCustomer.value = customer;
    sheets.value.kasbon = true;
}
const paidSale = ref(null);

const isWide = ref(false);
const media = typeof window !== 'undefined' ? window.matchMedia('(min-width: 900px)') : null;

onMounted(() => {
    loadBootstrap();
    if (media) {
        isWide.value = media.matches;
        media.addEventListener('change', (e) => (isWide.value = e.matches));
    }
});

const ready = computed(() => !store.loading && store.boot && !store.error);

function onAdd(product) {
    // Model baju: pilih ukuran & warna dulu.
    if (product.has_variants) {
        variantModel.value = product;
        sheets.value.variant = true;
        return;
    }
    // Barang yang perlu diisi dulu dibuka di panel: harga bebas / per kg / desimal,
    // punya satuan lain, punya pilihan ukuran/tambahan, atau layanan yang perlu dipilih pengerjanya.
    const needsStaff = (hasModule('staff_commission') || store.boot.tenant.pos_layout === 'service') && product.type === 'service' && store.boot.staff.length > 0;
    if (product.pricing_mode !== 'fixed' || product.unit_allows_decimal || product.units?.length || product.modifier_groups?.length || needsStaff) {
        editing.value = { line: null, product };
        sheets.value.item = true;
        return;
    }
    addToCart(product);
}

function onEdit(line) {
    editing.value = { line, product: null };
    sheets.value.item = true;
}

function openPay() {
    sheets.value.cart = false;
    sheets.value.pay = true;
}

function onPaid(sale) {
    paidSale.value = sale;
}

function openSheet(name) {
    sheets.value.menu = false;
    sheets.value[name] = true;
}

async function switchUser() {
    try {
        await api.post(route('switch-user'));
    } finally {
        window.location.href = route('pin.create');
    }
}

const menuItems = [
    { key: 'history', label: 'Transaksi Hari Ini', icon: History },
    { key: 'cash', label: 'Uang Masuk / Keluar', icon: Banknote },
    { key: 'printer', label: 'Printer Struk', icon: Printer },
    { key: 'close', label: 'Tutup Kasir', icon: Lock },
];
</script>

<template>
    <div class="flex h-dvh flex-col bg-page">
        <!-- Bar atas -->
        <header class="shrink-0 border-b border-line bg-surface pt-safe">
            <div class="flex items-center gap-2 px-3 py-2 sm:px-4">
                <a
                    :href="route('dashboard')"
                    class="pressable flex min-h-12 items-center gap-1 rounded-xl px-2 text-base font-bold text-ink-soft hover:bg-ink/5"
                >
                    <ArrowLeft :size="24" aria-hidden="true" />
                    <span class="hidden sm:inline">Beranda</span>
                </a>
                <AppLogo :size="34" :wordmark="false" class="hidden sm:inline-flex" />
                <div v-if="store.boot" class="min-w-0 flex-1">
                    <p class="truncate font-display text-lg leading-tight font-extrabold text-ink">Kasir · {{ store.boot.outlet.name }}</p>
                    <p class="truncate text-base text-ink-soft">{{ store.boot.user.name }}</p>
                </div>
                <div v-else class="flex-1" />
                <SyncStatus v-if="ready" />
                <button
                    v-if="ready && store.boot.shift"
                    type="button"
                    class="pressable flex min-h-12 items-center gap-2 rounded-xl bg-surface-2 px-3 text-base font-bold text-ink"
                    @click="sheets.menu = true"
                >
                    <Menu :size="24" aria-hidden="true" /> Menu
                </button>
            </div>
            <OfflineBanner message="Sedang offline - transaksi tetap tersimpan aman." />
            <p v-if="store.fromCache && syncState.online" class="bg-info-soft px-4 py-2 text-base font-bold text-info-ink">
                Memakai data yang tersimpan di HP. Data terbaru dimuat saat tersambung.
            </p>
        </header>

        <!-- Memuat / gagal -->
        <div v-if="store.loading" class="flex flex-1 items-center justify-center text-xl text-ink-soft">Menyiapkan kasir...</div>
        <div v-else-if="store.error" class="mx-auto flex max-w-md flex-1 flex-col items-center justify-center gap-4 px-6 text-center">
            <p class="text-xl font-bold text-ink">{{ store.error }}</p>
            <BigButton @click="loadBootstrap"><RefreshCw :size="22" aria-hidden="true" /> Coba Lagi</BigButton>
        </div>

        <!-- Belum buka kasir -->
        <main v-else-if="!store.boot.shift" class="flex-1 overflow-y-auto">
            <OpenShift />
        </main>

        <!-- Jualan -->
        <main v-else class="flex min-h-0 flex-1">
            <section class="min-h-0 flex-1 overflow-y-auto p-3 sm:p-4" :class="isWide ? '' : 'pb-32'">
                <Catalog @add="onAdd" />
            </section>

            <aside v-if="isWide" class="flex w-[400px] shrink-0 flex-col border-l border-line bg-surface p-4 xl:w-[440px]">
                <CartPanel @edit="onEdit" @pay="openPay" @customer="sheets.customer = true" @discount="sheets.discount = true" @tables="sheets.tables = true" />
            </aside>
        </main>

        <!-- HP: ringkasan keranjang + BAYAR menempel di bawah -->
        <div v-if="ready && store.boot.shift && !isWide" class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-surface p-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))]">
            <div class="flex gap-2">
                <button
                    type="button"
                    class="pressable flex min-h-touch-lg flex-1 items-center gap-2 rounded-2xl border-2 border-line px-3 text-left"
                    @click="sheets.cart = true"
                >
                    <ChevronUp :size="24" class="shrink-0 text-ink-soft" aria-hidden="true" />
                    <span class="min-w-0">
                        <span class="block text-base text-ink-soft">{{ cartCount ? `${String(cartCount).replace('.', ',')} barang` : 'Keranjang kosong' }}</span>
                        <span class="block font-display text-xl font-extrabold text-ink tabular-nums">{{ formatRupiah(totals.total) }}</span>
                    </span>
                </button>
                <BigButton size="large" :disabled="!store.cart.length" class="px-8" @click="openPay">BAYAR</BigButton>
            </div>
        </div>

        <!-- Panel-panel -->
        <BottomSheet v-if="ready && !isWide" v-model:open="sheets.cart" title="">
            <div class="h-[70dvh]">
                <CartPanel @edit="onEdit" @pay="openPay" @customer="sheets.customer = true" @discount="sheets.discount = true" @tables="sheets.tables = true" />
            </div>
        </BottomSheet>

        <BottomSheet v-if="ready" v-model:open="sheets.menu" title="Menu Kasir">
            <div class="grid gap-2">
                <button
                    v-for="item in menuItems"
                    :key="item.key"
                    type="button"
                    class="pressable flex min-h-touch items-center gap-3 rounded-2xl border-2 border-line px-4 text-left text-lg font-bold text-ink hover:border-primary"
                    @click="openSheet(item.key)"
                >
                    <component :is="item.icon" :size="24" aria-hidden="true" /> {{ item.label }}
                </button>
                <button type="button" class="pressable flex min-h-touch items-center gap-3 rounded-2xl border-2 border-line px-4 text-left text-lg font-bold text-ink" @click="switchUser">
                    <Repeat :size="24" aria-hidden="true" /> Ganti Pengguna
                </button>
                <a :href="route('dashboard')" class="pressable flex min-h-touch items-center gap-3 rounded-2xl border-2 border-line px-4 text-lg font-bold text-ink">
                    <StoreIcon :size="24" aria-hidden="true" /> Ke Halaman Beranda
                </a>
            </div>
        </BottomSheet>

        <template v-if="ready">
            <ItemSheet v-model:open="sheets.item" :line="editing.line" :product="editing.product" />
            <VariantSheet v-model:open="sheets.variant" :model="variantModel" />
            <PaymentSheet v-model:open="sheets.pay" @paid="onPaid" @customer="sheets.customer = true" />
            <CustomerSheet v-model:open="sheets.customer" @kasbon="openKasbon" />
            <TablesSheet v-if="hasModule('tables')" v-model:open="sheets.tables" />
            <KasbonPaySheet v-model:open="sheets.kasbon" :customer="kasbonCustomer" />
            <DiscountSheet v-model:open="sheets.discount" />
            <HistorySheet v-model:open="sheets.history" />
            <CashSheet v-model:open="sheets.cash" />
            <CloseShiftSheet v-model:open="sheets.close" />
            <PrinterSheet v-model:open="sheets.printer" />
        </template>

        <SuccessScreen v-if="paidSale" :sale="paidSale" @done="paidSale = null" @printer="sheets.printer = true" />
        <PosToast />
    </div>
</template>
