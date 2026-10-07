<script setup>
/**
 * Pengaturan WhatsApp. Default: memakai nomor pusat Hermes (langsung jalan, tanpa atur apa-apa).
 * Opsional: nomor usaha sendiri lewat Fonnte / Wablas / WhatsApp Cloud API.
 */
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { CheckCircle2, Clock, Send, XCircle } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import BigInput from '@/Components/ui/BigInput.vue';
import BottomSheet from '@/Components/ui/BottomSheet.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import ToggleSwitch from '@/Components/ui/ToggleSwitch.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    account: { type: Object, default: null },
    templates: { type: Array, required: true },
    messages: { type: Array, required: true },
});

const PROVIDERS = [
    { value: 'fonnte', label: 'Fonnte', hint: 'Token ada di menu Device di fonnte.com.' },
    { value: 'wablas', label: 'Wablas', hint: 'Token & alamat server ada di dashboard Wablas.' },
    { value: 'cloud_api', label: 'WhatsApp Cloud API', hint: 'Dari Meta for Developers (untuk usaha besar).' },
];

const accountForm = useForm({
    mode: props.account?.is_active ? 'own' : 'central',
    provider: props.account?.provider ?? 'fonnte',
    sender_phone: props.account?.sender_phone ?? '',
    token: '',
    domain: props.account?.domain ?? 'https://solo.wablas.com',
    phone_number_id: props.account?.phone_number_id ?? '',
});
const testForm = useForm({ phone: '' });
const editing = ref(null);
const templateForm = useForm({ body: '', is_active: true });

function saveAccount() {
    accountForm.put(route('settings.whatsapp.account'), { preserveScroll: true, onSuccess: () => accountForm.reset('token') });
}

function sendTest() {
    testForm.post(route('settings.whatsapp.test'), { preserveScroll: true });
}

function editTemplate(t) {
    editing.value = t;
    templateForm.body = t.body;
    templateForm.is_active = t.is_active;
    templateForm.clearErrors();
}

function saveTemplate(reset = false) {
    templateForm
        .transform((d) => ({ ...d, reset }))
        .put(route('settings.whatsapp.template', editing.value.code), { preserveScroll: true, onSuccess: () => (editing.value = null) });
}

function toggleTemplate(t, value) {
    router.put(route('settings.whatsapp.template', t.code), { body: t.body, is_active: value }, { preserveScroll: true });
}

const STATUS = { sent: [CheckCircle2, 'text-primary-ink', 'Terkirim'], failed: [XCircle, 'text-danger-ink', 'Gagal'], queued: [Clock, 'text-ink-soft', 'Menunggu'] };
</script>

