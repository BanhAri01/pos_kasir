import { onBeforeUnmount, onMounted, ref } from 'vue';

/** Status internet (true = online). Ikut berubah saat internet mati / menyala lagi. */
export function useOnline() {
    const online = ref(typeof navigator === 'undefined' ? true : navigator.onLine);
    const update = () => (online.value = navigator.onLine);

    onMounted(() => {
        window.addEventListener('online', update);
        window.addEventListener('offline', update);
    });
    onBeforeUnmount(() => {
        window.removeEventListener('online', update);
        window.removeEventListener('offline', update);
    });

    return online;
}
