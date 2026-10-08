<script setup>
import { computed, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { MessageCircle } from 'lucide-vue-next';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import BigInput from '@/Components/ui/BigInput.vue';

defineOptions({ layout: GuestLayout });

const page = usePage();
const phone = ref('');
const business = ref('');

const ready = computed(() => phone.value.replace(/\D/g, '').length >= 9);
const waUrl = computed(() => {
    const text = [
        'Halo admin Hermes POS, saya lupa kata sandi.',
        `No HP akun: ${phone.value.trim()}`,
        business.value.trim() ? `Nama usaha: ${business.value.trim()}` : null,
        'Mohon dibantu buatkan kata sandi baru. Terima kasih.',
    ].filter(Boolean).join('\n');
    return `https://wa.me/${page.props.adminWhatsapp}?text=${encodeURIComponent(text)}`;
});
</script>

<template>
    <Head title="Lupa Kata Sandi" />

    <h1 class="text-3xl font-extrabold text-ink sm:text-4xl">Lupa kata sandi?</h1>
    <p class="mt-2 text-lg text-ink-soft">Tidak apa-apa. Admin Hermes akan membuatkan kata sandi baru lewat WhatsApp.</p>

    <ol class="mt-6 flex flex-col gap-2 text-lg text-ink">
        <li><b>1.</b> Isi no HP yang dipakai untuk masuk.</li>
        <li><b>2.</b> Ketuk tombol Kirim lewat WhatsApp, aplikasi WhatsApp akan terbuka.</li>
        <li><b>3.</b> Kirim pesannya. Admin akan membalas dengan kata sandi baru.</li>
    </ol>

    <div class="mt-6 flex flex-col gap-5">
        <BigInput v-model="phone" label="No HP akun" type="tel" inputmode="tel" placeholder="0812 3456 7890" autocomplete="tel" />
        <BigInput v-model="business" label="Nama usaha" optional placeholder="Contoh: Kedai Kopi Bu Sri" />
        <BigButton :href="ready ? waUrl : null" external :disabled="!ready" size="large" block>
            <MessageCircle :size="24" aria-hidden="true" />
            Kirim lewat WhatsApp
        </BigButton>
        <p class="text-base text-ink-soft">Demi keamanan, admin hanya mengganti kata sandi untuk no HP pemilik akun yang terdaftar.</p>
    </div>

    <p class="mt-10 text-center text-lg text-ink-soft">
        Sudah ingat?
        <Link :href="route('login')" class="font-extrabold text-primary-ink underline underline-offset-4">Kembali masuk</Link>
    </p>
</template>
