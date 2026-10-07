<script setup>
/** Pengeluaran per bulan: catat cepat (jumlah → jenis → simpan), ringkasan per jenis, hapus kalau salah. */
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Plus, Trash2, Wallet } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import BigInput from '@/Components/ui/BigInput.vue';
import BottomSheet from '@/Components/ui/BottomSheet.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import MoneyDisplay from '@/Components/ui/MoneyDisplay.vue';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import ToggleSwitch from '@/Components/ui/ToggleSwitch.vue';
import { formatRupiah } from '@/composables/useRupiah';

defineOptions({ layout: AppLayout });

const props = defineProps({
    expenses: { type: Array, required: true },
    byCategory: { type: Array, required: true },
    total: { type: Number, required: true },
    month: { type: String, required: true },
    monthLabel: { type: String, required: true },
    categories: { type: Array, required: true },
    paymentMethods: { type: Array, required: true },
    drawerOpen: { type: Boolean, default: false },
    today: { type: String, required: true },
});

const adding = ref(false);
const removing = ref(null);
const newCategory = ref(false);
const form = useForm({ amount: null, expense_category_id: null, new_category: '', spent_on: props.today, payment_method_id: props.paymentMethods[0]?.id ?? null, from_cash_drawer: false, note: '' });

const maxCategory = computed(() => Math.max(1, ...props.byCategory.map((c) => c.total)));

function openAdd() {
    form.reset();
    form.spent_on = props.today;
    form.from_cash_drawer = props.drawerOpen;
    newCategory.value = false;
    adding.value = true;
}

function shiftMonth(delta) {
    const [y, m] = props.month.split('-').map(Number);
    const d = new Date(y, m - 1 + delta, 1);
    router.get(route('expenses.index'), { bulan: `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}` }, { preserveState: true, replace: true });
}

function save() {
    form.post(route('expenses.store'), { preserveScroll: true, onSuccess: () => (adding.value = false) });
}
</script>

