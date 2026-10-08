<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Copy, KeyRound, LogIn, MessageCircle, Snowflake, Sun } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import BigInput from '@/Components/ui/BigInput.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { formatRupiah } from '@/composables/useRupiah';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    tenant: { type: Object, required: true },
    usage: { type: Object, required: true },
    plans: { type: Array, required: true },
    payments: { type: Array, default: () => [] },
    newPassword: { type: String, default: null },
});

const form = useForm({ plan: props.tenant.plan, months: 1, amount: '', note: '' });
const confirmReset = ref(false);
const confirmSuspend = ref(false);
const copied = ref(false);

const statusText = computed(() => {
    const t = props.tenant;
    if (t.suspended) return 'Dibekukan';
    if (t.unlimited) return `${t.plan_label}, tanpa batas waktu`;
    if (!t.has_access) return `Habis sejak ${t.ends_at}`;
    if (t.on_trial) return `Masa coba ${t.plan_label} sampai ${t.ends_at} (${t.days_left} hari)`;
    return `${t.plan_label} aktif sampai ${t.ends_at} (${t.days_left} hari)`;
});

const passwordWaUrl = computed(() => {
    if (!props.newPassword || !props.tenant.owner?.phone_raw) return null;
    const text = `Halo ${props.tenant.owner.name}, ini kata sandi baru Hermes POS untuk ${props.tenant.name}:\n\n*${props.newPassword}*\n\nMasuk dengan no HP ${props.tenant.owner.phone}. Setelah masuk, segera ganti kata sandi ini.`;
    return `https://wa.me/${props.tenant.owner.phone_raw}?text=${encodeURIComponent(text)}`;
});

function extend() {
    form.transform((d) => ({ ...d, amount: d.amount === '' ? null : Number(d.amount) })).post(route('admin.tenants.extend', props.tenant.id), { preserveScroll: true, onSuccess: () => form.reset('amount', 'note') });
}

async function copyPassword() {
    try {
        await navigator.clipboard.writeText(props.newPassword);
        copied.value = true;
    } catch {
        copied.value = false;
    }
}
</script>

