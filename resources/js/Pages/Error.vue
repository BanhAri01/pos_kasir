<script setup>
/** Halaman error dengan bahasa sehari-hari dan satu tombol jelas untuk kembali. */
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import { CircleAlert, MessageCircle } from 'lucide-vue-next';
import BigButton from '@/Components/ui/BigButton.vue';

const props = defineProps({
    status: { type: Number, required: true },
    // Dikirim langsung karena halaman 404 bisa tampil tanpa data bersama (shared props).
    adminWhatsapp: { type: String, default: '' },
});

const page = usePage();

const content = computed(
    () =>
        ({
            403: {
                title: 'Maaf, Anda tidak punya izin',
                message: 'Halaman ini hanya untuk pemilik atau manajer. Minta bantuan pemilik usaha bila perlu.',
            },
            404: {
                title: 'Halaman tidak ditemukan',
                message: 'Mungkin datanya sudah dihapus, atau alamatnya salah.',
            },
            503: {
                title: 'Sedang ada perbaikan',
                message: 'Aplikasi sedang diperbaiki sebentar. Silakan coba lagi beberapa menit lagi.',
            },
        })[props.status] ?? {
            title: 'Ada yang tidak beres',
            message: 'Maaf, terjadi masalah. Silakan coba lagi. Kalau masih terjadi, hubungi admin.',
        },
);

const home = computed(() => (page.props.auth?.user ? route('dashboard') : route('login')));
</script>

<template>
    <Head :title="content.title" />

    <main class="mx-auto flex min-h-dvh max-w-xl flex-col items-center justify-center px-4 text-center">
        <span class="flex size-20 items-center justify-center rounded-full bg-warn-soft text-warn-ink">
            <CircleAlert :size="44" aria-hidden="true" />
        </span>
        <h1 class="mt-6 text-3xl font-extrabold text-ink">{{ content.title }}</h1>
        <p class="mt-3 text-lg text-ink-soft">{{ content.message }}</p>

        <BigButton :href="home" size="large" block class="mt-8">Kembali ke Beranda</BigButton>

        <a
            :href="`https://wa.me/${adminWhatsapp}`"
            target="_blank"
            rel="noopener"
            class="mt-4 inline-flex min-h-touch items-center gap-2 rounded-2xl px-4 text-lg font-bold text-primary-ink"
        >
            <MessageCircle :size="26" aria-hidden="true" />
            Hubungi Admin via WhatsApp
        </a>
    </main>
</template>
