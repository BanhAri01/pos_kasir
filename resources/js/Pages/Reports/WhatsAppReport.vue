<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { Send } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import BigInput from '@/Components/ui/BigInput.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import ToggleSwitch from '@/Components/ui/ToggleSwitch.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    settings: { type: Object, required: true },
    recipient: { type: String, default: '' },
    preview: { type: String, default: null },
});

const form = useForm({ ...props.settings });

function save() {
    form.put(route('owner-report.update'), { preserveScroll: true });
}
</script>

<template>
    <Head title="Laporan ke WhatsApp" />
    <div class="mx-auto max-w-3xl">
        <PageHeader title="Laporan ke WhatsApp" subtitle="Ringkasan jualan dan untung dikirim ke HP pemilik tanpa perlu membuka aplikasi." :back-href="route('more')" />

        <section class="card mb-6 flex flex-col gap-5 p-5">
            <div class="flex items-center justify-between gap-3">
                <span>
                    <span class="block text-lg font-bold text-ink">Laporan harian</span>
                    <span class="block text-base text-ink-soft">Uang masuk, untung, cara bayar, menu terlaris, stok menipis.</span>
                </span>
                <ToggleSwitch v-model="form.daily" label="Laporan harian" />
            </div>
            <BigInput v-if="form.daily" v-model="form.daily_at" label="Jam kirim" type="time" :error="form.errors.daily_at" />
            <div class="flex items-center justify-between gap-3">
                <span>
                    <span class="block text-lg font-bold text-ink">Setiap tutup kasir</span>
                    <span class="block text-base text-ink-soft">Penjualan per kasir dan selisih uang di laci.</span>
                </span>
                <ToggleSwitch v-model="form.on_close" label="Laporan tutup kasir" />
            </div>
            <BigInput v-model="form.phone" label="No WhatsApp tujuan" inputmode="tel" optional :hint="`Kosongkan untuk memakai no pemilik (${recipient || 'belum ada'}).`" :error="form.errors.phone" />
            <p class="text-base text-ink-soft">Setiap laporan memakai 1 pesan dari kuota WhatsApp paket Anda.</p>
            <BigButton block :loading="form.processing" @click="save">Simpan</BigButton>
        </section>

        <section class="card p-5">
            <h2 class="mb-3 text-xl font-extrabold text-ink">Contoh laporan hari ini</h2>
            <pre v-if="preview" class="mb-4 rounded-2xl bg-surface-2 p-4 font-sans text-base whitespace-pre-wrap text-ink">{{ preview }}</pre>
            <p v-else class="mb-4 text-lg text-ink-soft">Belum ada penjualan hari ini.</p>
            <BigButton variant="secondary" block :disabled="!preview" @click="router.post(route('owner-report.test'), {}, { preserveScroll: true })">
                <Send :size="22" aria-hidden="true" /> Kirim Sekarang
            </BigButton>
        </section>
    </div>
</template>