<template>
    <Head :title="tenant.name" />
    <PageHeader :title="tenant.name" :subtitle="`${tenant.type} · terdaftar ${tenant.created_at}`" :back-href="route('admin.tenants.index')" />

    <section class="card mb-6 grid gap-4 p-5 md:grid-cols-2">
        <div>
            <p class="text-base text-ink-soft">Status langganan</p>
            <p class="text-xl font-extrabold text-ink">{{ statusText }}</p>
        </div>
        <div v-if="tenant.owner">
            <p class="text-base text-ink-soft">Pemilik</p>
            <p class="text-xl font-extrabold text-ink">{{ tenant.owner.name }} · {{ tenant.owner.phone }}</p>
            <p class="text-base text-ink-soft">Terakhir masuk: {{ tenant.owner.last_login ?? 'belum pernah' }}</p>
        </div>
        <div v-if="tenant.address" class="md:col-span-2">
            <p class="text-base text-ink-soft">Alamat</p>
            <p class="text-lg text-ink">{{ tenant.address }}</p>
        </div>
        <ul class="grid gap-3 sm:grid-cols-3 md:col-span-2">
            <li class="rounded-2xl bg-surface-2 p-3"><p class="text-base text-ink-soft">Outlet</p><p class="text-xl font-extrabold text-ink">{{ usage.outlets.used }} / {{ usage.outlets.limit ?? '∞' }}</p></li>
            <li class="rounded-2xl bg-surface-2 p-3"><p class="text-base text-ink-soft">Karyawan</p><p class="text-xl font-extrabold text-ink">{{ usage.staff.used }} / {{ usage.staff.limit ?? '∞' }}</p></li>
            <li class="rounded-2xl bg-surface-2 p-3"><p class="text-base text-ink-soft">WhatsApp bulan ini</p><p class="text-xl font-extrabold text-ink">{{ usage.whatsapp.used }} / {{ usage.whatsapp.limit ?? '∞' }}</p></li>
        </ul>
    </section>

    <section v-if="newPassword" class="mb-6 rounded-3xl bg-accent-soft p-5">
        <p class="text-lg font-bold text-accent-ink">Kata sandi baru (hanya tampil sekali):</p>
        <p class="my-2 font-mono text-3xl font-extrabold tracking-wider text-ink">{{ newPassword }}</p>
        <div class="flex flex-wrap gap-3">
            <BigButton variant="secondary" @click="copyPassword"><Copy :size="22" aria-hidden="true" /> {{ copied ? 'Tersalin' : 'Salin' }}</BigButton>
            <BigButton v-if="passwordWaUrl" :href="passwordWaUrl" external><MessageCircle :size="22" aria-hidden="true" /> Kirim ke pemilik</BigButton>
        </div>
    </section>

    <section class="card mb-6 flex flex-col gap-4 p-5">
        <h2 class="text-xl font-extrabold text-ink">Perpanjang manual (bayar transfer / tunai)</h2>
        <label class="block">
            <span class="mb-2 block text-lg font-bold text-ink">Paket</span>
            <select v-model="form.plan" class="min-h-touch w-full rounded-2xl border-2 border-line bg-surface px-4 text-lg text-ink">
                <option v-for="p in plans" :key="p.value" :value="p.value">{{ p.label }}</option>
            </select>
        </label>
        <div class="grid gap-4 sm:grid-cols-2">
            <BigInput v-model="form.months" label="Lama (bulan)" type="number" inputmode="numeric" :error="form.errors.months" />
            <BigInput v-model="form.amount" label="Uang diterima" prefix="Rp" inputmode="numeric" optional hint="Kosongkan untuk memakai harga paket." :error="form.errors.amount" />
        </div>
        <BigInput v-model="form.note" label="Catatan" optional placeholder="Contoh: transfer BCA 9 Okt" :error="form.errors.note" />
        <BigButton block :loading="form.processing" @click="extend">Perpanjang</BigButton>
    </section>

    <section class="card mb-6 grid gap-3 p-5 sm:grid-cols-2">
        <BigButton variant="secondary" @click="confirmReset = true"><KeyRound :size="22" aria-hidden="true" /> Buat kata sandi baru</BigButton>
        <BigButton variant="secondary" @click="router.post(route('admin.tenants.impersonate', tenant.id))"><LogIn :size="22" aria-hidden="true" /> Masuk sebagai pemilik</BigButton>
        <BigButton v-if="!tenant.unlimited" variant="soft" @click="router.post(route('admin.tenants.unlimited', tenant.id), {}, { preserveScroll: true })">Aktifkan tanpa batas waktu</BigButton>
        <BigButton :variant="tenant.suspended ? 'primary' : 'danger'" @click="confirmSuspend = true">
            <component :is="tenant.suspended ? Sun : Snowflake" :size="22" aria-hidden="true" />
            {{ tenant.suspended ? 'Aktifkan kembali' : 'Bekukan usaha' }}
        </BigButton>
    </section>

    <section v-if="payments.length">
        <h2 class="mb-3 text-xl font-extrabold text-ink">Riwayat pembayaran</h2>
        <ul class="card divide-y divide-line">
            <li v-for="p in payments" :key="p.reference" class="flex flex-wrap items-center gap-3 px-4 py-3">
                <span class="min-w-0 flex-1">
                    <span class="block text-lg font-bold text-ink">{{ p.plan }} {{ p.months }} bln · {{ formatRupiah(p.amount) }}</span>
                    <span class="block text-base text-ink-soft">{{ p.created_at }} · {{ p.channel }}<template v-if="p.note"> · {{ p.note }}</template></span>
                </span>
                <span class="rounded-full bg-surface-2 px-3 py-1 text-base font-bold text-ink">{{ p.status_label }}</span>
            </li>
        </ul>
    </section>

    <ConfirmDialog
        v-model:open="confirmReset"
        title="Buat kata sandi baru?"
        message="Kata sandi lama tidak bisa dipakai lagi dan pemilik akan keluar dari semua HP. Pastikan yang meminta benar-benar pemilik usaha ini."
        confirm-text="Ya, Buat Baru" danger
        @confirm="confirmReset = false; router.post(route('admin.tenants.password', tenant.id), {}, { preserveScroll: true })"
    />
    <ConfirmDialog
        v-model:open="confirmSuspend"
        :title="tenant.suspended ? 'Aktifkan kembali usaha ini?' : 'Bekukan usaha ini?'"
        :message="tenant.suspended ? 'Pemilik dan karyawan bisa masuk lagi.' : 'Semua pengguna usaha ini langsung keluar dan tidak bisa masuk sampai diaktifkan lagi.'"
        :confirm-text="tenant.suspended ? 'Ya, Aktifkan' : 'Ya, Bekukan'"
        @confirm="confirmSuspend = false; router.post(route('admin.tenants.suspend', tenant.id), { suspend: !tenant.suspended }, { preserveScroll: true })"
    />
</template>
