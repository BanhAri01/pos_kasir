<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Gift, Search } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import BigInput from '@/Components/ui/BigInput.vue';
import BottomSheet from '@/Components/ui/BottomSheet.vue';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import ToggleSwitch from '@/Components/ui/ToggleSwitch.vue';
import { formatRupiah } from '@/composables/useRupiah';

defineOptions({ layout: AppLayout });

const props = defineProps({
    settings: { type: Object, required: true },
    canManage: { type: Boolean, default: false },
    filters: { type: Object, required: true },
    customers: { type: Array, default: () => [] },
    recent: { type: Array, default: () => [] },
});

const form = useForm({ ...props.settings });
const adjustForm = useForm({ points: '', note: '' });
const adjusting = ref(null);
const q = ref(props.filters.q);
let timer = null;

watch(q, () => {
    clearTimeout(timer);
    timer = setTimeout(() => router.get(route('loyalty.index'), { q: q.value || undefined }, { preserveState: true, replace: true }), 350);
});

const example = computed(() => {
    const spend = Number(form.spend_per_point) || 0;
    const target = Number(form.points_for_reward) || 0;
    if (!spend || !target) return '';
    return `Contoh: pelanggan belanja ${formatRupiah(spend * target)} total, dapat ${target} poin, lalu bisa tukar potongan ${formatRupiah(form.reward_value)}. Nilai hadiah = ${((Number(form.reward_value) / (spend * target)) * 100).toFixed(1).replace('.', ',')}% dari belanja.`;
});

function save() {
    form.put(route('loyalty.update'), { preserveScroll: true });
}

function openAdjust(c) {
    adjusting.value = c;
    adjustForm.reset();
    adjustForm.clearErrors();
}

function submitAdjust() {
    adjustForm.post(route('loyalty.adjust', adjusting.value.id), { preserveScroll: true, onSuccess: () => (adjusting.value = null) });
}
</script>

<template>
    <Head title="Poin Pelanggan" />
    <div class="mx-auto max-w-3xl">
        <PageHeader title="Poin Pelanggan" subtitle="Pelanggan setia dapat hadiah, jadi rajin kembali." :back-href="route('more')" />

        <section class="card mb-6 flex flex-col gap-4 p-5">
            <h2 class="text-xl font-extrabold text-ink">Aturan poin</h2>
            <div class="grid gap-4 sm:grid-cols-3">
                <MoneyInput v-model="form.spend_per_point" label="Belanja per 1 poin" :error="form.errors.spend_per_point" />
                <BigInput v-model="form.points_for_reward" label="Poin untuk ditukar" type="number" inputmode="numeric" :error="form.errors.points_for_reward" />
                <MoneyInput v-model="form.reward_value" label="Potongan yang didapat" :error="form.errors.reward_value" />
            </div>
            <p class="rounded-2xl bg-surface-2 p-4 text-lg text-ink">{{ example }}</p>
            <div class="flex items-center justify-between gap-3">
                <span>
                    <span class="block text-lg font-bold text-ink">Kirim WhatsApp saat poin bertambah</span>
                    <span class="block text-base text-ink-soft">Memakai kuota pesan WhatsApp paket Anda.</span>
                </span>
                <ToggleSwitch v-model="form.notify" label="Kirim WhatsApp poin" />
            </div>
            <BigButton v-if="canManage" block :loading="form.processing" @click="save">Simpan Aturan</BigButton>
            <p class="text-base text-ink-soft">Di kasir: pilih nama pelanggan, lalu ketuk <b>Tukar Poin</b> kalau poinnya cukup. Poin dari transaksi yang dibatalkan otomatis dikembalikan.</p>
        </section>

        <section class="mb-6">
            <h2 class="mb-3 text-xl font-extrabold text-ink">Pelanggan dengan poin</h2>
            <label class="relative mb-3 block">
                <span class="sr-only">Cari pelanggan</span>
                <Search :size="22" class="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-ink-soft" aria-hidden="true" />
                <input v-model="q" type="search" placeholder="Cari nama atau no HP" class="min-h-touch w-full rounded-2xl border-2 border-line bg-surface pr-4 pl-12 text-lg text-ink" />
            </label>
            <p v-if="!customers.length" class="card p-5 text-lg text-ink-soft">Belum ada pelanggan yang punya poin.</p>
            <ul v-else class="card divide-y divide-line">
                <li v-for="c in customers" :key="c.id" class="flex items-center gap-3 px-4 py-3">
                    <Gift :size="22" class="shrink-0 text-accent-ink" aria-hidden="true" />
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-lg font-bold text-ink">{{ c.name }}</span>
                        <span v-if="c.phone" class="block text-base text-ink-soft">{{ c.phone }}</span>
                    </span>
                    <span class="font-display text-xl font-extrabold text-ink">{{ c.points }} poin</span>
                    <button v-if="canManage" type="button" class="pressable min-h-12 rounded-2xl px-3 text-base font-bold text-primary-ink" @click="openAdjust(c)">Ubah</button>
                </li>
            </ul>
        </section>

        <section v-if="recent.length">
            <h2 class="mb-3 text-xl font-extrabold text-ink">Riwayat poin terakhir</h2>
            <ul class="card divide-y divide-line">
                <li v-for="e in recent" :key="e.id" class="flex items-center gap-3 px-4 py-3">
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-lg font-bold text-ink">{{ e.customer }}</span>
                        <span class="block truncate text-base text-ink-soft">{{ e.note }} · {{ e.time }}</span>
                    </span>
                    <span class="font-display text-lg font-extrabold" :class="e.points > 0 ? 'text-primary-ink' : 'text-danger-ink'">{{ e.points > 0 ? '+' : '' }}{{ e.points }}</span>
                </li>
            </ul>
        </section>

        <BottomSheet :open="!!adjusting" :title="`Ubah poin ${adjusting?.name ?? ''}`" @update:open="(v) => !v && (adjusting = null)">
            <form class="flex flex-col gap-4" @submit.prevent="submitAdjust">
                <BigInput v-model="adjustForm.points" label="Tambah / kurangi poin" inputmode="numeric" hint="Contoh: 5 untuk menambah, -5 untuk mengurangi." :error="adjustForm.errors.points" />
                <BigInput v-model="adjustForm.note" label="Alasan" placeholder="Contoh: hadiah ulang tahun" :error="adjustForm.errors.note" />
                <BigButton type="submit" block :loading="adjustForm.processing">Simpan</BigButton>
            </form>
        </BottomSheet>
    </div>
</template>
