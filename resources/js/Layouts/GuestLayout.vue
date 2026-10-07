<script setup>
/**
 * Kerangka halaman sebelum masuk (daftar, masuk, masuk dengan PIN).
 *
 * - HP: logo di atas, form di bawahnya.
 * - Laptop/tablet lebar: dua sisi. Kiri panel merek Hermes, kanan form.
 */
import { usePage } from '@inertiajs/vue3';
import { MessageCircle, ReceiptText, WifiOff, Zap } from 'lucide-vue-next';
import AppLogo from '@/Components/ui/AppLogo.vue';
import CaduceusMark from '@/Components/ui/CaduceusMark.vue';
import GreekKey from '@/Components/ui/GreekKey.vue';
import FlashMessages from '@/Components/ui/FlashMessages.vue';

const page = usePage();

const highlights = [
    { icon: Zap, text: 'Jualan cepat, tampilan besar dan mudah dibaca' },
    { icon: MessageCircle, text: 'Struk & pengingat utang terkirim lewat WhatsApp' },
    { icon: WifiOff, text: 'Tetap bisa jualan walau internet mati' },
    { icon: ReceiptText, text: 'Laporan untung harian yang gampang dipahami' },
];
</script>

<template>
    <div class="min-h-dvh lg:grid lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]">
        <!-- Panel merek (laptop) -->
        <aside class="relative hidden overflow-hidden bg-brand p-12 text-white lg:flex lg:flex-col lg:justify-between">
            <!-- Hiasan: caduceus besar samar + pinggiran meander Yunani -->
            <CaduceusMark class="absolute -right-32 -bottom-20 size-136 text-accent opacity-[0.06]" />
            <GreekKey class="absolute inset-x-0 top-0 text-accent/70" :height="14" />
            <GreekKey class="absolute inset-x-0 bottom-0 text-accent/70" :height="14" />

            <AppLogo :size="52" variant="inverse" class="relative mt-4" />

            <div class="relative">
                <h2 class="font-greek text-5xl leading-tight font-bold tracking-wide">
                    Kasir cepat,<br />
                    <span class="text-accent">kabar sampai.</span>
                </h2>
                <p class="mt-4 max-w-md text-xl text-white/85">
                    Terinspirasi Hermes, dewa pengantar pesan dari Yunani kuno. Kami bantu jualan Anda lebih cepat,
                    dan setiap kabar sampai ke pelanggan.
                </p>
                <ul class="mt-10 flex flex-col gap-4">
                    <li v-for="item in highlights" :key="item.text" class="flex items-center gap-4 text-lg">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-white/10">
                            <component :is="item.icon" :size="24" />
                        </span>
                        {{ item.text }}
                    </li>
                </ul>
            </div>

            <p class="relative mb-4 text-base text-white/70">Dibuat untuk UMKM Indonesia.</p>
        </aside>

        <!-- Form -->
        <div class="flex min-h-dvh flex-col pt-safe">
            <header class="lg:hidden">
                <div class="flex items-center justify-between px-5 py-5">
                    <AppLogo :size="40" />
                    <span class="text-base font-bold text-ink-soft">Kasir cepat, kabar sampai.</span>
                </div>
                <GreekKey class="text-accent/60" :height="10" />
            </header>

            <main class="mx-auto w-full max-w-xl flex-1 px-5 pt-2 pb-6 lg:flex lg:flex-col lg:justify-center lg:py-12">
                <slot />
            </main>

            <footer class="px-4 pb-[calc(1.5rem+env(safe-area-inset-bottom))] text-center">
                <a
                    :href="`https://wa.me/${page.props.adminWhatsapp}?text=${encodeURIComponent('Halo admin Hermes POS, saya butuh bantuan.')}`"
                    target="_blank"
                    rel="noopener"
                    class="pressable inline-flex min-h-touch items-center gap-2 rounded-2xl px-4 text-lg font-bold text-primary-ink hover:bg-primary-soft"
                >
                    <MessageCircle :size="26" aria-hidden="true" />
                    Butuh bantuan? Hubungi Admin via WhatsApp
                </a>
            </footer>
        </div>

        <FlashMessages />
    </div>
</template>
