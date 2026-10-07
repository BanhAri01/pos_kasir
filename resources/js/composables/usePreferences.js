import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

/** Terapkan ukuran tampilan & tema ke <html> (dipakai CSS: data-size, data-theme). */
export function applyPreferences(prefs = {}) {
    const root = document.documentElement;
    root.dataset.size = prefs.display_size ?? 'normal';
    root.dataset.theme = prefs.theme ?? 'system';
}

/**
 * Pengaturan pribadi pengguna (ukuran, tema, suara, tur).
 *
 *   const { prefs, save } = usePreferences();
 *   save({ theme: 'dark' })   // langsung terlihat, lalu disimpan ke server
 */
export function usePreferences() {
    const page = usePage();
    const prefs = computed(() => page.props.auth?.user?.preferences ?? {});

    function save(changes, options = {}) {
        // Terapkan dulu supaya terasa instan, baru simpan ke server.
        applyPreferences({ ...prefs.value, ...changes });

        router.put(route('preferences.update'), changes, {
            preserveScroll: true,
            preserveState: true,
            only: ['auth'],
            ...options,
        });
    }

    return { prefs, save };
}
