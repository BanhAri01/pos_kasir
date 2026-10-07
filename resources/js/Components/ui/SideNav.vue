<script setup>
/**
 * Menu samping untuk laptop / layar lebar. Isinya sama dengan navigasi bawah +
 * menu pengaturan, supaya tidak perlu bolak-balik ke halaman "Lainnya".
 */
import { Link, router } from '@inertiajs/vue3';
import { LogOut, Repeat } from 'lucide-vue-next';
import { useAuth } from '@/composables/useAuth';
import { useNavigation } from '@/composables/useNavigation';
import AppLogo from './AppLogo.vue';
import GreekKey from './GreekKey.vue';

const { user, tenant } = useAuth();
const { main, business, settings, isActive } = useNavigation();

const itemClass = (active) => [
    'pressable flex min-h-touch items-center gap-3 rounded-2xl px-4 text-lg font-bold transition-colors',
    active ? 'bg-primary-soft text-primary-ink' : 'text-ink-soft hover:bg-ink/5 hover:text-ink',
];
</script>

<template>
    <aside class="fixed inset-y-0 left-0 z-30 hidden w-72 flex-col border-r border-line bg-surface lg:flex" aria-label="Menu utama">
        <div class="px-6 pt-6 pb-4">
            <AppLogo :size="40" />
        </div>
        <GreekKey class="mb-2 text-accent/60" :height="8" />

        <nav class="flex-1 overflow-y-auto px-3">
            <ul class="flex flex-col gap-1">
                <li v-for="item in main.filter((i) => !i.mobileOnly)" :key="item.key">
                    <component :is="item.external ? 'a' : Link" :href="item.href" :class="itemClass(isActive(item))" :aria-current="isActive(item) ? 'page' : undefined">
                        <component :is="item.icon" :size="24" aria-hidden="true" />
                        {{ item.label }}
                    </component>
                </li>
            </ul>

            <template v-if="business.length">
                <p class="mt-6 mb-2 px-4 text-base font-bold text-ink-soft">Fitur Usaha</p>
                <ul class="flex flex-col gap-1">
                    <li v-for="item in business" :key="item.key">
                        <Link :href="item.href" :class="itemClass(isActive(item))" :aria-current="isActive(item) ? 'page' : undefined">
                            <component :is="item.icon" :size="24" aria-hidden="true" />
                            {{ item.label }}
                        </Link>
                    </li>
                </ul>
            </template>

            <p class="mt-6 mb-2 px-4 text-base font-bold text-ink-soft">Pengaturan</p>
            <ul class="flex flex-col gap-1">
                <li v-for="item in settings" :key="item.key">
                    <component :is="item.external ? 'a' : Link" :href="item.href" :class="itemClass(isActive(item))" :aria-current="isActive(item) ? 'page' : undefined">
                        <component :is="item.icon" :size="24" aria-hidden="true" />
                        {{ item.label }}
                    </component>
                </li>
            </ul>
        </nav>

        <div class="border-t border-line p-4">
            <div class="mb-3 px-2">
                <p class="truncate text-lg font-extrabold text-ink">{{ user?.name }}</p>
                <p class="truncate text-base text-ink-soft">{{ user?.role_label }} · {{ tenant?.name }}</p>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <button type="button" :class="itemClass(false)" class="justify-center px-2 text-base" @click="router.post(route('switch-user'))">
                    <Repeat :size="20" aria-hidden="true" />
                    Ganti
                </button>
                <button
                    type="button"
                    :class="itemClass(false)"
                    class="justify-center px-2 text-base text-danger-ink!"
                    @click="router.post(route('logout'))"
                >
                    <LogOut :size="20" aria-hidden="true" />
                    Keluar
                </button>
            </div>
        </div>
    </aside>
</template>
