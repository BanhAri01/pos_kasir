<script setup>
/**
 * Buka kasir: hitung uang yang ada di laci sebelum mulai jualan.
 */
import { ref } from 'vue';
import { LockOpen } from 'lucide-vue-next';
import BigButton from '@/Components/ui/BigButton.vue';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import { formatRupiah } from '@/composables/useRupiah';
import { openShift, store } from '../store';
import { showToast } from '../lib/toast';

const amount = ref(null);
const saving = ref(false);
const quick = [0, 50000, 100000, 200000];

async function submit() {
    saving.value = true;
    try {
        await openShift(amount.value ?? 0);
        showToast('Kasir sudah dibuka. Selamat berjualan!');
    } catch (e) {
        showToast(e.message, 'error');
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div class="mx-auto flex max-w-lg flex-col items-center px-4 py-10 text-center">
        <span class="flex size-20 items-center justify-center rounded-full bg-primary-soft text-primary-ink">
            <LockOpen :size="40" aria-hidden="true" />
        </span>
        <h1 class="mt-5 text-3xl font-extrabold text-ink">Buka Kasir</h1>
        <p class="mt-2 text-lg text-ink-soft">
            Halo {{ store.boot.user.name }}! Hitung dulu uang yang ada di laci sekarang, untuk dicocokkan saat tutup kasir nanti.
        </p>

        <div class="card mt-8 w-full p-5 text-left">
            <MoneyInput v-model="amount" label="Uang awal di laci" hint="Isi 0 kalau laci kosong." />
            <div class="mt-3 grid grid-cols-2 gap-2">
                <button
                    v-for="q in quick"
                    :key="q"
                    type="button"
                    class="pressable min-h-12 rounded-xl border-2 text-lg font-bold"
                    :class="amount === q ? 'border-primary bg-primary-soft text-primary-ink' : 'border-line text-ink'"
                    @click="amount = q"
                >
                    {{ q === 0 ? 'Kosong' : formatRupiah(q) }}
                </button>
            </div>
            <BigButton size="large" block class="mt-5" :loading="saving" @click="submit">Buka Kasir</BigButton>
        </div>
    </div>
</template>
