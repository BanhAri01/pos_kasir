import { usePage } from '@inertiajs/vue3';

/**
 * Umpan balik saat aksi berhasil / gagal: suara "ting" dan getar ringan.
 *
 * - Suara dibuat dengan Web Audio (tanpa file mp3), jadi ringan dan jalan offline.
 * - Getar memakai navigator.vibrate. iPhone (Safari) tidak mendukung getar dari web,
 *   jadi di iPhone hanya suara yang bunyi.
 * - Bisa dimatikan di Lainnya > Tampilan & Suara.
 */

let audioContext = null;

function context() {
    const Ctx = window.AudioContext || window.webkitAudioContext;
    if (!Ctx) return null;
    audioContext ??= new Ctx();
    return audioContext;
}

/**
 * iPhone hanya mengizinkan suara setelah layar disentuh. Panggil sekali saat aplikasi
 * dimulai: audio "dibuka" pada sentuhan pertama.
 */
export function unlockAudioOnFirstTouch() {
    const unlock = () => {
        const ctx = context();
        if (ctx?.state === 'suspended') ctx.resume();
        window.removeEventListener('pointerdown', unlock);
        window.removeEventListener('keydown', unlock);
    };
    window.addEventListener('pointerdown', unlock, { once: true });
    window.addEventListener('keydown', unlock, { once: true });
}

function tone(ctx, frequency, start, duration, volume) {
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.type = 'sine';
    osc.frequency.value = frequency;
    gain.gain.setValueAtTime(0, ctx.currentTime + start);
    gain.gain.linearRampToValueAtTime(volume, ctx.currentTime + start + 0.01);
    gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + start + duration);
    osc.connect(gain).connect(ctx.destination);
    osc.start(ctx.currentTime + start);
    osc.stop(ctx.currentTime + start + duration + 0.05);
}

export function playTing() {
    const ctx = context();
    if (!ctx || ctx.state !== 'running') return;
    tone(ctx, 1318.5, 0, 0.35, 0.18); // E6
    tone(ctx, 1760, 0.09, 0.5, 0.14); // A6
}

export function playError() {
    const ctx = context();
    if (!ctx || ctx.state !== 'running') return;
    tone(ctx, 330, 0, 0.18, 0.15);
    tone(ctx, 262, 0.16, 0.28, 0.15);
}

export function vibrate(pattern) {
    navigator.vibrate?.(pattern);
}

export function useFeedback() {
    const page = usePage();
    const soundOn = () => page.props.auth?.user?.preferences?.sound ?? true;

    return {
        success() {
            if (soundOn()) playTing();
            vibrate(40);
        },
        error() {
            if (soundOn()) playError();
            vibrate([60, 60, 60]);
        },
    };
}
