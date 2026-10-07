<script setup>
/**
 * Tampilan & Suara: pengaturan pribadi tiap pengguna (berlaku di HP mana pun ia masuk).
 * Perubahan langsung terlihat dan tersimpan otomatis, tanpa tombol Simpan.
 */
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { CircleCheck, Moon, Smartphone, Sparkles, Sun, Volume2 } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import MoneyDisplay from '@/Components/ui/MoneyDisplay.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import ToggleSwitch from '@/Components/ui/ToggleSwitch.vue';
import { playTing } from '@/composables/useFeedback';
import { usePreferences } from '@/composables/usePreferences';

defineOptions({ layout: AppLayout });

const { prefs, save } = usePreferences();

const sizes = [
    { value: 'normal', label: 'Normal' },
    { value: 'besar', label: 'Besar' },
    { value: 'sangat-besar', label: 'Sangat Besar' },
];

const themes = [
    { value: 'system', label: 'Ikuti HP', icon: Smartphone },
    { value: 'light', label: 'Terang', icon: Sun },
    { value: 'dark', label: 'Gelap', icon: Moon },
];

const displaySize = computed({
    get: () => prefs.value.display_size,
    set: (value) => update({ display_size: value }),
});
const theme = computed({
    get: () => prefs.value.theme,
    set: (value) => update({ theme: value }),
});
const simpleMode = computed({
    get: () => prefs.value.simple_mode,
    set: (value) => update({ simple_mode: value }),
});
const sound = computed({
    get: () => prefs.value.sound,
    set: (value) => {
        update({ sound: value });
        if (value) playTing();
    },
});

// Tanda "Tersimpan" kecil, pengganti pesan besar yang muncul setiap ketukan.
const saved = ref(false);
let timer = null;

function update(changes) {
    save(changes, {
        onSuccess: () => {
            saved.value = true;
            clearTimeout(timer);
            timer = setTimeout(() => (saved.value = false), 2500);
        },
    });
}

</script>

<template>
    <Head title="Tampilan & Suara" />

    <div class="mx-auto max-w-2xl">
        <PageHeader title="Tampilan & Suara" subtitle="Atur supaya nyaman dilihat dan dipakai." :back-href="route('more')" help="display" />

        <p v-if="saved" role="status" class="mb-4 flex animate-pop-in items-center gap-2 text-lg font-bold text-primary-ink">
            <CircleCheck :size="24" aria-hidden="true" />
            Tersimpan
        </p>

        <section class="card p-5 sm:p-6">
            <h2 class="text-xl font-extrabold text-ink">Ukuran tulisan</h2>
            <p class="mt-1 mb-4 text-lg text-ink-soft">Pilih yang paling nyaman dibaca.</p>
            <SegmentedControl v-model="displaySize" label="Ukuran tulisan" :options="sizes" />

            <!-- Pratinjau langsung -->
            <div class="mt-5 rounded-2xl border-2 border-dashed border-line p-4">
                <p class="text-base font-bold text-ink-soft">Contoh tampilan:</p>
                <p class="mt-1 text-lg text-ink">Kopi Susu Gula Aren × 2</p>
                <MoneyDisplay :amount="36000" size="xl" label="Total belanja" class="mt-2" />
                <BigButton block class="mt-3" tabindex="-1">Bayar</BigButton>
            </div>
        </section>

        <section class="card mt-4 p-5 sm:p-6">
            <h2 class="text-xl font-extrabold text-ink">Tema warna</h2>
            <p class="mt-1 mb-4 text-lg text-ink-soft">Mode gelap nyaman di malam hari dan menghemat baterai.</p>
            <SegmentedControl v-model="theme" label="Tema warna" :options="themes" />
        </section>

        <section class="card mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 p-5 sm:p-6">
            <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-primary-soft text-primary-ink">
                <Sparkles :size="26" aria-hidden="true" />
            </span>
            <div class="min-w-48 flex-1">
                <h2 class="text-xl font-extrabold text-ink">Mode Sederhana</h2>
                <p class="text-lg text-ink-soft">Menyembunyikan tombol yang jarang dipakai di kasir (diskon, ubah harga). Matikan kalau butuh fitur lengkap.</p>
            </div>
            <ToggleSwitch v-model="simpleMode" label="Mode Sederhana" class="ml-auto" />
        </section>

        <section class="card mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 p-5 sm:p-6">
            <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-primary-soft text-primary-ink">
                <Volume2 :size="26" aria-hidden="true" />
            </span>
            <div class="min-w-48 flex-1">
                <h2 class="text-xl font-extrabold text-ink">Suara "ting"</h2>
                <p class="text-lg text-ink-soft">Berbunyi saat berhasil menyimpan atau transaksi berhasil.</p>
            </div>
            <ToggleSwitch v-model="sound" label='Suara "ting"' class="ml-auto" />
        </section>
    </div>
</template>
