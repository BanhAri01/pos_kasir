/** Pesan singkat di layar kasir (berhasil / gagal), dengan suara & getar. */
import { reactive } from 'vue';
import { playError, playTing, vibrate } from '@/composables/useFeedback';
import { store } from '../store';

export const toast = reactive({ message: null, kind: 'success', id: 0 });

let timer = null;

export function showToast(message, kind = 'success') {
    toast.message = message;
    toast.kind = kind;
    toast.id++;

    const soundOn = store.boot?.user.preferences?.sound ?? true;
    if (kind === 'success') {
        if (soundOn) playTing();
        vibrate(40);
    } else if (kind === 'error') {
        if (soundOn) playError();
        vibrate([60, 60, 60]);
    }

    clearTimeout(timer);
    timer = setTimeout(() => (toast.message = null), kind === 'error' ? 7000 : 3500);
}
