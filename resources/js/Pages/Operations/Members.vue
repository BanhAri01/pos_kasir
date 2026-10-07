<script setup>
/** Member gym / paket: cari member, absen masuk satu ketukan, buat paket baru. */
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { CheckCircle2, IdCard, Plus, Search } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import BigInput from '@/Components/ui/BigInput.vue';
import BottomSheet from '@/Components/ui/BottomSheet.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { useAuth } from '@/composables/useAuth';
import { formatRupiah } from '@/composables/useRupiah';

defineOptions({ layout: AppLayout });

const props = defineProps({
    members: { type: Array, required: true },
    plans: { type: Array, required: true },
    todayCheckins: { type: Number, required: true },
    q: { type: String, default: '' },
});

const { can } = useAuth();
const search = ref(props.q);
const addingPlan = ref(false);
const planForm = useForm({ name: '', price: null, kind: 'gym', duration_value: 1, duration_unit: 'month', session_quota: null });

const UNITS = { day: 'hari', week: 'minggu', month: 'bulan', year: 'tahun' };
const KINDS = { gym: 'Member gym', pt_session: 'Sesi personal trainer', package: 'Paket layanan' };

let timer;
function onSearch() {
    clearTimeout(timer);
    timer = setTimeout(() => router.get(route('members.index'), { q: search.value || undefined }, { preserveState: true, replace: true }), 300);
}

function checkin(member, ptSession = false) {
    router.post(route('members.checkin'), { customer_id: member.id, pt_session: ptSession }, { preserveScroll: true });
}

function savePlan() {
    planForm.post(route('members.plans.store'), { preserveScroll: true, onSuccess: () => { planForm.reset(); addingPlan.value = false; } });
}
</script>

<template>
    <Head title="Member" />
    <PageHeader title="Member" :subtitle="`${todayCheckins} orang sudah absen hari ini`" />

    <label class="mb-5 flex min-h-touch items-center gap-3 rounded-2xl border-2 border-line bg-surface px-4 focus-within:border-focus">
        <Search :size="24" class="shrink-0 text-ink-soft" aria-hidden="true" />
        <span class="sr-only">Cari member</span>
        <input v-model="search" type="search" placeholder="Cari nama, no HP, atau kode member" class="min-w-0 flex-1 bg-transparent text-lg text-ink focus:outline-none" @input="onSearch" />
    </label>

    <EmptyState v-if="!members.length" title="Belum ada member aktif" message="Jual paket member di kasir. Pelanggan otomatis jadi member setelah membayar.">
        <template #icon><IdCard :size="48" aria-hidden="true" /></template>
    </EmptyState>

    <ul class="grid gap-3 md:grid-cols-2">
        <li v-for="m in members" :key="m.id" class="card flex min-w-0 flex-col gap-3 p-4">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="truncate text-xl font-extrabold text-ink">{{ m.name }}</p>
                    <p class="text-base text-ink-soft">{{ m.plan }}<template v-if="m.member_code"> · {{ m.member_code }}</template></p>
                </div>
                <span class="shrink-0 rounded-full px-3 py-1 text-sm font-bold" :class="m.active ? (m.days_left <= 3 ? 'bg-accent-soft text-accent-ink' : 'bg-primary-soft text-primary-ink') : 'bg-danger-soft text-danger-ink'">
                    {{ m.active ? (m.days_left <= 3 ? `Habis ${m.days_left} hari lagi` : 'Aktif') : 'Belum mulai' }}
                </span>
            </div>
            <p class="text-base text-ink-soft">
                Berlaku sampai <strong class="text-ink">{{ m.ends_on }}</strong>
                <template v-if="m.sessions_remaining !== null"> · sisa {{ m.sessions_remaining }} sesi</template>
            </p>
            <div class="grid gap-2" :class="m.sessions_remaining !== null ? 'grid-cols-2' : 'grid-cols-1'">
                <BigButton :disabled="!m.active" @click="checkin(m)"><CheckCircle2 :size="22" aria-hidden="true" /> Absen Masuk</BigButton>
                <BigButton v-if="m.sessions_remaining !== null" variant="soft" :disabled="!m.active || m.sessions_remaining < 1" @click="checkin(m, true)">Pakai 1 Sesi</BigButton>
            </div>
            <button v-if="!m.member_code" type="button" class="self-start text-base font-bold text-primary-ink underline" @click="router.post(route('members.code', m.id), {}, { preserveScroll: true })">
                Buat kode member (untuk kartu)
            </button>
        </li>
    </ul>

    <section class="mt-8">
        <div class="mb-3 flex items-center justify-between gap-3">
            <h2 class="text-xl font-extrabold text-ink">Paket member</h2>
            <BigButton v-if="can('manage_business')" variant="soft" @click="addingPlan = true"><Plus :size="22" aria-hidden="true" /> Paket Baru</BigButton>
        </div>
        <ul v-if="plans.length" class="card divide-y divide-line">
            <li v-for="p in plans" :key="p.id" class="flex min-h-touch flex-wrap items-center justify-between gap-2 px-4 py-3">
                <span class="min-w-0">
                    <span class="block text-lg font-bold text-ink">{{ p.name }}</span>
                    <span class="block text-base text-ink-soft">{{ KINDS[p.kind] }} · {{ p.duration_value }} {{ UNITS[p.duration_unit] }}<template v-if="p.session_quota"> · {{ p.session_quota }} sesi</template></span>
                </span>
                <span class="font-display text-lg font-extrabold text-ink tabular-nums">{{ formatRupiah(p.price) }}</span>
            </li>
        </ul>
        <p v-else class="text-lg text-ink-soft">Belum ada paket. Buat paket supaya bisa dijual di kasir.</p>
    </section>

    <BottomSheet v-model:open="addingPlan" title="Paket Member Baru">
        <form class="flex flex-col gap-4" @submit.prevent="savePlan">
            <BigInput v-model="planForm.name" label="Nama paket" placeholder="Contoh: Member 1 Bulan" :error="planForm.errors.name" />
            <MoneyInput v-model="planForm.price" label="Harga" :error="planForm.errors.price" />
            <label class="block">
                <span class="mb-2 block text-lg font-bold text-ink">Jenis paket</span>
                <select v-model="planForm.kind" class="min-h-touch w-full rounded-2xl border-2 border-line bg-surface px-4 text-lg text-ink">
                    <option v-for="(label, value) in KINDS" :key="value" :value="value">{{ label }}</option>
                </select>
            </label>
            <div class="grid grid-cols-2 gap-3">
                <BigInput v-model="planForm.duration_value" label="Lama berlaku" type="number" inputmode="numeric" :error="planForm.errors.duration_value" />
                <label class="block">
                    <span class="mb-2 block text-lg font-bold text-ink">Satuan</span>
                    <select v-model="planForm.duration_unit" class="min-h-touch w-full rounded-2xl border-2 border-line bg-surface px-4 text-lg text-ink">
                        <option v-for="(label, value) in UNITS" :key="value" :value="value">{{ label }}</option>
                    </select>
                </label>
            </div>
            <BigInput v-if="planForm.kind !== 'gym'" v-model="planForm.session_quota" label="Jumlah sesi" type="number" inputmode="numeric" optional :error="planForm.errors.session_quota" />
            <BigButton type="submit" block size="large" :loading="planForm.processing">Simpan Paket</BigButton>
        </form>
    </BottomSheet>
</template>
