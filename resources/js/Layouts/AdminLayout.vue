<script setup>
import { Link, router } from '@inertiajs/vue3';
import { LogOut } from 'lucide-vue-next';
import AppLogo from '@/Components/ui/AppLogo.vue';
import FlashMessages from '@/Components/ui/FlashMessages.vue';
</script>

<template>
    <div class="min-h-dvh bg-page">
        <header class="sticky top-0 z-20 border-b border-line bg-surface pt-safe">
            <div class="mx-auto flex max-w-5xl items-center gap-3 px-4 py-3 sm:px-6">
                <Link :href="route('admin.tenants.index')" class="flex items-center gap-3">
                    <AppLogo :size="36" :wordmark="false" />
                    <span class="font-display text-lg font-extrabold text-ink">Admin Hermes</span>
                </Link>
                <button type="button" class="pressable ml-auto flex min-h-touch items-center gap-2 rounded-2xl px-3 text-lg font-bold text-ink-soft" @click="router.post(route('logout'))">
                    <LogOut :size="22" aria-hidden="true" /> Keluar
                </button>
            </div>
        </header>
        <main class="mx-auto max-w-5xl px-4 pt-6 pb-12 sm:px-6">
            <FlashMessages />
            <slot />
        </main>
    </div>
</template>
