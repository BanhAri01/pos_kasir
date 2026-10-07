<script setup>
/**
 * Form karyawan, satu kolom. Kolom no HP & kata sandi hanya muncul untuk Manajer,
 * karena kasir & karyawan cukup masuk dengan PIN.
 */
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { CircleCheck } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import BigInput from '@/Components/ui/BigInput.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    staff: { type: Object, default: null },
    roles: { type: Array, required: true },
    outlets: { type: Array, required: true },
});

const isEdit = !!props.staff;
const isSelf = props.staff?.is_self ?? false;

const form = useForm({
    name: props.staff?.name ?? '',
    role: isSelf ? undefined : (props.staff?.role ?? props.roles.at(-2)?.value ?? props.roles[0]?.value),
    job_title: props.staff?.job_title ?? '',
    // Kalau hanya ada 1 outlet, langsung dipilih dan kolomnya disembunyikan.
    outlet_ids: props.staff?.outlet_ids ?? (props.outlets.length === 1 ? [props.outlets[0].id] : []),
    pin: '',
    phone: props.staff?.phone ?? '',
    password: '',
});

const needsPhoneLogin = computed(() => form.role === 'manager' || (isSelf && props.staff?.has_password));

function toggleOutlet(id) {
    form.outlet_ids = form.outlet_ids.includes(id)
        ? form.outlet_ids.filter((x) => x !== id)
        : [...form.outlet_ids, id];
}

function submit() {
    const options = { onFinish: () => form.reset('pin', 'password') };
    if (isEdit) {
        form.put(route('staff.update', props.staff.id), options);
    } else {
        form.post(route('staff.store'), options);
    }
}
</script>

<template>
    <Head :title="isEdit ? 'Ubah Karyawan' : 'Tambah Karyawan'" />

    <div class="mx-auto max-w-2xl">
        <PageHeader :title="isEdit ? 'Ubah Karyawan' : 'Tambah Karyawan'" :back-href="route('staff.index')" help="staff-form" />

        <form class="card flex flex-col gap-6 p-5 sm:p-8" @submit.prevent="submit">
            <BigInput v-model="form.name" label="Nama" placeholder="Contoh: Budi" :error="form.errors.name" />

            <!-- Jabatan: kartu pilihan dengan penjelasan -->
            <fieldset v-if="!isSelf">
                <legend class="mb-2 text-lg font-bold text-ink">Jabatan</legend>
                <div class="flex flex-col gap-3">
                    <label
                        v-for="role in roles"
                        :key="role.value"
                        class="flex min-h-touch cursor-pointer items-start gap-3 rounded-2xl border-2 p-4 transition-colors"
                        :class="form.role === role.value ? 'border-primary bg-primary-soft' : 'border-line'"
                    >
                        <input v-model="form.role" type="radio" name="role" :value="role.value" class="mt-1 size-6 accent-(--color-primary)" />
                        <span>
                            <span class="block text-lg font-extrabold text-ink">{{ role.label }}</span>
                            <span class="block text-base text-ink-soft">{{ role.description }}</span>
                        </span>
                    </label>
                </div>
                <p v-if="form.errors.role" role="alert" class="mt-2 text-base font-bold text-danger-ink">{{ form.errors.role }}</p>
            </fieldset>

            <BigInput
                v-model="form.job_title"
                label="Sebutan pekerjaan"
                optional
                placeholder="Contoh: Barista, Kapster, Tukang Masak"
                :error="form.errors.job_title"
            />

            <!-- Outlet: hanya tampil kalau outletnya lebih dari satu -->
            <fieldset v-if="outlets.length > 1">
                <legend class="mb-2 text-lg font-bold text-ink">Bekerja di outlet</legend>
                <div class="flex flex-col gap-3">
                    <button
                        v-for="outlet in outlets"
                        :key="outlet.id"
                        type="button"
                        role="checkbox"
                        :aria-checked="form.outlet_ids.includes(outlet.id)"
                        class="pressable flex min-h-touch items-center gap-3 rounded-2xl border-2 px-4 text-left text-lg font-bold"
                        :class="form.outlet_ids.includes(outlet.id) ? 'border-primary bg-primary-soft text-ink' : 'border-line text-ink-soft'"
                        @click="toggleOutlet(outlet.id)"
                    >
                        <CircleCheck v-if="form.outlet_ids.includes(outlet.id)" :size="26" class="text-primary-ink" aria-hidden="true" />
                        <span v-else class="size-6 rounded-full border-2 border-ink-soft" />
                        {{ outlet.name }}
                    </button>
                </div>
                <p v-if="form.errors.outlet_ids" role="alert" class="mt-2 text-base font-bold text-danger-ink">{{ form.errors.outlet_ids }}</p>
            </fieldset>

            <BigInput
                v-model="form.pin"
                :label="isEdit ? 'PIN baru' : 'PIN (4-6 angka)'"
                type="password"
                inputmode="numeric"
                maxlength="6"
                autocomplete="new-password"
                :optional="isEdit"
                :hint="isEdit ? 'Kosongkan kalau PIN tidak diganti.' : 'Dipakai untuk masuk cepat. Beri tahu karyawan PIN-nya.'"
                :error="form.errors.pin"
            />

            <template v-if="needsPhoneLogin">
                <BigInput
                    v-model="form.phone"
                    label="No HP untuk masuk"
                    type="tel"
                    inputmode="tel"
                    placeholder="0812 3456 7890"
                    :error="form.errors.phone"
                />
                <BigInput
                    v-model="form.password"
                    :label="staff?.has_password ? 'Kata sandi baru' : 'Kata sandi'"
                    type="password"
                    autocomplete="new-password"
                    :optional="staff?.has_password"
                    :hint="staff?.has_password ? 'Kosongkan kalau tidak diganti.' : 'Minimal 6 huruf atau angka.'"
                    :error="form.errors.password"
                />
            </template>

            <BigButton type="submit" size="large" block :loading="form.processing">Simpan</BigButton>
        </form>
    </div>
</template>