<template>
    <Head title="Pengeluaran" />
    <PageHeader title="Pengeluaran" subtitle="Dipakai untuk menghitung untung bersih di Laporan.">
        <template #action>
            <BigButton @click="openAdd"><Plus :size="24" aria-hidden="true" /> Catat Pengeluaran</BigButton>
        </template>
    </PageHeader>

    <div class="mb-5 flex items-center gap-2">
        <button type="button" class="pressable flex size-touch shrink-0 items-center justify-center rounded-2xl border-2 border-line" aria-label="Bulan sebelumnya" @click="shiftMonth(-1)"><ChevronLeft :size="26" /></button>
        <p class="flex-1 text-center text-xl font-extrabold text-ink">{{ monthLabel }}</p>
        <button type="button" class="pressable flex size-touch shrink-0 items-center justify-center rounded-2xl border-2 border-line" aria-label="Bulan berikutnya" @click="shiftMonth(1)"><ChevronRight :size="26" /></button>
    </div>

    <div class="mb-5 grid gap-4 lg:grid-cols-[1fr_2fr]">
        <div class="card flex flex-col gap-4 p-5">
            <MoneyDisplay :amount="total" size="xl" tone="negative" label="Total pengeluaran" />
            <ul v-if="byCategory.length" class="flex flex-col gap-3">
                <li v-for="c in byCategory" :key="c.name">
                    <div class="flex justify-between gap-3 text-lg"><span class="text-ink">{{ c.name }}</span><span class="font-bold tabular-nums">{{ formatRupiah(c.total) }}</span></div>
                    <div class="mt-1 h-2 overflow-hidden rounded-full bg-surface-2" aria-hidden="true"><div class="h-full rounded-full bg-danger/70" :style="{ width: `${(c.total / maxCategory) * 100}%` }" /></div>
                </li>
            </ul>
        </div>

        <div>
            <EmptyState v-if="!expenses.length" title="Belum ada pengeluaran bulan ini" message="Catat listrik, gaji, sewa, atau belanja kecil supaya untung bersih terhitung benar.">
                <template #icon><Wallet :size="48" aria-hidden="true" /></template>
            </EmptyState>
            <ul v-else class="card divide-y divide-line overflow-hidden">
                <li v-for="e in expenses" :key="e.id" class="flex items-center gap-3 px-4 py-3">
                    <span class="min-w-0 flex-1">
                        <span class="block text-lg font-bold text-ink">{{ e.category }}</span>
                        <span class="block text-base text-ink-soft">{{ e.date }}<template v-if="e.method"> · {{ e.method }}</template><template v-if="e.note"> · {{ e.note }}</template></span>
                    </span>
                    <span class="font-display text-lg font-extrabold text-ink tabular-nums">{{ formatRupiah(e.amount) }}</span>
                    <button type="button" class="pressable flex size-touch shrink-0 items-center justify-center rounded-xl text-ink-soft hover:text-danger-ink" :aria-label="`Hapus pengeluaran ${e.category} ${formatRupiah(e.amount)}`" @click="removing = e">
                        <Trash2 :size="22" />
                    </button>
                </li>
            </ul>
        </div>
    </div>

    <BottomSheet v-model:open="adding" title="Catat Pengeluaran">
        <form class="flex flex-col gap-4" @submit.prevent="save">
            <MoneyInput v-model="form.amount" label="Jumlah uang" :error="form.errors.amount" />
            <fieldset>
                <legend class="mb-2 text-lg font-bold text-ink">Untuk apa</legend>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="c in categories"
                        :key="c.id"
                        type="button"
                        class="pressable min-h-12 rounded-full border-2 px-4 text-base font-bold"
                        :class="form.expense_category_id === c.id && !newCategory ? 'border-primary bg-primary-soft text-primary-ink' : 'border-line text-ink'"
                        @click="form.expense_category_id = c.id; newCategory = false; form.new_category = ''"
                    >
                        {{ c.name }}
                    </button>
                    <button type="button" class="pressable min-h-12 rounded-full border-2 px-4 text-base font-bold" :class="newCategory ? 'border-primary bg-primary-soft text-primary-ink' : 'border-line text-ink'" @click="newCategory = true; form.expense_category_id = null">
                        <Plus :size="16" class="inline" aria-hidden="true" /> Jenis lain
                    </button>
                </div>
                <BigInput v-if="newCategory" v-model="form.new_category" label="Nama jenis baru" placeholder="Contoh: Servis kulkas" class="mt-3" :error="form.errors.new_category" />
            </fieldset>
            <BigInput v-model="form.spent_on" label="Tanggal" type="date" :error="form.errors.spent_on" />
            <div class="flex items-center justify-between gap-3 rounded-2xl bg-surface-2 p-4">
                <span>
                    <span class="block text-lg font-bold text-ink">Ambil dari laci kasir</span>
                    <span class="block text-base text-ink-soft">{{ drawerOpen ? 'Tercatat sebagai uang keluar di kasir yang sedang buka.' : 'Kasir sedang tutup.' }}</span>
                </span>
                <ToggleSwitch v-model="form.from_cash_drawer" label="Ambil dari laci kasir" :disabled="!drawerOpen" />
            </div>
            <p v-if="form.errors.from_cash_drawer" class="text-base font-bold text-danger-ink">{{ form.errors.from_cash_drawer }}</p>
            <label v-if="!form.from_cash_drawer" class="block">
                <span class="mb-2 block text-lg font-bold text-ink">Dibayar dengan</span>
                <select v-model="form.payment_method_id" class="min-h-touch w-full rounded-2xl border-2 border-line bg-surface px-4 text-lg text-ink">
                    <option v-for="m in paymentMethods" :key="m.id" :value="m.id">{{ m.name }}</option>
                </select>
            </label>
            <BigInput v-model="form.note" label="Catatan" optional placeholder="Contoh: token listrik" :error="form.errors.note" />
            <BigButton type="submit" block size="large" :loading="form.processing">Simpan</BigButton>
        </form>
    </BottomSheet>

    <ConfirmDialog
        :open="!!removing"
        :title="`Hapus pengeluaran ${removing ? formatRupiah(removing.amount) : ''}?`"
        message="Kalau uangnya diambil dari laci kasir yang masih buka, catatan uang keluarnya ikut dihapus."
        confirm-text="Ya, Hapus"
        danger
        @update:open="(v) => !v && (removing = null)"
        @confirm="router.delete(route('expenses.destroy', removing.id), { preserveScroll: true, onFinish: () => (removing = null) })"
    />
</template>
