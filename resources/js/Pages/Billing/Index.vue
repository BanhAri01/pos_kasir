<script setup>
import { computed, ref } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { BadgeCheck, CalendarClock, Check, Copy, CreditCard, Gift, MessageCircle, QrCode, ShieldAlert } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import { formatRupiah } from '@/composables/useRupiah';

defineOptions({ layout: AppLayout });

const props = defineProps({
    subscription: { type: Object, required: true },
    catalog: { type: Object, required: true },
    usage: { type: Object, required: true },
    canPay: { type: Boolean, default: false },
    onlineAvailable: { type: Boolean, default: false },
    payments: { type: Array, default: () => [] },
    referral: { type: Object, default: null },
});

const page = usePage();
const referralCopied = ref(false);
const referralWa = computed(() => {
    if (!props.referral) return '';
    const text = `Saya pakai Hermes POS untuk kasir usaha, gampang dan bisa jalan walau internet mati. Daftar lewat link ini dapat bonus coba gratis ${props.referral.bonus_days} hari: ${props.referral.url}`;
    return `https://wa.me/?text=${encodeURIComponent(text)}`;
});

async function copyReferral() {
    try {
        await navigator.clipboard.writeText(props.referral.url);
        referralCopied.value = true;
    } catch {
        referralCopied.value = false;
    }
}
const form = useForm({ plan: props.subscription.plan, months: 1, channel: 'qris' });

const selectedPlan = computed(() => props.catalog.plans.find((p) => p.key === form.plan));
const options = computed(() => selectedPlan.value?.options?.[form.months] ?? []);
const selectedOption = computed(() => options.value.find((o) => o.key === form.channel));
const durationOptions = computed(() => props.catalog.durations.map((d) => ({ value: d.months, label: d.label })));

const statusText = computed(() => {
    const s = props.subscription;
    if (s.suspended) return 'Usaha sedang dibekukan. Hubungi admin.';
    if (s.unlimited) return `Paket ${s.plan_label} aktif tanpa batas waktu.`;
    if (!s.has_access) return 'Masa langganan sudah habis. Perpanjang supaya bisa jualan lagi.';
    if (s.in_grace) return `Masa langganan sudah lewat. Masih bisa dipakai ${s.grace_days_left} hari lagi, segera perpanjang.`;
    if (s.on_trial) return `Masa coba gratis paket ${s.plan_label}: ${s.days_left} hari lagi (sampai ${s.ends_at}).`;
    return `Paket ${s.plan_label} aktif sampai ${s.ends_at} (${s.days_left} hari lagi).`;
});
const statusTone = computed(() => (props.subscription.has_access && !props.subscription.in_grace ? 'bg-primary-soft text-primary-ink' : 'bg-danger-soft text-danger-ink'));

const adminUrl = computed(() => {
    const text = `Halo admin Hermes POS, saya ingin berlangganan paket ${selectedPlan.value?.label ?? ''} ${form.months} bulan untuk usaha ${page.props.tenant?.name ?? ''}.`;
    return `https://wa.me/${page.props.adminWhatsapp}?text=${encodeURIComponent(text)}`;
});

const usageRows = computed(() => [
    { label: 'Outlet', ...props.usage.outlets },
    { label: 'Karyawan', ...props.usage.staff },
    { label: 'Pesan WhatsApp bulan ini', ...props.usage.whatsapp },
]);

function pay() {
    form.post(route('billing.checkout'));
}
</script>

