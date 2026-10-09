<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { CircleCheck, Clock, FileText, Receipt, Store, Upload, XCircle } from 'lucide-vue-next';
import BigButton from '@/Components/ui/BigButton.vue';
import FlashMessages from '@/Components/ui/FlashMessages.vue';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import { formatRupiah } from '@/composables/useRupiah';

const props = defineProps({
    token: { type: String, required: true },
    business: { type: Object, required: true },
    customer: { type: String, required: true },
    today: { type: String, required: true },
    balance: { type: Number, required: true },
    due: { type: Number, required: true },
    items: { type: Array, required: true },
    paymentInfo: { type: String, default: '' },
    proofs: { type: Array, required: true },
});

const input = ref(null);
const preview = ref(null);
const fileName = ref('');
const working = ref(false);
let objectUrl = null;

const form = useForm({ amount: props.due || props.balance, file: null, note: '' });
const pending = computed(() => props.proofs.filter((p) => p.status === 'pending'));

const STATUS = {
    pending: { label: 'Menunggu dicek', icon: Clock, tone: 'text-info-ink' },
    approved: { label: 'Diterima', icon: CircleCheck, tone: 'text-primary-ink' },
    rejected: { label: 'Ditolak', icon: XCircle, tone: 'text-danger-ink' },
};

async function compress(file) {
    if (file.type === 'application/pdf') return file;
    const bitmap = await createImageBitmap(file).catch(() => null);
    if (!bitmap) return file;

    const scale = Math.min(1, 1600 / Math.max(bitmap.width, bitmap.height));
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(bitmap.width * scale);
    canvas.height = Math.round(bitmap.height * scale);
    canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);

    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.85));
    return blob ? new File([blob], 'bukti.jpg', { type: 'image/jpeg' }) : file;
}

async function onSelect(event) {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) return;

    working.value = true;
    const ready = await compress(file);
    working.value = false;

    if (objectUrl) URL.revokeObjectURL(objectUrl);
    objectUrl = ready.type.startsWith('image/') ? URL.createObjectURL(ready) : null;
    preview.value = objectUrl;
    fileName.value = ready.type === 'application/pdf' ? file.name : '';
    form.file = ready;
    form.clearErrors('file');
}

function submit() {
    form.post(route('kasbon.bill.proof', props.token), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset('file', 'note');
            preview.value = null;
            fileName.value = '';
        },
    });
}

onBeforeUnmount(() => objectUrl && URL.revokeObjectURL(objectUrl));
</script>

