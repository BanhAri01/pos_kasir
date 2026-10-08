<script setup>
/**
 * Daftar dalam 2 langkah:
 *   1. Pilih jenis usaha (kartu besar bergambar)
 *   2. Isi nama usaha, nama, no HP, kata sandi
 */
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ChevronLeft, CircleCheck } from 'lucide-vue-next';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import AppIcon from '@/Components/ui/AppIcon.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import BigInput from '@/Components/ui/BigInput.vue';

defineOptions({ layout: GuestLayout });

const props = defineProps({
    categories: { type: Array, required: true },
    referral: { type: Object, default: null },
    trialDays: { type: Number, default: 14 },
});

const step = ref(1);

const form = useForm({
    business_type: '',
    business_name: '',
    owner_name: '',
    phone: '',
    password: '',
    ref: props.referral?.code ?? '',
});

const selectedType = computed(() =>
    props.categories.flatMap((c) => c.types).find((t) => t.code === form.business_type),
);

function chooseType(code) {
    form.business_type = code;
    form.clearErrors('business_type');
    step.value = 2;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function submit() {
    form.post(route('register.store'), {
        onError: (errors) => {
            if (errors.business_type) step.value = 1;
        },
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Daftar" />

    <p v-if="referral" class="mb-5 rounded-2xl bg-accent-soft p-4 text-lg font-bold text-accent-ink">
        Diajak oleh {{ referral.name }}. Anda dapat bonus coba gratis {{ referral.bonus_days }} hari.
    </p>

    <!-- Langkah 1: pilih jenis usaha -->
    <section v-if="step === 1">
        <p class="text-lg font-bold text-primary-ink">Langkah 1 dari 2</p>
        <h1 class="mt-1 text-3xl font-extrabold text-ink sm:text-4xl">Usaha Anda apa?</h1>
        <p class="mt-2 text-lg text-ink-soft">Ketuk salah satu. Bisa diubah lagi nanti.</p>

        <p v-if="form.errors.business_type" role="alert" class="mt-4 text-lg font-bold text-danger-ink">
            {{ form.errors.business_type }}
        </p>

        <div v-for="category in categories" :key="category.key" class="mt-8">
            <h2 class="mb-3 text-xl font-extrabold text-ink">{{ category.label }}</h2>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                <button
                    v-for="type in category.types"
                    :key="type.code"
                    type="button"
                    class="card pressable flex min-h-40 flex-col items-center justify-center gap-2 border-2 p-4 text-center"
                    :class="form.business_type === type.code ? 'border-primary bg-primary-soft' : 'border-transparent hover:border-line'"
                    @click="chooseType(type.code)"
                >
                    <span class="flex size-16 items-center justify-center rounded-2xl bg-primary-soft text-primary-ink">
                        <AppIcon :name="type.icon" :size="34" />
                    </span>
                    <span class="font-display text-lg leading-tight font-extrabold text-ink">{{ type.name }}</span>
                    <span class="text-base leading-snug text-ink-soft">{{ type.description }}</span>
                </button>
            </div>
        </div>

        <p class="mt-10 text-center text-lg text-ink-soft">
            Sudah punya akun?
            <Link :href="route('login')" class="font-extrabold text-primary-ink underline underline-offset-4">Masuk di sini</Link>
        </p>
    </section>

    <!-- Langkah 2: data usaha & akun -->
    <section v-else>
        <button
            type="button"
            class="pressable -ml-2 mb-2 inline-flex min-h-12 items-center gap-1 rounded-xl px-2 text-lg font-bold text-ink-soft hover:bg-ink/5"
            @click="step = 1"
        >
            <ChevronLeft :size="26" aria-hidden="true" />
            Ganti jenis usaha
        </button>

        <p class="text-lg font-bold text-primary-ink">Langkah 2 dari 2</p>
        <h1 class="mt-1 text-3xl font-extrabold text-ink sm:text-4xl">Isi data usaha</h1>

        <div v-if="selectedType" class="mt-4 flex items-center gap-3 rounded-2xl bg-primary-soft p-4">
            <CircleCheck :size="28" class="shrink-0 text-primary-ink" aria-hidden="true" />
            <p class="text-lg text-ink">
                Jenis usaha: <strong>{{ selectedType.name }}</strong>
            </p>
        </div>

        <form class="mt-6 flex flex-col gap-5" @submit.prevent="submit">
            <BigInput
                v-model="form.business_name"
                label="Nama usaha"
                placeholder="Contoh: Warung Bu Sri"
                autocomplete="organization"
                :error="form.errors.business_name"
            />
            <BigInput
                v-model="form.owner_name"
                label="Nama Anda"
                placeholder="Contoh: Sri Wahyuni"
                autocomplete="name"
                :error="form.errors.owner_name"
            />
            <BigInput
                v-model="form.phone"
                label="No HP (WhatsApp)"
                type="tel"
                inputmode="tel"
                placeholder="0812 3456 7890"
                autocomplete="tel"
                hint="Dipakai untuk masuk ke aplikasi."
                :error="form.errors.phone"
            />
            <BigInput
                v-model="form.password"
                label="Buat kata sandi"
                type="password"
                autocomplete="new-password"
                hint="Minimal 6 huruf atau angka. Simpan baik-baik ya."
                :error="form.errors.password"
            />

            <BigButton type="submit" size="large" block :loading="form.processing" class="mt-2">
                Daftar Sekarang
            </BigButton>
            <p class="text-center text-base text-ink-soft">Gratis dicoba {{ trialDays + (referral?.bonus_days ?? 0) }} hari. Tidak perlu kartu kredit.</p>
        </form>
    </section>
</template>
