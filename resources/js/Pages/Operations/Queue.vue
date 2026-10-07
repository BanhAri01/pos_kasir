<script setup>
/** Antrean walk-in: buat nomor, panggil berikutnya, tandai dilayani / selesai / lewati. */
import { computed, ref } from 'vue';
import { Head, router, useForm, usePoll } from '@inertiajs/vue3';
import { Megaphone, MonitorPlay, Plus } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import BigInput from '@/Components/ui/BigInput.vue';
import BottomSheet from '@/Components/ui/BottomSheet.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    tickets: { type: Array, required: true },
    current: { type: Array, required: true },
    waitingCount: { type: Number, required: true },
    staff: { type: Array, default: () => [] },
});

usePoll(8000, { only: ['tickets', 'current', 'waitingCount'] });

const adding = ref(false);
const form = useForm({ customer_name: '', service_note: '' });
const waiting = computed(() => props.tickets.filter((t) => t.status === 'waiting'));
const active = computed(() => props.tickets.filter((t) => ['called', 'serving'].includes(t.status)));
const finished = computed(() => props.tickets.filter((t) => ['done', 'skipped'].includes(t.status)).slice(-10).reverse());

const STATUS = { waiting: 'Menunggu', called: 'Dipanggil', serving: 'Sedang dilayani', done: 'Selesai', skipped: 'Dilewati' };

function take() {
    form.post(route('queue.store'), { preserveScroll: true, onSuccess: () => { form.reset(); adding.value = false; } });
}

function callNext() {
    router.post(route('queue.next'), {}, { preserveScroll: true });
}

function setStatus(ticket, status, staffId = ticket.staff_id) {
    router.put(route('queue.update', ticket.id), { status, staff_id: staffId }, { preserveScroll: true });
}
</script>

<template>
    <Head title="Antrean" />
    <PageHeader title="Antrean" :subtitle="`${waitingCount} orang menunggu`">
        <template #action>
            <a :href="route('queue.display')" target="_blank" class="pressable flex min-h-touch items-center gap-2 rounded-2xl border-2 border-line px-4 text-lg font-bold text-ink">
                <MonitorPlay :size="22" aria-hidden="true" /> Layar TV
            </a>
        </template>
    </PageHeader>

    <div class="mb-6 grid gap-3 sm:grid-cols-2">
        <BigButton size="large" :disabled="!waitingCount" @click="callNext"><Megaphone :size="26" aria-hidden="true" /> Panggil Berikutnya</BigButton>
        <BigButton size="large" variant="soft" @click="adding = true"><Plus :size="26" aria-hidden="true" /> Ambil Nomor Baru</BigButton>
    </div>

    <section v-if="active.length" class="mb-6">
        <h2 class="mb-3 text-xl font-extrabold text-ink">Sedang dipanggil / dilayani</h2>
        <ul class="grid gap-3 md:grid-cols-2">
            <li v-for="t in active" :key="t.id" class="card flex flex-col gap-3 border-2 border-primary p-4">
                <div class="flex items-center gap-4">
                    <span class="font-display text-5xl font-extrabold text-primary-ink tabular-nums">{{ t.number }}</span>
                    <div class="min-w-0">
                        <p class="truncate text-xl font-bold text-ink">{{ t.customer_name || 'Tanpa nama' }}</p>
                        <p class="text-base text-ink-soft">{{ STATUS[t.status] }}<template v-if="t.service_note"> · {{ t.service_note }}</template></p>
                    </div>
                </div>
                <label v-if="staff.length" class="block">
                    <span class="sr-only">Dilayani oleh</span>
                    <select :value="t.staff_id ?? ''" class="min-h-touch w-full rounded-2xl border-2 border-line bg-surface px-4 text-lg text-ink" @change="setStatus(t, 'serving', Number($event.target.value) || null)">
                        <option value="">Dilayani oleh…</option>
                        <option v-for="s in staff" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </label>
                <div class="grid grid-cols-3 gap-2">
                    <BigButton v-if="t.status === 'called'" variant="soft" @click="setStatus(t, 'serving')">Layani</BigButton>
                    <BigButton @click="setStatus(t, 'done')" :class="t.status === 'called' ? '' : 'col-span-2'">Selesai</BigButton>
                    <BigButton variant="ghost" @click="setStatus(t, 'skipped')">Lewati</BigButton>
                </div>
            </li>
        </ul>
    </section>

    <section class="mb-6">
        <h2 class="mb-3 text-xl font-extrabold text-ink">Menunggu</h2>
        <EmptyState v-if="!waiting.length" title="Tidak ada yang menunggu" message="Ketuk “Ambil Nomor Baru” saat pelanggan datang." />
        <ul v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            <li v-for="t in waiting" :key="t.id" class="card p-4">
                <p class="font-display text-4xl font-extrabold text-ink tabular-nums">{{ t.number }}</p>
                <p class="truncate text-lg text-ink-soft">{{ t.customer_name || 'Tanpa nama' }}</p>
            </li>
        </ul>
    </section>

    <section v-if="finished.length">
        <h2 class="mb-3 text-xl font-extrabold text-ink">Terakhir selesai</h2>
        <ul class="card divide-y divide-line">
            <li v-for="t in finished" :key="t.id" class="flex min-h-touch items-center justify-between gap-3 px-4 py-2 text-lg">
                <span><strong class="tabular-nums">{{ t.number }}</strong> · {{ t.customer_name || 'Tanpa nama' }}</span>
                <span class="text-ink-soft">{{ STATUS[t.status] }}<template v-if="t.staff"> · {{ t.staff }}</template></span>
            </li>
        </ul>
    </section>

    <BottomSheet v-model:open="adding" title="Ambil Nomor Antrean">
        <form class="flex flex-col gap-4" @submit.prevent="take">
            <BigInput v-model="form.customer_name" label="Nama pelanggan" optional placeholder="Contoh: Pak Budi" :error="form.errors.customer_name" />
            <BigInput v-model="form.service_note" label="Mau layanan apa" optional placeholder="Contoh: potong + cuci" :error="form.errors.service_note" />
            <BigButton type="submit" block size="large" :loading="form.processing">Buat Nomor</BigButton>
        </form>
    </BottomSheet>
</template>
