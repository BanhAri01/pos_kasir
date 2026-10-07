<script setup>
/** Buku kasbon: siapa saja yang masih berutang, urut dari yang terbesar. */
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { AlertTriangle, ChevronRight, NotebookPen, Plus, Search } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import BigInput from '@/Components/ui/BigInput.vue';
import BottomSheet from '@/Components/ui/BottomSheet.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import MoneyDisplay from '@/Components/ui/MoneyDisplay.vue';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { formatRupiah } from '@/composables/useRupiah';

defineOptions({ layout: AppLayout });

const props = defineProps({
    customers: { type: Array, required: true },
    total: { type: Number, required: true },
    q: { type: String, default: '' },
    allCustomers: { type: Array, required: true },
});

const search = ref(props.q);
const adding = ref(false);
const form = useForm({ customer_id: null, amount: null, note: '', due_date: '' });

let timer;
function onSearch() {
    clearTimeout(timer);
    timer = setTimeout(() => router.get(route('receivables.index'), { q: search.value || undefined }, { preserveState: true, replace: true }), 300);
}

function save() {
    form.post(route('receivables.store'), { onSuccess: () => (adding.value = false) });
}
</script>

<template>
    <Head title="Kasbon" />
    <PageHeader title="Kasbon / Utang Pelanggan" help="kasbon">
        <template #action>
            <BigButton variant="soft" @click="adding = true"><Plus :size="22" aria-hidden="true" /> Catat Utang</BigButton>
        </template>
    </PageHeader>

    <div class="card mb-5 p-5">
        <MoneyDisplay :amount="total" size="lg" tone="negative" label="Total utang pelanggan" />
        <p class="mt-1 text-base text-ink-soft">{{ customers.length }} pelanggan masih punya utang</p>
    </div>

    <label class="mb-5 flex min-h-touch items-center gap-3 rounded-2xl border-2 border-line bg-surface px-4 focus-within:border-focus">
        <Search :size="24" class="shrink-0 text-ink-soft" aria-hidden="true" />
        <span class="sr-only">Cari pelanggan</span>
        <input v-model="search" type="search" placeholder="Cari nama pelanggan" class="min-w-0 flex-1 bg-transparent text-lg text-ink focus:outline-none" @input="onSearch" />
    </label>

    <EmptyState v-if="!customers.length" title="Tidak ada utang" message="Semua pelanggan sudah lunas. Utang muncul di sini saat kasir memilih “Bayar Nanti / Kasbon”.">
        <template #icon><NotebookPen :size="48" aria-hidden="true" /></template>
    </EmptyState>

    <ul v-else class="card divide-y divide-line overflow-hidden">
        <li v-for="c in customers" :key="c.id">
            <Link :href="route('receivables.show', c.id)" class="flex min-h-touch-lg items-center gap-3 px-4 py-3 hover:bg-surface-2">
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-lg font-extrabold text-ink">{{ c.name }}</span>
                    <span class="flex flex-wrap items-center gap-x-2 text-base text-ink-soft">
                        <span v-if="c.phone">{{ c.phone }}</span>
                        <span v-if="c.overdue" class="inline-flex items-center gap-1 font-bold text-danger-ink"><AlertTriangle :size="16" aria-hidden="true" /> Lewat jatuh tempo</span>
                        <span v-if="c.credit_limit && c.balance > c.credit_limit" class="font-bold text-danger-ink">Melebihi batas</span>
                    </span>
                </span>
                <span class="font-display text-xl font-extrabold text-warn-ink tabular-nums">{{ formatRupiah(c.balance) }}</span>
                <ChevronRight :size="24" class="shrink-0 text-ink-soft" aria-hidden="true" />
            </Link>
        </li>
    </ul>

    <BottomSheet v-model:open="adding" title="Catat Utang Manual">
        <form class="flex flex-col gap-4" @submit.prevent="save">
            <p class="text-base text-ink-soft">Untuk utang di luar kasir, mis. pinjam uang atau utang lama dari buku catatan.</p>
            <label class="block">
                <span class="mb-2 block text-lg font-bold text-ink">Pelanggan</span>
                <select v-model="form.customer_id" class="min-h-touch w-full rounded-2xl border-2 border-line bg-surface px-4 text-lg text-ink">
                    <option :value="null" disabled>Pilih pelanggan…</option>
                    <option v-for="c in allCustomers" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
                <span v-if="form.errors.customer_id" class="mt-2 block text-base font-bold text-danger-ink">{{ form.errors.customer_id }}</span>
            </label>
            <MoneyInput v-model="form.amount" label="Jumlah utang" :error="form.errors.amount" />
            <BigInput v-model="form.note" label="Keterangan" optional placeholder="Contoh: utang lama bulan lalu" :error="form.errors.note" />
            <BigInput v-model="form.due_date" label="Jatuh tempo" type="date" optional :error="form.errors.due_date" />
            <BigButton type="submit" block size="large" :loading="form.processing">Simpan</BigButton>
        </form>
    </BottomSheet>
</template>