<template>
    <Head :title="`Tagihan ${business.name}`" />
    <FlashMessages />
    <div class="min-h-dvh bg-page pb-16">
        <header class="bg-brand px-4 pt-safe pb-5 text-white">
            <div class="mx-auto flex max-w-2xl items-center gap-3 pt-5">
                <img v-if="business.logo_url" :src="business.logo_url" alt="" class="size-14 rounded-2xl bg-white object-cover" />
                <span v-else class="flex size-14 items-center justify-center rounded-2xl bg-accent text-ink"><Store :size="28" aria-hidden="true" /></span>
                <div class="min-w-0 flex-1">
                    <h1 class="truncate font-display text-2xl font-extrabold">{{ business.name }}</h1>
                    <p v-if="business.phone || business.address" class="truncate text-base text-white/80">{{ [business.phone, business.address].filter(Boolean).join(' · ') }}</p>
                </div>
            </div>
        </header>

        <main class="mx-auto flex max-w-2xl flex-col gap-5 px-4 pt-5">
            <section class="card p-5">
                <p class="text-base text-ink-soft">Tagihan untuk</p>
                <h2 class="text-2xl font-extrabold text-ink">{{ customer }}</h2>
                <p class="text-base text-ink-soft">Per {{ today }}</p>

                <div v-if="balance > 0" class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-2xl bg-danger-soft p-4">
                        <span class="block text-base font-bold text-danger-ink">Sudah jatuh tempo</span>
                        <span class="block font-display text-3xl font-extrabold text-danger-ink tabular-nums">{{ formatRupiah(due) }}</span>
                    </div>
                    <div class="rounded-2xl bg-surface-2 p-4">
                        <span class="block text-base font-bold text-ink-soft">Total sisa tagihan</span>
                        <span class="block font-display text-3xl font-extrabold text-ink tabular-nums">{{ formatRupiah(balance) }}</span>
                    </div>
                </div>
                <p v-else class="mt-4 flex items-center gap-2 rounded-2xl bg-primary-soft p-4 text-xl font-extrabold text-primary-ink">
                    <CircleCheck :size="28" aria-hidden="true" /> Semua tagihan sudah lunas. Terima kasih!
                </p>
            </section>

            <section v-if="items.length">
                <h2 class="mb-3 text-xl font-extrabold text-ink">Rincian nota ({{ items.length }})</h2>
                <ul class="card divide-y divide-line">
                    <li v-for="item in items" :key="item.id" class="flex items-center justify-between gap-3 px-4 py-3">
                        <span class="min-w-0">
                            <span class="block text-lg font-bold text-ink">{{ item.label }}</span>
                            <span class="block text-base text-ink-soft">
                                {{ item.date }}
                                <template v-if="item.due_date"> · <span :class="item.overdue ? 'font-bold text-danger-ink' : ''">jatuh tempo {{ item.due_date }}</span></template>
                            </span>
                            <a v-if="item.receipt_url" :href="item.receipt_url" target="_blank" rel="noopener" class="mt-1 inline-flex min-h-10 items-center gap-1 text-base font-bold text-info-ink underline">
                                <Receipt :size="18" aria-hidden="true" /> Lihat nota
                            </a>
                        </span>
                        <span class="shrink-0 text-right">
                            <span class="block font-display text-lg font-extrabold text-ink tabular-nums">{{ formatRupiah(item.remaining) }}</span>
                            <span v-if="item.paid" class="block text-sm text-ink-soft">dari {{ formatRupiah(item.amount) }}</span>
                        </span>
                    </li>
                </ul>
            </section>

            <section v-if="paymentInfo && balance > 0" class="card p-5">
                <h2 class="mb-2 text-xl font-extrabold text-ink">Cara pembayaran</h2>
                <p class="whitespace-pre-line text-lg text-ink">{{ paymentInfo }}</p>
            </section>

            <section v-if="proofs.length">
                <h2 class="mb-3 text-xl font-extrabold text-ink">Bukti yang sudah dikirim</h2>
                <ul class="card divide-y divide-line">
                    <li v-for="p in proofs" :key="p.id" class="flex items-center justify-between gap-3 px-4 py-3">
                        <span class="min-w-0">
                            <span class="flex items-center gap-2 text-lg font-bold" :class="STATUS[p.status].tone">
                                <component :is="STATUS[p.status].icon" :size="20" aria-hidden="true" /> {{ STATUS[p.status].label }}
                            </span>
                            <span class="block text-base text-ink-soft">{{ p.date }}<template v-if="p.reason"> · {{ p.reason }}</template></span>
                        </span>
                        <span class="shrink-0 font-display text-lg font-extrabold text-ink tabular-nums">{{ formatRupiah(p.amount) }}</span>
                    </li>
                </ul>
            </section>

            <section v-if="balance > 0" class="card p-5">
                <h2 class="mb-1 text-xl font-extrabold text-ink">Kirim bukti pembayaran</h2>
                <p class="mb-4 text-base text-ink-soft">Setelah transfer, foto atau screenshot buktinya lalu kirim di sini. {{ business.name }} akan mengecek dan memberi kabar lewat WhatsApp.</p>

                <p v-if="pending.length >= 3" class="rounded-2xl bg-info-soft p-4 text-lg text-info-ink">Bukti sebelumnya masih dicek. Mohon tunggu konfirmasi dulu.</p>

                <form v-else class="flex flex-col gap-4" @submit.prevent="submit">
                    <MoneyInput v-model="form.amount" label="Jumlah yang dibayar" :hint="`Sisa tagihan ${formatRupiah(balance)}`" :error="form.errors.amount" />

                    <div>
                        <span class="mb-2 block text-lg font-bold text-ink">Foto / PDF bukti transfer</span>
                        <button
                            type="button"
                            class="pressable flex min-h-touch w-full items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-line px-4 py-4 text-lg font-bold text-ink hover:border-ink-soft"
                            :disabled="working"
                            @click="input.click()"
                        >
                            <Upload :size="24" aria-hidden="true" />
                            {{ working ? 'Menyiapkan foto...' : form.file ? 'Ganti file' : 'Pilih foto / PDF' }}
                        </button>
                        <img v-if="preview" :src="preview" alt="Pratinjau bukti pembayaran" class="mt-3 max-h-80 w-full rounded-2xl bg-surface-2 object-contain" />
                        <p v-else-if="fileName" class="mt-3 flex items-center gap-2 text-lg text-ink"><FileText :size="22" aria-hidden="true" /> {{ fileName }}</p>
                        <input ref="input" type="file" accept="image/*,application/pdf" class="hidden" @change="onSelect" />
                        <p v-if="form.errors.file" role="alert" class="mt-2 text-base font-bold text-danger-ink">{{ form.errors.file }}</p>
                    </div>

                    <label class="block">
                        <span class="mb-2 block text-lg font-bold text-ink">Catatan <span class="font-semibold text-ink-soft">(boleh dikosongkan)</span></span>
                        <textarea v-model="form.note" rows="2" maxlength="300" placeholder="Contoh: transfer dari rekening istri" class="w-full rounded-2xl border-2 border-line bg-surface px-4 py-3 text-lg text-ink focus:border-focus focus:outline-none"></textarea>
                    </label>

                    <BigButton type="submit" block size="large" :loading="form.processing" :disabled="!form.file || working">Kirim Bukti Pembayaran</BigButton>
                </form>
            </section>
        </main>
    </div>
</template>