<template>
    <Head title="Langganan" />
    <div class="mx-auto max-w-4xl">
        <PageHeader title="Langganan" subtitle="Pilih paket, bayar online, langsung aktif." :back-href="route('more')" />

        <section class="mb-6 flex items-start gap-3 rounded-3xl p-5 text-lg font-bold" :class="statusTone">
            <component :is="subscription.has_access && !subscription.in_grace ? BadgeCheck : ShieldAlert" :size="28" class="shrink-0" aria-hidden="true" />
            <p>{{ statusText }}</p>
        </section>

        <section class="card mb-6 p-5">
            <h2 class="mb-3 text-xl font-extrabold text-ink">Pemakaian paket</h2>
            <ul class="grid gap-3 sm:grid-cols-3">
                <li v-for="row in usageRows" :key="row.label" class="rounded-2xl bg-surface-2 p-4">
                    <p class="text-base text-ink-soft">{{ row.label }}</p>
                    <p class="font-display text-2xl font-extrabold text-ink">
                        {{ row.used }}<span class="text-lg text-ink-soft"> / {{ row.limit === null ? 'tanpa batas' : row.limit }}</span>
                    </p>
                    <p v-if="row.own" class="text-base text-ink-soft">Memakai nomor WhatsApp sendiri</p>
                </li>
            </ul>
        </section>

        <h2 class="mb-3 text-xl font-extrabold text-ink">Pilih paket</h2>
        <div class="mb-6 grid gap-4 md:grid-cols-3">
            <button
                v-for="plan in catalog.plans"
                :key="plan.key"
                type="button"
                class="card pressable flex flex-col gap-3 p-5 text-left ring-2"
                :class="form.plan === plan.key ? 'ring-primary' : 'ring-transparent'"
                :aria-pressed="form.plan === plan.key"
                @click="form.plan = plan.key"
            >
                <span class="flex items-center justify-between gap-2">
                    <span class="font-display text-2xl font-extrabold text-ink">{{ plan.label }}</span>
                    <span v-if="subscription.plan === plan.key" class="rounded-full bg-accent-soft px-3 py-1 text-sm font-bold text-accent-ink">Paket Anda</span>
                </span>
                <span class="text-base text-ink-soft">{{ plan.tagline }}</span>
                <span class="font-display text-3xl font-extrabold text-ink">{{ formatRupiah(plan.price) }}<span class="text-lg font-bold text-ink-soft">/bulan</span></span>
                <ul class="flex flex-col gap-2">
                    <li v-for="h in plan.highlights" :key="h" class="flex items-start gap-2 text-base text-ink">
                        <Check :size="20" class="mt-0.5 shrink-0 text-primary-ink" aria-hidden="true" />{{ h }}
                    </li>
                </ul>
            </button>
        </div>

        <section v-if="canPay && !subscription.unlimited" class="card mb-6 flex flex-col gap-5 p-5">
            <div>
                <h2 class="mb-3 text-xl font-extrabold text-ink">Lama langganan</h2>
                <SegmentedControl v-model="form.months" label="Lama langganan" :options="durationOptions" />
            </div>

            <template v-if="onlineAvailable">
                <div>
                    <h2 class="mb-3 text-xl font-extrabold text-ink">Cara bayar</h2>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <button
                            v-for="o in options"
                            :key="o.key"
                            type="button"
                            class="pressable flex items-start gap-3 rounded-2xl border-2 p-4 text-left"
                            :class="form.channel === o.key ? 'border-primary bg-primary-soft' : 'border-line'"
                            :aria-pressed="form.channel === o.key"
                            @click="form.channel = o.key"
                        >
                            <component :is="o.key === 'qris' ? QrCode : CreditCard" :size="28" class="shrink-0 text-ink" aria-hidden="true" />
                            <span>
                                <span class="block text-lg font-bold text-ink">{{ o.label }}</span>
                                <span class="block text-base text-ink-soft">Biaya layanan {{ formatRupiah(o.fee) }}</span>
                            </span>
                        </button>
                    </div>
                </div>

                <dl v-if="selectedOption" class="rounded-2xl bg-surface-2 p-4 text-lg">
                    <div class="flex justify-between gap-3"><dt class="text-ink-soft">Paket {{ selectedPlan.label }} {{ form.months }} bulan</dt><dd class="font-bold text-ink">{{ formatRupiah(selectedPlan.prices[form.months]) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-soft">Biaya layanan pembayaran</dt><dd class="font-bold text-ink">{{ formatRupiah(selectedOption.fee) }}</dd></div>
                    <div class="mt-2 flex justify-between gap-3 border-t border-line pt-2"><dt class="font-extrabold text-ink">Total bayar</dt><dd class="font-display text-2xl font-extrabold text-ink">{{ formatRupiah(selectedOption.total) }}</dd></div>
                </dl>
                <p v-if="form.errors.plan" class="text-lg font-bold text-danger-ink">{{ form.errors.plan }}</p>
                <BigButton block size="large" :loading="form.processing" @click="pay">Bayar {{ selectedOption ? formatRupiah(selectedOption.total) : '' }}</BigButton>
                <p class="text-base text-ink-soft">Biaya layanan dikenakan oleh penyedia pembayaran. Setelah bayar, paket langsung aktif otomatis.</p>
            </template>

            <template v-else>
                <p class="rounded-2xl bg-surface-2 p-4 text-lg text-ink">Bayar online sedang disiapkan. Untuk sekarang, hubungi admin lewat WhatsApp untuk berlangganan.</p>
                <BigButton :href="adminUrl" external variant="secondary" block><MessageCircle :size="22" aria-hidden="true" /> Hubungi Admin</BigButton>
            </template>
        </section>

        <p v-else-if="!canPay" class="card mb-6 p-5 text-lg text-ink">Hanya pemilik usaha yang bisa membayar langganan.</p>

        <section v-if="referral" class="card mb-6 flex flex-col gap-4 p-5">
            <div class="flex items-start gap-3">
                <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-accent-soft text-accent-ink"><Gift :size="26" aria-hidden="true" /></span>
                <span>
                    <span class="block text-xl font-extrabold text-ink">Ajak teman, dapat gratis {{ referral.reward_days }} hari</span>
                    <span class="block text-lg text-ink-soft">Setiap usaha yang daftar lewat link Anda lalu berlangganan, langganan Anda bertambah {{ referral.reward_days }} hari. Teman Anda dapat bonus coba gratis {{ referral.bonus_days }} hari.</span>
                </span>
            </div>
            <p class="rounded-2xl bg-surface-2 p-4 text-lg break-all text-ink">{{ referral.url }}</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <BigButton variant="secondary" @click="copyReferral"><Copy :size="22" aria-hidden="true" /> {{ referralCopied ? 'Tersalin' : 'Salin Link' }}</BigButton>
                <BigButton :href="referralWa" external><MessageCircle :size="22" aria-hidden="true" /> Bagikan ke WhatsApp</BigButton>
            </div>
            <p class="text-lg text-ink">Kode: <b>{{ referral.code }}</b> · {{ referral.joined }} usaha mendaftar · {{ referral.rewarded }} sudah berlangganan · bonus {{ referral.days }} hari</p>
        </section>

        <section v-if="payments.length">
            <h2 class="mb-3 text-xl font-extrabold text-ink">Riwayat pembayaran</h2>
            <ul class="card divide-y divide-line">
                <li v-for="p in payments" :key="p.reference" class="flex flex-wrap items-center gap-3 px-4 py-3">
                    <CalendarClock :size="22" class="shrink-0 text-ink-soft" aria-hidden="true" />
                    <span class="min-w-0 flex-1">
                        <span class="block text-lg font-bold text-ink">{{ p.plan }} {{ p.months }} bulan · {{ formatRupiah(p.amount) }}</span>
                        <span class="block text-base text-ink-soft">{{ p.created_at }}<template v-if="p.period_until"> · aktif sampai {{ p.period_until }}</template></span>
                    </span>
                    <span class="rounded-full px-3 py-1 text-base font-bold" :class="p.status === 'paid' ? 'bg-primary-soft text-primary-ink' : p.status === 'pending' ? 'bg-accent-soft text-accent-ink' : 'bg-surface-2 text-ink-soft'">{{ p.status_label }}</span>
                    <a v-if="p.redirect_url" :href="p.redirect_url" class="pressable rounded-2xl bg-primary px-4 py-2 text-base font-bold text-on-primary">Lanjut bayar</a>
                </li>
            </ul>
        </section>
    </div>
</template>
