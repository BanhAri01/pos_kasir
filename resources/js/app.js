import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { ZiggyVue } from 'ziggy-js';
import { applyPreferences } from '@/composables/usePreferences';
import { unlockAudioOnFirstTouch } from '@/composables/useFeedback';
import { registerServiceWorker } from '@/lib/registerServiceWorker';

createInertiaApp({
    title: (title) => (title ? `${title} - Hermes POS` : 'Hermes POS'),
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue');
        return pages[`./Pages/${name}.vue`]();
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#f59e0b',
        delay: 150,
    },
});

// Ukuran tampilan & tema mengikuti pengguna yang sedang masuk (berubah saat ganti pengguna).
router.on('navigate', (event) => applyPreferences(event.detail.page.props.auth?.user?.preferences));

unlockAudioOnFirstTouch();
registerServiceWorker();
