<script setup>
/**
 * Status pesanan (laundry, jahit, servis): kolom per tahap di laptop, tab per tahap di HP.
 * "Lanjut" memindahkan ke tahap berikutnya; tahap yang ditandai "kabari pelanggan" mengirim WhatsApp.
 */
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { AlertTriangle, ArrowRight, PackageCheck, Search } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import BottomSheet from '@/Components/ui/BottomSheet.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { formatRupiah } from '@/composables/useRupiah';

defineOptions({ layout: AppLayout });

const props = defineProps({
    statuses: { type: Array, required: true },
    orders: { type: Array, required: true },
    paymentMethods: { type: Array, required: true },
    q: { type: String, default: '' },
});

const search = ref(props.q);
const tab = ref(props.statuses[0]?.id);
const pickup = ref(null);
const methodId = ref(props.paymentMethods[0]?.id ?? null);

const byStatus = computed(() => Object.fromEntries(props.statuses.map((s) => [s.id, props.orders.filter((o) => o.status_id === s.id)])));
const nextOf = (statusId) => {
    const i = props.statuses.findIndex((s) => s.id === statusId);
    return props.statuses[i + 1] ?? null;
};
const isLastBeforeFinal = (statusId) => nextOf(statusId)?.is_final;

let timer;
function onSearch() {
    clearTimeout(timer);
    timer = setTimeout(() => router.get(route('orders.index'), { q: search.value || undefined }, { preserveState: true, replace: true }), 300);
}

function move(order) {
    router.post(route('orders.move', order.uuid), {}, { preserveScroll: true });
}

function confirmPickup() {
    router.post(route('orders.pickup', pickup.value.uuid), { payment_method_id: methodId.value }, { preserveScroll: true, onSuccess: () => (pickup.value = null) });
}
</script>

<template>
    <Head title="Status Pesanan" />
    <PageHeader title="Status Pesanan" :subtitle="`${orders.filter((o) => !statuses.find((s) => s.id === o.status_id)?.is_final).length} pesanan dalam proses`" />

    <label class="mb-5 flex min-h-touch items-center gap-3 rounded-2xl border-2 border-line bg-surface px-4 focus-within:border-focus">
        <Search :size="24" class="shrink-0 text-ink-soft" aria-hidden="true" />
        <span class="sr-only">Cari pesanan</span>
        <input v-model="search" type="search" placeholder="Cari no nota atau nama pelanggan" class="min-w-0 flex-1 bg-transparent text-lg text-ink focus:outline-none" @input="onSearch" />
    </label>

    <!-- HP: tab per tahap -->
    <div class="-mx-4 mb-4 flex gap-2 overflow-x-auto px-4 pb-1 lg:hidden" role="tablist">
        <button
            v-for="s in statuses"
            :key="s.id"
            type="button"
            role="tab"
            :aria-selected="tab === s.id"
            class="pressable min-h-touch shrink-0 rounded-2xl px-4 text-lg font-bold"
            :class="tab === s.id ? 'bg-primary text-on-primary' : 'bg-surface-2 text-ink'"
            @click="tab = s.id"
        >
            {{ s.name }} ({{ byStatus[s.id].length }})
        </button>
    </div>

    <div class="grid gap-4 lg:auto-cols-[minmax(16rem,1fr)] lg:grid-flow-col lg:overflow-x-auto lg:pb-2">
        <section v-for="s in statuses" :key="s.id" class="min-w-0" :class="tab === s.id ? '' : 'hidden lg:block'">
            <h2 class="mb-3 hidden items-center gap-2 text-lg font-extrabold text-ink lg:flex">
                <span class="size-3 rounded-full" :style="{ background: s.color || '#9ca3af' }" aria-hidden="true" />
                {{ s.name }} <span class="text-ink-soft">({{ byStatus[s.id].length }})</span>
            </h2>
            <EmptyState v-if="!byStatus[s.id].length" title="Kosong" />
            <ul class="flex flex-col gap-3">
                <li v-for="o in byStatus[s.id]" :key="o.uuid" class="card flex flex-col gap-2 p-4" :class="o.late ? 'ring-2 ring-danger' : ''">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="truncate text-lg font-extrabold text-ink">{{ o.customer || 'Tanpa nama' }}</p>
                            <p class="text-base text-ink-soft">{{ o.number }}</p>
                        </div>
                        <span v-if="o.due_amount > 0" class="shrink-0 rounded-full bg-accent-soft px-2 py-0.5 text-sm font-bold text-accent-ink">Sisa {{ formatRupiah(o.due_amount) }}</span>
                    </div>
                    <p class="text-base text-ink">{{ o.items }}</p>
                    <p v-if="o.ready_at" class="flex items-center gap-1 text-base" :class="o.late ? 'font-bold text-danger-ink' : 'text-ink-soft'">
                        <AlertTriangle v-if="o.late" :size="18" aria-hidden="true" /> Selesai: {{ o.ready_at }}
                    </p>
                    <template v-if="!s.is_final">
                        <BigButton v-if="isLastBeforeFinal(s.id)" @click="pickup = o"><PackageCheck :size="22" aria-hidden="true" /> Diambil Pelanggan</BigButton>
                        <BigButton v-else-if="nextOf(s.id)" variant="soft" @click="move(o)">Lanjut: {{ nextOf(s.id).name }} <ArrowRight :size="20" aria-hidden="true" /></BigButton>
                    </template>
                </li>
            </ul>
        </section>
    </div>

    <BottomSheet :open="!!pickup" title="Pesanan Diambil" @update:open="(v) => !v && (pickup = null)">
        <div v-if="pickup" class="flex flex-col gap-4">
            <p class="text-lg text-ink">{{ pickup.customer }} · {{ pickup.number }}</p>
            <template v-if="pickup.due_amount > 0">
                <p class="rounded-2xl bg-accent-soft p-4 text-xl font-extrabold text-accent-ink">Sisa bayar: {{ formatRupiah(pickup.due_amount) }}</p>
                <label class="block">
                    <span class="mb-2 block text-lg font-bold text-ink">Dibayar dengan</span>
                    <select v-model="methodId" class="min-h-touch w-full rounded-2xl border-2 border-line bg-surface px-4 text-lg text-ink">
                        <option v-for="m in paymentMethods" :key="m.id" :value="m.id">{{ m.name }}</option>
                    </select>
                </label>
            </template>
            <p v-else class="text-lg text-ink-soft">Sudah lunas.</p>
            <BigButton block size="large" @click="confirmPickup">{{ pickup.due_amount > 0 ? 'Terima Bayar & Serahkan' : 'Serahkan ke Pelanggan' }}</BigButton>
        </div>
    </BottomSheet>
</template>
