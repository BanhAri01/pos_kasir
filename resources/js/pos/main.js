import { createApp } from 'vue';
import { ZiggyVue } from 'ziggy-js';
import { unlockAudioOnFirstTouch } from '@/composables/useFeedback';
import { registerServiceWorker } from '@/lib/registerServiceWorker';
import PosApp from './PosApp.vue';
import { startSyncLoop } from './lib/sync';

createApp(PosApp).use(ZiggyVue).mount('#pos');

unlockAudioOnFirstTouch();
registerServiceWorker();
startSyncLoop();
