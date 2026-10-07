<script setup>
/**
 * Katalog komponen (khusus development): semua komponen UI di satu halaman,
 * untuk mengecek tampilan di HP, tablet, laptop, mode terang & gelap.
 */
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Moon, Package, Plus, Smartphone, Sun } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppLogo from '@/Components/ui/AppLogo.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import BigInput from '@/Components/ui/BigInput.vue';
import BottomSheet from '@/Components/ui/BottomSheet.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import MoneyDisplay from '@/Components/ui/MoneyDisplay.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import PinPad from '@/Components/ui/PinPad.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import ToggleSwitch from '@/Components/ui/ToggleSwitch.vue';
import { useFeedback } from '@/composables/useFeedback';
import { applyPreferences, usePreferences } from '@/composables/usePreferences';

defineOptions({ layout: AppLayout });

const { prefs } = usePreferences();
const feedback = useFeedback();

const text = ref('');
const price = ref('');
const pin = ref('');
const toggle = ref(true);
const choice = ref('system');
const sheetOpen = ref(false);
const confirmOpen = ref(false);

// Pratinjau tema di halaman ini saja (tidak disimpan).
function previewTheme(value) {
    choice.value = value;
    applyPreferences({ ...prefs.value, theme: value });
}

const swatches = [
    ['page', 'bg-page'], ['surface', 'bg-surface'], ['surface-2', 'bg-surface-2'], ['line', 'bg-line'],
    ['ink', 'bg-ink'], ['ink-soft', 'bg-ink-soft'], ['primary', 'bg-primary'], ['primary-soft', 'bg-primary-soft'],
    ['primary-ink', 'bg-primary-ink'], ['accent', 'bg-accent'], ['danger', 'bg-danger'], ['warn-soft', 'bg-warn-soft'],
];
</script>

<template>
    <Head title="Katalog Komponen" />
    <PageHeader title="Katalog Komponen" subtitle="Khusus developer: cek tampilan semua komponen." :back-href="route('more')" />

    <div class="flex flex-col gap-6">
        <section class="card p-5">
            <h2 class="mb-4 text-xl font-extrabold">Tema (pratinjau)</h2>
            <SegmentedControl
                :model-value="choice"
                label="Tema"
                :options="[
                    { value: 'system', label: 'Ikuti HP', icon: Smartphone },
                    { value: 'light', label: 'Terang', icon: Sun },
                    { value: 'dark', label: 'Gelap', icon: Moon },
                ]"
                @update:model-value="previewTheme"
            />
            <div class="mt-4 grid grid-cols-3 gap-2 sm:grid-cols-6">
                <div v-for="[name, cls] in swatches" :key="name" class="text-center">
                    <div class="h-12 rounded-xl border border-line" :class="cls" />
                    <p class="mt-1 text-base text-ink-soft">{{ name }}</p>
                </div>
            </div>
        </section>

        <section class="card p-5">
            <h2 class="mb-4 text-xl font-extrabold">Logo</h2>
            <div class="flex flex-wrap items-center gap-6">
                <AppLogo :size="48" />
                <AppLogo :size="40" :wordmark="false" />
                <span class="rounded-2xl bg-primary p-3"><AppLogo :size="40" variant="inverse" /></span>
            </div>
        </section>

        <section class="card p-5">
            <h2 class="mb-4 text-xl font-extrabold">BigButton</h2>
            <div class="grid gap-3 sm:grid-cols-2">
                <BigButton><Plus :size="24" /> Utama (primary)</BigButton>
                <BigButton variant="secondary">Kedua (secondary)</BigButton>
                <BigButton variant="soft">Lembut (soft)</BigButton>
                <BigButton variant="danger">Bahaya (danger)</BigButton>
                <BigButton variant="ghost">Tipis (ghost)</BigButton>
                <BigButton loading>Sedang memproses</BigButton>
                <BigButton size="large" block class="sm:col-span-2">BAYAR (large)</BigButton>
            </div>
        </section>

        <section class="card p-5">
            <h2 class="mb-4 text-xl font-extrabold">MoneyDisplay</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <MoneyDisplay :amount="12500" size="sm" label="Kecil" />
                <MoneyDisplay :amount="125000" size="md" label="Sedang" />
                <MoneyDisplay :amount="1250000" size="lg" tone="positive" label="Uang masuk" />
                <MoneyDisplay :amount="-75000" size="lg" tone="negative" label="Uang keluar" />
                <MoneyDisplay :amount="2375000" size="xl" label="Total belanja" class="sm:col-span-2" />
            </div>
        </section>

        <section class="card flex flex-col gap-5 p-5">
            <h2 class="text-xl font-extrabold">BigInput</h2>
            <BigInput v-model="text" label="Nama barang" placeholder="Contoh: Kopi Susu" hint="Contoh petunjuk di bawah kolom." />
            <BigInput v-model="price" label="Harga jual" prefix="Rp" inputmode="numeric" placeholder="15.000" />
            <BigInput v-model="text" label="Dengan kesalahan" error="Nama barang belum diisi." />
            <BigInput v-model="text" label="Kata sandi" type="password" optional />
        </section>

        <section class="card flex flex-col gap-4 p-5">
            <h2 class="text-xl font-extrabold">ToggleSwitch & SegmentedControl</h2>
            <div class="flex items-center justify-between">
                <span class="text-lg">Fitur contoh</span>
                <ToggleSwitch v-model="toggle" label="Fitur contoh" />
            </div>
            <SegmentedControl
                v-model="choice"
                label="Contoh"
                :options="[{ value: 'system', label: 'Satu' }, { value: 'light', label: 'Dua' }, { value: 'dark', label: 'Tiga' }]"
            />
        </section>

        <section class="card p-5">
            <h2 class="mb-4 text-xl font-extrabold">Panel & umpan balik</h2>
            <div class="grid gap-3 sm:grid-cols-2">
                <BigButton variant="secondary" @click="sheetOpen = true">Buka BottomSheet</BigButton>
                <BigButton variant="secondary" @click="confirmOpen = true">Buka ConfirmDialog</BigButton>
                <BigButton variant="soft" @click="feedback.success()">Suara berhasil</BigButton>
                <BigButton variant="soft" @click="feedback.error()">Suara gagal</BigButton>
            </div>
        </section>

        <section class="card p-5">
            <h2 class="mb-4 text-xl font-extrabold">PinPad</h2>
            <PinPad v-model="pin" @submit="pin = ''" />
        </section>

        <EmptyState title="Belum ada barang" message="Tambahkan barang pertama Anda supaya bisa mulai jualan.">
            <template #icon><Package :size="48" /></template>
            <BigButton><Plus :size="24" /> Tambah Barang</BigButton>
        </EmptyState>
    </div>

    <BottomSheet v-model:open="sheetOpen" title="Contoh panel">
        <p class="text-lg text-ink-soft">Di HP panel muncul dari bawah. Di laptop muncul di tengah layar.</p>
        <BigButton block class="mt-6" @click="sheetOpen = false">Mengerti</BigButton>
    </BottomSheet>

    <ConfirmDialog
        v-model:open="confirmOpen"
        title="Hapus barang ini?"
        message="Barang tidak akan tampil lagi di kasir."
        confirm-text="Ya, Hapus"
        danger
        @confirm="confirmOpen = false"
    />
</template>
