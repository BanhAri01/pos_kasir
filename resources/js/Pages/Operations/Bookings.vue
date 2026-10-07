<script setup>
/** Janji temu per hari: geser tanggal, buat janji, ubah status (datang / selesai / batal / tidak datang). */
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { CalendarDays, ChevronLeft, ChevronRight, MessageCircle, Plus } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import BigInput from '@/Components/ui/BigInput.vue';
import BottomSheet from '@/Components/ui/BottomSheet.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { formatRupiah } from '@/composables/useRupiah';

defineOptions({ layout: AppLayout });

const props = defineProps({
    bookings: { type: Array, required: true },
    date: { type: String, required: true },
    services: { type: Array, required: true },
    staff: { type: Array, required: true },
});

const STATUS = {
    booked: { label: 'Terjadwal', tone: 'bg-surface-2 text-ink' },
    arrived: { label: 'Sudah datang', tone: 'bg-accent-soft text-accent-ink' },
    done: { label: 'Selesai', tone: 'bg-primary-soft text-primary-ink' },
    canceled: { label: 'Batal', tone: 'bg-danger-soft text-danger-ink' },
    no_show: { label: 'Tidak datang', tone: 'bg-danger-soft text-danger-ink' },
};

const adding = ref(false);
const form = useForm({ customer_name: '', customer_phone: '', staff_id: null, date: props.date, time: '10:00', product_ids: [], note: '' });

const dateLabel = computed(() => new Date(`${props.date}T00:00:00`).toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }));
const chosenTotal = computed(() => props.services.filter((s) => form.product_ids.includes(s.id)).reduce((sum, s) => sum + s.price, 0));
const chosenMinutes = computed(() => props.services.filter((s) => form.product_ids.includes(s.id)).reduce((sum, s) => sum + (s.duration_minutes || 30), 0));

function shift(days) {
    const d = new Date(`${props.date}T00:00:00`);
    d.setDate(d.getDate() + days);
    goTo(d.toLocaleDateString('sv-SE'));
}

function goTo(value) {
    router.get(route('bookings.index'), { tanggal: value }, { preserveState: true, replace: true });
}

function toggleService(id) {
    form.product_ids = form.product_ids.includes(id) ? form.product_ids.filter((x) => x !== id) : [...form.product_ids, id];
}

function openForm() {
    form.reset();
    form.date = props.date;
    adding.value = true;
}

function save() {
    form.post(route('bookings.store'), { preserveScroll: true, onSuccess: () => (adding.value = false) });
}

function setStatus(booking, status) {
    router.put(route('bookings.update', booking.id), { status }, { preserveScroll: true });
}
</script>

