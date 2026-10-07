<script setup>
/** Komisi karyawan per periode + aturan komisi (persen / nominal per layanan). */
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { HandCoins, Plus, Trash2 } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import BigInput from '@/Components/ui/BigInput.vue';
import BottomSheet from '@/Components/ui/BottomSheet.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import { useAuth } from '@/composables/useAuth';
import { formatRupiah } from '@/composables/useRupiah';

defineOptions({ layout: AppLayout });

const props = defineProps({
    summary: { type: Array, required: true },
    range: { type: Object, required: true },
    rules: { type: Array, required: true },
    staff: { type: Array, required: true },
    services: { type: Array, required: true },
    categories: { type: Array, required: true },
});

const { can } = useAuth();
const from = ref(props.range.dari);
const to = ref(props.range.sampai);
const addingRule = ref(false);
const paying = ref(null);
const form = useForm({ user_id: null, product_id: null, category_id: null, type: 'percent', value: '' });

function applyRange() {
    router.get(route('commissions.index'), { dari: from.value, sampai: to.value }, { preserveState: true, replace: true });
}

function saveRule() {
    form.post(route('commissions.rules.store'), { preserveScroll: true, onSuccess: () => { form.reset(); addingRule.value = false; } });
}

function pay() {
    router.post(route('commissions.pay', paying.value.user_id), props.range, { preserveScroll: true, onFinish: () => (paying.value = null) });
}

const ruleValue = (r) => (r.type === 'percent' ? `${r.value / 100}%` : formatRupiah(r.value));
</script>

<template>
    <Head title="Komisi Karyawan" />
    <PageHeader title="Komisi Karyawan" subtitle="Dihitung otomatis dari layanan yang dikerjakan." />

    <div class="card mb-5 grid gap-3 p-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
        <BigInput v-model="from" label="Dari tanggal" type="date" />
        <BigInput v-model="to" label="Sampai tanggal" type="date" />
        <BigButton @click="applyRange">Tampilkan</BigButton>
    </div>

    <EmptyState v-if="!summary.length" title="Belum ada komisi di periode ini" message="Komisi muncul saat kasir memilih karyawan yang mengerjakan layanan.">
        <template #icon><HandCoins :size="48" aria-hidden="true" /></template>
    </EmptyState>

    <ul class="grid gap-3 md:grid-cols-2">
        <li v-for="s in summary" :key="s.user_id" class="card flex flex-col gap-3 p-4">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="truncate text-xl font-extrabold text-ink">{{ s.name }}</p>
                    <p class="text-base text-ink-soft">{{ s.jobs }} pekerjaan</p>
                </div>
                <p class="font-display text-2xl font-extrabold text-ink tabular-nums">{{ formatRupiah(s.total) }}</p>
            </div>
            <div class="flex items-center justify-between gap-3">
                <p class="text-base" :class="s.unpaid ? 'font-bold text-warn-ink' : 'text-primary-ink'">{{ s.unpaid ? `Belum dibayar ${formatRupiah(s.unpaid)}` : 'Sudah dibayar semua' }}</p>
                <BigButton v-if="s.unpaid && can('manage_business')" variant="soft" @click="paying = s">Tandai Dibayar</BigButton>
            </div>
        </li>
    </ul>

    <section class="mt-8">
        <div class="mb-3 flex items-center justify-between gap-3">
            <h2 class="text-xl font-extrabold text-ink">Aturan komisi</h2>
            <BigButton v-if="can('manage_business')" variant="soft" @click="addingRule = true"><Plus :size="22" aria-hidden="true" /> Aturan Baru</BigButton>
        </div>
        <p class="mb-3 text-base text-ink-soft">Aturan paling khusus yang dipakai: karyawan + layanan → layanan → kategori → semua.</p>
        <ul v-if="rules.length" class="card divide-y divide-line">
            <li v-for="r in rules" :key="r.id" class="flex min-h-touch items-center justify-between gap-3 px-4 py-3">
                <span class="min-w-0 text-lg text-ink">
                    <strong>{{ r.product || (r.category ? `Kategori ${r.category}` : 'Semua layanan') }}</strong>
                    <span class="text-ink-soft"> · {{ r.user || 'semua karyawan' }}</span>
                </span>
                <span class="flex shrink-0 items-center gap-2">
                    <span class="font-display text-lg font-extrabold text-ink">{{ ruleValue(r) }}</span>
                    <button v-if="can('manage_business')" type="button" class="pressable flex size-touch items-center justify-center rounded-xl text-danger-ink" :aria-label="`Hapus aturan ${r.product || 'ini'}`" @click="router.delete(route('commissions.rules.destroy', r.id), { preserveScroll: true })">
                        <Trash2 :size="22" />
                    </button>
                </span>
            </li>
        </ul>
        <p v-else class="text-lg text-ink-soft">Belum ada aturan. Tanpa aturan, komisi tidak dihitung.</p>
    </section>

    <BottomSheet v-model:open="addingRule" title="Aturan Komisi Baru">
        <form class="flex flex-col gap-4" @submit.prevent="saveRule">
            <SegmentedControl v-model="form.type" label="Jenis komisi" :options="[{ value: 'percent', label: 'Persen (%)' }, { value: 'fixed', label: 'Nominal (Rp)' }]" />
            <BigInput v-model="form.value" :label="form.type === 'percent' ? 'Besar komisi (%)' : 'Besar komisi (Rp)'" type="number" inputmode="decimal" :placeholder="form.type === 'percent' ? 'Contoh: 30' : 'Contoh: 10000'" :error="form.errors.value" />
            <label class="block">
                <span class="mb-2 block text-lg font-bold text-ink">Untuk layanan</span>
                <select v-model="form.product_id" class="min-h-touch w-full rounded-2xl border-2 border-line bg-surface px-4 text-lg text-ink">
                    <option :value="null">Semua layanan</option>
                    <option v-for="s in services" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
            </label>
            <label v-if="!form.product_id && categories.length" class="block">
                <span class="mb-2 block text-lg font-bold text-ink">Atau kategori</span>
                <select v-model="form.category_id" class="min-h-touch w-full rounded-2xl border-2 border-line bg-surface px-4 text-lg text-ink">
                    <option :value="null">Semua kategori</option>
                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
            </label>
            <label class="block">
                <span class="mb-2 block text-lg font-bold text-ink">Untuk karyawan</span>
                <select v-model="form.user_id" class="min-h-touch w-full rounded-2xl border-2 border-line bg-surface px-4 text-lg text-ink">
                    <option :value="null">Semua karyawan</option>
                    <option v-for="s in staff" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
            </label>
            <BigButton type="submit" block size="large" :loading="form.processing">Simpan Aturan</BigButton>
        </form>
    </BottomSheet>

    <ConfirmDialog
        :open="!!paying"
        :title="`Komisi ${paying?.name ?? ''} sudah dibayar?`"
        :message="paying ? `${formatRupiah(paying.unpaid)} untuk periode ${range.dari} s/d ${range.sampai} akan ditandai sudah dibayar.` : ''"
        confirm-text="Ya, Sudah Dibayar"
        @update:open="(v) => !v && (paying = null)"
        @confirm="pay"
    />
</template>
