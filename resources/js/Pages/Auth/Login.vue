<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { KeyRound } from 'lucide-vue-next';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import BigInput from '@/Components/ui/BigInput.vue';

defineOptions({ layout: GuestLayout });

defineProps({
    canUsePin: { type: Boolean, default: false },
});

const form = useForm({
    phone: '',
    password: '',
});

function submit() {
    form.post(route('login.store'), {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Masuk" />

    <h1 class="text-3xl font-extrabold text-ink sm:text-4xl">Selamat datang kembali</h1>
    <p class="mt-2 text-lg text-ink-soft">Masuk untuk pemilik usaha dan manajer.</p>

    <form class="mt-8 flex flex-col gap-5" @submit.prevent="submit">
        <BigInput
            v-model="form.phone"
            label="No HP"
            type="tel"
            inputmode="tel"
            placeholder="0812 3456 7890"
            autocomplete="tel"
            :error="form.errors.phone"
        />
        <BigInput
            v-model="form.password"
            label="Kata sandi"
            type="password"
            autocomplete="current-password"
            :error="form.errors.password"
        />
        <BigButton type="submit" size="large" block :loading="form.processing">Masuk</BigButton>
    </form>

    <div v-if="canUsePin" class="card mt-8 p-5">
        <p class="text-lg font-bold text-ink">Kasir atau karyawan?</p>
        <p class="mt-1 text-lg text-ink-soft">Masuk cukup dengan memilih nama dan mengetik PIN.</p>
        <BigButton :href="route('pin.create')" variant="soft" block class="mt-4">
            <KeyRound :size="24" aria-hidden="true" />
            Masuk dengan PIN
        </BigButton>
    </div>

    <p class="mt-10 text-center text-lg text-ink-soft">
        Belum punya akun?
        <Link :href="route('register')" class="font-extrabold text-primary-ink underline underline-offset-4">Daftar gratis</Link>
    </p>
</template>
