<script setup>
/**
 * Kerangka halaman setelah masuk.
 *
 * - HP & tablet: bar atas (logo + nama usaha) dan navigasi bawah.
 * - Laptop (>= 1024px): menu samping di kiri, tanpa navigasi bawah.
 * Status internet (OfflineBanner) menempel di bawah bar atas, selalu terlihat.
 */
import { useAuth } from '@/composables/useAuth';
import AppLogo from '@/Components/ui/AppLogo.vue';
import BottomNav from '@/Components/ui/BottomNav.vue';
import FlashMessages from '@/Components/ui/FlashMessages.vue';
import OfflineBanner from '@/Components/ui/OfflineBanner.vue';
import OutletSwitcher from '@/Components/ui/OutletSwitcher.vue';
import SideNav from '@/Components/ui/SideNav.vue';

const { user, tenant } = useAuth();
</script>

<template>
    <div class="min-h-dvh lg:pl-72">
        <SideNav />

        <div class="sticky top-0 z-20">
            <!-- Bar atas (HP & tablet) -->
            <header class="border-b border-line bg-surface pt-safe lg:hidden">
                <div class="mx-auto flex max-w-3xl items-center gap-3 px-4 py-3">
                    <AppLogo :size="38" :wordmark="false" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-display text-lg leading-tight font-extrabold text-ink">{{ tenant?.name }}</p>
                        <p class="truncate text-base text-ink-soft">{{ user?.name }} · {{ user?.role_label }}</p>
                    </div>
                    <span
                        v-if="tenant?.trial_days_left > 0"
                        class="hidden shrink-0 rounded-full bg-accent-soft px-3 py-1 text-base font-bold text-accent-ink sm:inline"
                    >
                        Coba gratis {{ tenant.trial_days_left }} hari
                    </span>
                </div>
                <div v-if="$page.props.outlet?.list?.length > 1" class="mx-auto max-w-3xl px-4 pb-3">
                    <OutletSwitcher />
                </div>
            </header>

            <!-- Bar atas (laptop) -->
            <header class="hidden border-b border-line bg-surface lg:block">
                <div class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-8 py-4">
                    <div class="flex min-w-0 items-center gap-4">
                        <p class="font-display text-xl font-extrabold text-ink">{{ tenant?.name }}</p>
                        <OutletSwitcher />
                    </div>
                    <span v-if="tenant?.trial_days_left > 0" class="rounded-full bg-accent-soft px-4 py-1 text-base font-bold text-accent-ink">
                        Masa coba gratis: {{ tenant.trial_days_left }} hari lagi
                    </span>
                </div>
            </header>

            <OfflineBanner />
        </div>

        <main class="mx-auto max-w-3xl px-4 pt-6 pb-nav sm:px-6 lg:max-w-5xl lg:px-8 lg:pt-8 lg:pb-12">
            <slot />
        </main>

        <BottomNav />
        <FlashMessages />
    </div>
</template>