<template>
    <Head title="Janji Temu" />
    <PageHeader title="Janji Temu" :subtitle="dateLabel">
        <template #action>
            <BigButton @click="openForm"><Plus :size="24" aria-hidden="true" /> Buat Janji</BigButton>
        </template>
    </PageHeader>

    <div class="mb-5 flex items-center gap-2">
        <button type="button" class="pressable flex size-touch shrink-0 items-center justify-center rounded-2xl border-2 border-line" aria-label="Hari sebelumnya" @click="shift(-1)">
            <ChevronLeft :size="26" />
        </button>
        <label class="flex min-h-touch min-w-0 flex-1 items-center gap-2 rounded-2xl border-2 border-line bg-surface px-4">
            <CalendarDays :size="22" class="shrink-0 text-ink-soft" aria-hidden="true" />
            <span class="sr-only">Pilih tanggal</span>
            <input type="date" :value="date" class="min-w-0 flex-1 bg-transparent text-lg text-ink focus:outline-none" @change="goTo($event.target.value)" />
        </label>
        <button type="button" class="pressable flex size-touch shrink-0 items-center justify-center rounded-2xl border-2 border-line" aria-label="Hari berikutnya" @click="shift(1)">
            <ChevronRight :size="26" />
        </button>
    </div>

    <EmptyState v-if="!bookings.length" title="Belum ada janji di hari ini" message="Ketuk “Buat Janji” saat pelanggan memesan jadwal." />

    <ul class="grid gap-3 md:grid-cols-2">
        <li v-for="b in bookings" :key="b.id" class="card flex min-w-0 flex-col gap-3 p-4" :class="['canceled', 'no_show'].includes(b.status) ? 'opacity-60' : ''">
            <div class="flex items-start gap-4">
                <div class="shrink-0 rounded-2xl bg-primary-soft px-3 py-2 text-center text-primary-ink">
                    <p class="font-display text-2xl font-extrabold tabular-nums">{{ b.start }}</p>
                    <p class="text-sm font-bold">s/d {{ b.end }}</p>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-xl font-extrabold text-ink">{{ b.customer_name }}</p>
                    <p class="text-base text-ink-soft">{{ b.services }}</p>
                    <p class="text-base text-ink-soft"><template v-if="b.staff">Oleh {{ b.staff }} · </template>{{ formatRupiah(b.total_price) }}</p>
                </div>
                <span class="shrink-0 rounded-full px-3 py-1 text-sm font-bold" :class="STATUS[b.status].tone">{{ STATUS[b.status].label }}</span>
            </div>
            <p v-if="b.note" class="text-base text-ink">Catatan: {{ b.note }}</p>
            <div v-if="['booked', 'arrived'].includes(b.status)" class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                <BigButton v-if="b.status === 'booked'" variant="soft" @click="setStatus(b, 'arrived')">Datang</BigButton>
                <BigButton @click="setStatus(b, 'done')">Selesai</BigButton>
                <BigButton variant="ghost" @click="setStatus(b, 'no_show')">Tidak datang</BigButton>
                <BigButton variant="ghost" class="text-danger-ink" @click="setStatus(b, 'canceled')">Batal</BigButton>
            </div>
            <a v-if="b.phone_raw" :href="`https://wa.me/${b.phone_raw}`" target="_blank" rel="noopener" class="inline-flex items-center gap-2 text-lg font-bold text-primary-ink">
                <MessageCircle :size="20" aria-hidden="true" /> {{ b.customer_phone }}
            </a>
        </li>
    </ul>

    <BottomSheet v-model:open="adding" title="Buat Janji Temu">
        <form class="flex flex-col gap-4" @submit.prevent="save">
            <BigInput v-model="form.customer_name" label="Nama pelanggan" :error="form.errors.customer_name" />
            <BigInput v-model="form.customer_phone" label="No HP / WhatsApp" optional inputmode="tel" hint="Untuk pengingat otomatis lewat WhatsApp." :error="form.errors.customer_phone" />
            <div class="grid grid-cols-2 gap-3">
                <BigInput v-model="form.date" label="Tanggal" type="date" :error="form.errors.date" />
                <BigInput v-model="form.time" label="Jam" type="time" :error="form.errors.time" />
            </div>
            <div>
                <p class="mb-2 text-lg font-bold text-ink">Layanan</p>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="s in services"
                        :key="s.id"
                        type="button"
                        class="pressable min-h-touch rounded-2xl border-2 px-4 text-lg font-bold"
                        :class="form.product_ids.includes(s.id) ? 'border-primary bg-primary-soft text-primary-ink' : 'border-line text-ink'"
                        :aria-pressed="form.product_ids.includes(s.id)"
                        @click="toggleService(s.id)"
                    >
                        {{ s.name }}
                    </button>
                </div>
                <p v-if="form.errors.product_ids" class="mt-2 text-base font-bold text-danger-ink">{{ form.errors.product_ids }}</p>
                <p v-if="!services.length" class="text-base text-ink-soft">Belum ada layanan. Tambahkan di menu Barang dengan jenis “Layanan”.</p>
                <p v-if="form.product_ids.length" class="mt-2 text-base text-ink-soft">± {{ chosenMinutes }} menit · {{ formatRupiah(chosenTotal) }}</p>
            </div>
            <label class="block">
                <span class="mb-2 block text-lg font-bold text-ink">Dikerjakan oleh <span class="font-normal text-ink-soft">(boleh kosong)</span></span>
                <select v-model="form.staff_id" class="min-h-touch w-full rounded-2xl border-2 border-line bg-surface px-4 text-lg text-ink">
                    <option :value="null">Siapa saja</option>
                    <option v-for="s in staff" :key="s.id" :value="s.id">{{ s.name }}<template v-if="s.job_title"> ({{ s.job_title }})</template></option>
                </select>
                <span v-if="form.errors.staff_id" class="mt-2 block text-base font-bold text-danger-ink">{{ form.errors.staff_id }}</span>
            </label>
            <BigInput v-model="form.note" label="Catatan" optional :error="form.errors.note" />
            <BigButton type="submit" block size="large" :loading="form.processing">Simpan Janji</BigButton>
        </form>
    </BottomSheet>
</template>
