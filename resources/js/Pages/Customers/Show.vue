<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ClipboardList, IdCard, MessageCircle, NotebookPen, Pencil, Trash2, X } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import MoneyDisplay from '@/Components/ui/MoneyDisplay.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { formatRupiah } from '@/composables/useRupiah';

defineOptions({ layout: AppLayout });

const props = defineProps({
    customer: { type: Object, required: true },
    stats: { type: Object, required: true },
    sales: { type: Array, required: true },
    canSeeSales: { type: Boolean, default: false },
    priceLevel: { type: String, default: null },
    balance: { type: Number, default: null },
    creditLimit: { type: Number, default: null },
    membership: { type: Object, default: null },
    serviceNotes: { type: Array, default: null },
    staff: { type: Array, default: () => [] },
});

const confirmDelete = ref(false);
const noteForm = useForm({ note: '', staff_id: null });

function addNote() {
    noteForm.post(route('service-notes.store', props.customer.id), { preserveScroll: true, onSuccess: () => noteForm.reset() });
}
</script>

<template>
    <Head :title="customer.name" />
    <div class="mx-auto max-w-2xl">
        <PageHeader :title="customer.name" :subtitle="customer.phone || 'Tanpa no HP'" :back-href="route('customers.index')" />

        <div class="mb-4 grid grid-cols-2 gap-3">
            <div class="card p-4">
                <p class="text-lg text-ink-soft">Jumlah belanja</p>
                <p class="font-display text-3xl font-extrabold text-ink">{{ stats.count }}x</p>
            </div>
            <div class="card p-4"><MoneyDisplay :amount="stats.total" size="lg" label="Total belanja" /></div>
        </div>

        <div class="card mb-4 flex flex-col gap-2 p-5">
            <p v-if="customer.address" class="text-lg"><span class="text-ink-soft">Alamat:</span> {{ customer.address }}</p>
            <p v-if="customer.notes" class="text-lg"><span class="text-ink-soft">Catatan:</span> {{ customer.notes }}</p>
            <div class="mt-2 grid gap-3 sm:grid-cols-2">
                <BigButton :href="route('customers.edit', customer.id)" variant="secondary"><Pencil :size="22" aria-hidden="true" /> Ubah</BigButton>
                <a
                    v-if="customer.phone_raw"
                    :href="`https://wa.me/${customer.phone_raw}`"
                    target="_blank"
                    rel="noopener"
                    class="pressable flex min-h-touch items-center justify-center gap-2 rounded-2xl bg-primary-soft px-6 text-lg font-bold text-primary-ink"
                >
                    <MessageCircle :size="22" aria-hidden="true" /> Chat WhatsApp
                </a>
            </div>
        </div>

        <div v-if="priceLevel || balance !== null || membership" class="mb-4 grid gap-3 sm:grid-cols-2">
            <Link v-if="balance !== null" :href="route('receivables.show', customer.id)" class="card pressable flex items-center gap-3 p-4">
                <NotebookPen :size="28" class="shrink-0 text-ink-soft" aria-hidden="true" />
                <span>
                    <span class="block text-base text-ink-soft">Utang / kasbon<template v-if="creditLimit"> (batas {{ formatRupiah(creditLimit) }})</template></span>
                    <span class="block font-display text-xl font-extrabold tabular-nums" :class="balance > 0 ? 'text-danger-ink' : 'text-primary-ink'">{{ balance > 0 ? formatRupiah(balance) : 'Tidak ada' }}</span>
                </span>
            </Link>
            <div v-if="membership" class="card flex items-center gap-3 p-4">
                <IdCard :size="28" class="shrink-0 text-ink-soft" aria-hidden="true" />
                <span>
                    <span class="block text-base text-ink-soft">{{ membership.plan }}</span>
                    <span class="block text-lg font-extrabold text-ink">Aktif s/d {{ membership.ends_on }}<template v-if="membership.sessions_remaining !== null"> · {{ membership.sessions_remaining }} sesi</template></span>
                </span>
            </div>
            <div v-if="priceLevel" class="card p-4">
                <span class="block text-base text-ink-soft">Tipe harga</span>
                <span class="block text-lg font-extrabold text-ink">{{ priceLevel }}</span>
            </div>
        </div>

        <section v-if="serviceNotes" class="mb-6">
            <h2 class="mb-3 flex items-center gap-2 text-xl font-extrabold text-ink"><ClipboardList :size="24" aria-hidden="true" /> Catatan layanan</h2>
            <form class="card mb-3 flex flex-col gap-3 p-4" @submit.prevent="addNote">
                <label class="block">
                    <span class="sr-only">Catatan baru</span>
                    <textarea v-model="noteForm.note" rows="2" placeholder="Contoh: potong pendek no. 2, tidak suka pomade" class="w-full rounded-2xl border-2 border-line bg-surface p-3 text-lg text-ink focus:border-focus focus:outline-none" />
                    <span v-if="noteForm.errors.note" class="mt-1 block text-base font-bold text-danger-ink">{{ noteForm.errors.note }}</span>
                </label>
                <div class="grid gap-3 sm:grid-cols-[1fr_auto]">
                    <select v-model="noteForm.staff_id" class="min-h-touch w-full rounded-2xl border-2 border-line bg-surface px-4 text-lg text-ink" aria-label="Dikerjakan oleh">
                        <option :value="null">Dikerjakan oleh… (boleh kosong)</option>
                        <option v-for="s in staff" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                    <BigButton type="submit" :loading="noteForm.processing">Simpan Catatan</BigButton>
                </div>
            </form>
            <ul v-if="serviceNotes.length" class="card divide-y divide-line">
                <li v-for="n in serviceNotes" :key="n.id" class="flex items-start gap-3 px-4 py-3">
                    <span class="min-w-0 flex-1">
                        <span class="block text-lg whitespace-pre-line text-ink">{{ n.note }}</span>
                        <span class="block text-base text-ink-soft">{{ n.date }}<template v-if="n.staff"> · {{ n.staff }}</template></span>
                    </span>
                    <button type="button" class="pressable flex size-touch shrink-0 items-center justify-center rounded-xl text-ink-soft" aria-label="Hapus catatan" @click="router.delete(route('service-notes.destroy', n.id), { preserveScroll: true })">
                        <X :size="22" />
                    </button>
                </li>
            </ul>
        </section>

        <h2 class="mb-3 text-xl font-extrabold text-ink">Riwayat belanja</h2>
        <ul v-if="sales.length" class="card divide-y divide-line overflow-hidden">
            <li v-for="s in sales" :key="s.uuid">
                <component
                    :is="canSeeSales ? Link : 'div'"
                    :href="canSeeSales ? route('sales.show', s.uuid) : undefined"
                    class="flex min-h-touch items-center justify-between gap-3 px-4 py-3"
                    :class="canSeeSales ? 'hover:bg-surface-2' : ''"
                >
                    <span>
                        <span class="block text-lg font-bold text-ink">{{ s.date }}</span>
                        <span class="block text-base text-ink-soft">{{ s.number }}<template v-if="s.status === 'void'"> · dibatalkan</template></span>
                    </span>
                    <span class="font-display text-lg font-extrabold tabular-nums" :class="s.status === 'void' ? 'line-through opacity-50' : 'text-ink'">{{ formatRupiah(s.total) }}</span>
                </component>
            </li>
        </ul>
        <p v-else class="text-lg text-ink-soft">Belum ada riwayat belanja.</p>

        <BigButton variant="ghost" block class="mt-6 text-danger-ink" @click="confirmDelete = true"><Trash2 :size="22" aria-hidden="true" /> Hapus pelanggan ini</BigButton>
    </div>

    <ConfirmDialog
        v-model:open="confirmDelete"
        :title="`Hapus ${customer.name}?`"
        message="Riwayat belanjanya tetap tersimpan di transaksi."
        confirm-text="Ya, Hapus"
        danger
        @confirm="router.delete(route('customers.destroy', customer.id))"
    />
</template>
