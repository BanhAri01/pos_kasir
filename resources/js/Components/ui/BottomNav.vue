<script setup>
/**
 * Navigasi bawah untuk HP & tablet: maksimal 5 menu, selalu ikon + tulisan.
 * Di laptop (layar lebar) diganti menu samping (SideNav).
 */
import { Link } from '@inertiajs/vue3';
import { useNavigation } from '@/composables/useNavigation';

const { main, isActive } = useNavigation();
</script>

<template>
    <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-surface pb-safe lg:hidden" aria-label="Menu utama">
        <ul class="mx-auto flex max-w-3xl">
            <li v-for="item in main.filter((i) => !i.desktopOnly)" :key="item.key" class="flex-1">
                <component
                    :is="item.external ? 'a' : Link"
                    :href="item.href"
                    class="flex min-h-touch-lg flex-col items-center justify-center gap-1 pt-2 pb-1 text-base font-bold transition-colors"
                    :class="isActive(item, true) ? 'text-primary-ink' : 'text-ink-soft'"
                    :aria-current="isActive(item, true) ? 'page' : undefined"
                >
                    <span class="rounded-full px-5 py-1 transition-colors" :class="isActive(item, true) ? 'bg-primary-soft' : ''">
                        <component :is="item.icon" :size="26" :stroke-width="2.25" aria-hidden="true" />
                    </span>
                    {{ item.label }}
                </component>
            </li>
        </ul>
    </nav>
</template>
