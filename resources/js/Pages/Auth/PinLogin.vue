<script setup>
/**
 * Masuk cepat: ketuk nama/foto sendiri, lalu ketik PIN di papan angka besar.
 */
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ChevronLeft } from 'lucide-vue-next';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PinPad from '@/Components/ui/PinPad.vue';

defineOptions({ layout: GuestLayout });

const props = defineProps({
    businessName: { type: String, required: true },
    staff: { type: Array, required: true },
});

const form = useForm({ user_id: null, pin: '' });

const selected = computed(() => props.staff.find((s) => s.id === form.user_id));

function initials(name) {
    return name.split(' ').slice(0, 2).map((w) => w[0]).join('').toUpperCase();
}

function choose(id) {
    form.user_id = id;
    form.pin = '';
    form.clearErrors();
}

function submit() {
    form.post(route('pin.store'), {
        onError: () => (form.pin = ''),
    });
}
</script>

<template>
    <Head title="Masuk dengan PIN" />

    <!-- Pilih nama -->
    <section v-if="!selected">
        <p class="text-lg font-bold text-primary-ink">{{ businessName }}</p>
        <h1 class="mt-1 text-3xl font-extrabold text-ink sm:text-4xl">Siapa yang mau masuk?</h1>
        <p class="mt-2 text-lg text-ink-soft">Ketuk nama Anda.</p>

        <div v-if="staff.length" class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3">
            <button
                v-for="person in staff"
                :key="person.id"
                type="button"
                class="card pressable flex min-h-40 flex-col items-center justify-center gap-2 border-2 border-transparent p-4 hover:border-line"
                @click="choose(person.id)"
            >
                <img v-if="person.photo_url" :src="person.photo_url" alt="" class="size-16 rounded-full object-cover" />
                <span
                    v-else
                    class="flex size-16 items-center justify-center rounded-full bg-primary-soft font-display text-2xl font-extrabold text-primary-ink"
                >
                    {{ initials(person.name) }}
                </span>
                <span class="font-display text-lg leading-tight font-extrabold text-ink">{{ person.name }}</span>
                <span class="text-base text-ink-soft">{{ person.role_label }}</span>
            </button>
        </div>

        <EmptyState
            v-else
            class="mt-6"
            title="Belum ada karyawan dengan PIN"
            message="Pemilik usaha bisa menambahkan karyawan dan PIN-nya di menu Lainnya, lalu Karyawan."
        />

        <p class="mt-10 text-center text-lg text-ink-soft">
            Pemilik atau manajer?
            <Link :href="route('login')" class="font-extrabold text-primary-ink underline underline-offset-4">Masuk dengan no HP</Link>
        </p>
    </section>

    <!-- Ketik PIN -->
    <section v-else>
        <button
            type="button"
            class="pressable -ml-2 mb-2 inline-flex min-h-12 items-center gap-1 rounded-xl px-2 text-lg font-bold text-ink-soft hover:bg-ink/5"
            @click="choose(null)"
        >
            <ChevronLeft :size="26" aria-hidden="true" />
            Bukan saya
        </button>

        <div class="mb-6 flex flex-col items-center text-center">
            <span class="flex size-20 items-center justify-center rounded-full bg-primary-soft font-display text-3xl font-extrabold text-primary-ink">
                {{ initials(selected.name) }}
            </span>
            <h1 class="mt-3 text-3xl font-extrabold text-ink">Halo, {{ selected.name }}</h1>
            <p class="mt-1 text-lg text-ink-soft">Ketik PIN Anda</p>
        </div>

        <PinPad v-model="form.pin" :error="form.errors.pin || form.errors.user_id" :loading="form.processing" @submit="submit" />
    </section>
</template>