<template>
    <Head title="WhatsApp" />
    <div class="mx-auto max-w-3xl">
        <PageHeader title="WhatsApp" subtitle="Struk, pengingat, dan kabar pesanan untuk pelanggan." :back-href="route('more')" />

        <section class="card mb-6 flex flex-col gap-4 p-5">
            <h2 class="text-xl font-extrabold text-ink">Nomor pengirim</h2>
            <SegmentedControl v-model="accountForm.mode" label="Nomor pengirim" :options="[{ value: 'central', label: 'Nomor pusat Hermes' }, { value: 'own', label: 'Nomor usaha sendiri' }]" />
            <p v-if="accountForm.mode === 'central'" class="rounded-2xl bg-primary-soft p-4 text-lg text-primary-ink">
                Pesan dikirim dari nomor resmi Hermes atas nama usaha Anda. Tidak perlu mengatur apa pun.
            </p>
            <template v-else>
                <label class="block">
                    <span class="mb-2 block text-lg font-bold text-ink">Penyedia</span>
                    <select v-model="accountForm.provider" class="min-h-touch w-full rounded-2xl border-2 border-line bg-surface px-4 text-lg text-ink">
                        <option v-for="p in PROVIDERS" :key="p.value" :value="p.value">{{ p.label }}</option>
                    </select>
                    <span class="mt-2 block text-base text-ink-soft">{{ PROVIDERS.find((p) => p.value === accountForm.provider)?.hint }}</span>
                </label>
                <BigInput v-model="accountForm.sender_phone" label="No WhatsApp usaha" inputmode="tel" optional :error="accountForm.errors.sender_phone" />
                <BigInput
                    v-model="accountForm.token"
                    label="Token"
                    type="password"
                    :hint="account?.has_token ? 'Token sudah tersimpan. Kosongkan kalau tidak ingin mengganti.' : ''"
                    :error="accountForm.errors.token"
                />
                <BigInput v-if="accountForm.provider === 'wablas'" v-model="accountForm.domain" label="Alamat server Wablas" :error="accountForm.errors.domain" />
                <BigInput v-if="accountForm.provider === 'cloud_api'" v-model="accountForm.phone_number_id" label="Phone Number ID" :error="accountForm.errors.phone_number_id" />
            </template>
            <BigButton block :loading="accountForm.processing" @click="saveAccount">Simpan</BigButton>
        </section>

        <section class="card mb-6 flex flex-col gap-4 p-5">
            <h2 class="text-xl font-extrabold text-ink">Kirim pesan uji</h2>
            <form class="grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end" @submit.prevent="sendTest">
                <BigInput v-model="testForm.phone" label="No HP tujuan" inputmode="tel" placeholder="0812 3456 7890" :error="testForm.errors.phone" />
                <BigButton type="submit" :loading="testForm.processing"><Send :size="22" aria-hidden="true" /> Kirim</BigButton>
            </form>
        </section>

        <section class="mb-6">
            <h2 class="mb-1 text-xl font-extrabold text-ink">Isi pesan</h2>
            <p class="mb-3 text-base text-ink-soft">Ketuk untuk mengubah. Kata dalam kurung kurawal seperti {nama} diganti otomatis.</p>
            <ul class="flex flex-col gap-3">
                <li v-for="t in templates" :key="t.code" class="card flex flex-col gap-2 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-lg font-extrabold text-ink">{{ t.label }}</p>
                        <ToggleSwitch :model-value="t.is_active" :label="`Kirim ${t.label}`" @update:model-value="(v) => toggleTemplate(t, v)" />
                    </div>
                    <button type="button" class="pressable rounded-2xl bg-surface-2 p-3 text-left text-base whitespace-pre-line text-ink" @click="editTemplate(t)">{{ t.body }}</button>
                </li>
            </ul>
        </section>

        <section>
            <h2 class="mb-3 text-xl font-extrabold text-ink">Pesan terakhir</h2>
            <p v-if="!messages.length" class="text-lg text-ink-soft">Belum ada pesan terkirim.</p>
            <ul v-else class="card divide-y divide-line">
                <li v-for="m in messages" :key="m.id" class="flex items-start gap-3 px-4 py-3">
                    <component :is="STATUS[m.status]?.[0] ?? Clock" :size="22" class="mt-0.5 shrink-0" :class="STATUS[m.status]?.[1]" aria-hidden="true" />
                    <span class="min-w-0 flex-1">
                        <span class="block text-lg font-bold text-ink">{{ m.template }} · {{ m.to }}</span>
                        <span class="block truncate text-base text-ink-soft">{{ m.body }}</span>
                        <span v-if="m.error" class="block text-base text-danger-ink">{{ m.error }}</span>
                    </span>
                    <span class="shrink-0 text-base text-ink-soft">{{ m.time }}</span>
                </li>
            </ul>
        </section>
    </div>

    <BottomSheet :open="!!editing" :title="editing?.label ?? ''" @update:open="(v) => !v && (editing = null)">
        <form v-if="editing" class="flex flex-col gap-4" @submit.prevent="saveTemplate(false)">
            <label class="block">
                <span class="mb-2 block text-lg font-bold text-ink">Isi pesan</span>
                <textarea v-model="templateForm.body" rows="8" class="w-full rounded-2xl border-2 border-line bg-surface p-4 text-lg text-ink focus:border-focus focus:outline-none" />
                <span v-if="templateForm.errors.body" class="mt-2 block text-base font-bold text-danger-ink">{{ templateForm.errors.body }}</span>
            </label>
            <BigButton type="submit" block size="large" :loading="templateForm.processing">Simpan</BigButton>
            <BigButton v-if="editing.is_custom" variant="ghost" block @click="saveTemplate(true)">Kembalikan ke pesan bawaan</BigButton>
        </form>
    </BottomSheet>
</template>
