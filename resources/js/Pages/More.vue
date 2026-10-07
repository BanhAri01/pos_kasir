<script setup>
/**
 * Menu "Lainnya": semua pengaturan dikumpulkan di sini supaya layar utama tetap sederhana.
 */
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ChevronRight, Component, LogOut, MessageCircle, Repeat, Sparkles } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { useNavigation } from '@/composables/useNavigation';

defineOptions({ layout: AppLayout });

const page = usePage();
const { business, settings } = useNavigation();

const whatsappUrl = computed(
    () => `https://wa.me/${page.props.adminWhatsapp}?text=${encodeURIComponent('Halo admin Hermes POS, saya butuh bantuan.')}`,
);

const rowClass = 'card pressable flex min-h-touch items-center gap-3 px-4 text-lg font-bold';
</script>

<template>
    <Head title="Lainnya" />
    <PageHeader title="Lainnya" help="more" />

    <template v-if="business.length">
        <h2 class="mb-3 text-xl font-extrabold text-ink">Fitur usaha</h2>
        <ul class="mb-8 grid gap-3 md:grid-cols-2">
            <li v-for="item in business" :key="item.key">
                <Link :href="item.href" class="card pressable flex min-h-touch-lg items-center gap-4 p-4 hover:ring-2 hover:ring-primary/30">
                    <span class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-accent-soft text-accent-ink">
                        <component :is="item.icon" :size="28" aria-hidden="true" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-display text-lg font-extrabold text-ink">{{ item.label }}</span>
                        <span class="block text-base text-ink-soft">{{ item.description }}</span>
                    </span>
                    <ChevronRight :size="28" class="text-ink-soft" aria-hidden="true" />
                </Link>
            </li>
        </ul>
        <h2 class="mb-3 text-xl font-extrabold text-ink">Pengaturan</h2>
    </template>

    <ul class="grid gap-3 md:grid-cols-2">
        <li v-for="item in settings" :key="item.key">
            <Link :href="item.href" class="card pressable flex min-h-touch-lg items-center gap-4 p-4 hover:ring-2 hover:ring-primary/30">
                <span class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-primary-soft text-primary-ink">
                    <component :is="item.icon" :size="28" aria-hidden="true" />
                </span>
                <span class="flex-1">
                    <span class="block font-display text-lg font-extrabold text-ink">{{ item.label }}</span>
                    <span class="block text-base text-ink-soft">{{ item.description }}</span>
                </span>
                <ChevronRight :size="28" class="text-ink-soft" aria-hidden="true" />
            </Link>
        </li>
    </ul>

    <h2 class="mt-8 mb-3 text-xl font-extrabold text-ink">Bantuan & akun</h2>
    <div class="grid gap-3 md:grid-cols-2">
        <a :href="whatsappUrl" target="_blank" rel="noopener" :class="rowClass" class="text-primary-ink">
            <MessageCircle :size="26" aria-hidden="true" />
            Hubungi Admin via WhatsApp
        </a>
        <button type="button" :class="rowClass" class="text-ink" @click="router.post(route('tour.restart'))">
            <Sparkles :size="26" aria-hidden="true" />
            Ulangi Tur Pengenalan
        </button>
        <button type="button" :class="rowClass" class="text-ink" @click="router.post(route('switch-user'))">
            <Repeat :size="26" aria-hidden="true" />
            Ganti Pengguna
        </button>
        <button type="button" :class="rowClass" class="text-danger-ink" @click="router.post(route('logout'))">
            <LogOut :size="26" aria-hidden="true" />
            Keluar
        </button>
        <Link v-if="page.props.isLocal" :href="route('dev.components')" :class="rowClass" class="text-ink-soft">
            <Component :size="26" aria-hidden="true" />
            Katalog Komponen (khusus developer)
        </Link>
    </div>
</template>
