<script setup>
/**
 * Asisten Toko: tanya data usaha dengan bahasa sehari-hari, lewat aplikasi atau WhatsApp.
 * Asisten hanya membaca data; pulsa ditampilkan dalam persen supaya mudah dipahami.
 */
import { nextTick, onMounted, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { BatteryMedium, Bot, Lock, MessageCircle, Send, Sparkles, Trash2, UserRound } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import BigInput from '@/Components/ui/BigInput.vue';
import { api } from '@/pos/lib/api';

defineOptions({ layout: AppLayout });

const props = defineProps({
    allowed: { type: Boolean, default: false },
    requiredPlan: { type: String, default: 'Bisnis' },
    live: { type: Boolean, default: false },
    quota: { type: Object, default: () => ({ percent: 0, questions: 0 }) },
    messages: { type: Array, default: () => [] },
    canManagePhones: { type: Boolean, default: false },
    phones: { type: Array, default: () => [] },
    maxPhones: { type: Number, default: 3 },
    examples: { type: Array, default: () => [] },
});

const chat = ref([...props.messages]);
const quota = ref({ ...props.quota });
const question = ref('');
const sending = ref(false);
const error = ref('');
const list = ref(null);

const phoneForm = useForm({ phone: '', label: '' });

function scrollDown() {
    nextTick(() => {
        if (list.value) list.value.scrollTop = list.value.scrollHeight;
    });
}

onMounted(scrollDown);

async function ask(text = question.value) {
    const q = text.trim();
    if (!q || sending.value) return;

    error.value = '';
    sending.value = true;
    chat.value.push({ id: `q${Date.now()}`, role: 'user', text: q, time: '' });
    question.value = '';
    scrollDown();

    try {
        const res = await api.post(route('assistant.ask'), { question: q });
        chat.value.push({ id: `a${Date.now()}`, role: 'assistant', text: res.text, status: res.status, time: res.time });
        quota.value = res.quota;
    } catch (e) {
        error.value = e.message;
        question.value = q;
        chat.value.pop();
    } finally {
        sending.value = false;
        scrollDown();
    }
}

function addPhone() {
    phoneForm.post(route('assistant.phones.store'), { preserveScroll: true, onSuccess: () => phoneForm.reset() });
}

function removePhone(phone) {
    router.delete(route('assistant.phones.destroy', phone.id), { preserveScroll: true });
}

// *tebal* gaya WhatsApp -> tebal; teks lain di-escape dulu supaya aman ditampilkan.
function format(text) {
    const escaped = text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    return escaped.replace(/\*([^*\n]+)\*/g, '<strong>$1</strong>').replace(/(^|\s)_([^_\n]+)_(?=\s|$)/g, '$1<em>$2</em>');
}
</script>

<template>
    <Head title="Asisten Toko" />
    <PageHeader title="Asisten Toko" subtitle="Tanya penjualan, stok, dan untung dengan bahasa sehari-hari." :back-href="route('more')" />

    <div v-if="!allowed" class="card flex flex-col items-center gap-3 p-8 text-center">
        <Lock :size="40" class="text-ink-soft" aria-hidden="true" />
        <p class="text-xl font-bold text-ink">Asisten Toko tersedia di paket {{ requiredPlan }}</p>
        <p class="max-w-md text-lg text-ink-soft">Tanya "penjualan hari ini berapa?" atau "stok apa yang mau habis?" langsung dari WhatsApp, tanpa membuka laporan.</p>
        <Link :href="route('billing.index')" class="pressable mt-2 inline-flex min-h-touch items-center rounded-2xl bg-primary px-6 text-lg font-bold text-on-primary">Lihat Paket</Link>
    </div>

    <template v-else>
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <span class="inline-flex min-h-11 items-center gap-2 rounded-2xl bg-primary-soft px-4 text-lg font-bold text-primary-ink">
                <BatteryMedium :size="22" aria-hidden="true" />
                Pulsa {{ quota.percent }}% <span class="font-normal">(± {{ quota.questions }} pertanyaan)</span>
            </span>
            <span v-if="!live" class="text-base text-ink-soft">Asisten pintar belum diaktifkan admin, sementara dijawab dengan data singkat.</span>
        </div>

        <div class="card flex flex-col p-0">
            <div ref="list" class="flex max-h-[60vh] min-h-64 flex-col gap-3 overflow-y-auto p-4" aria-live="polite">
                <div v-if="chat.length === 0" class="py-6 text-center text-lg text-ink-soft">
                    <Sparkles :size="32" class="mx-auto mb-2 text-primary" aria-hidden="true" />
                    Pilih contoh pertanyaan di bawah, atau ketik sendiri.
                </div>
                <div v-for="m in chat" :key="m.id" class="flex gap-2" :class="m.role === 'user' ? 'flex-row-reverse' : ''">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full" :class="m.role === 'user' ? 'bg-surface-2 text-ink' : 'bg-primary-soft text-primary-ink'">
                        <UserRound v-if="m.role === 'user'" :size="20" aria-hidden="true" />
                        <Bot v-else :size="20" aria-hidden="true" />
                    </span>
                    <div class="max-w-[85%] whitespace-pre-line rounded-2xl px-4 py-3 text-lg leading-relaxed" :class="m.role === 'user' ? 'bg-primary text-on-primary' : 'bg-surface-2 text-ink'">
                        <span v-html="format(m.text)" />
                        <span v-if="m.time" class="mt-1 block text-right text-sm opacity-70">{{ m.time }}</span>
                    </div>
                </div>
                <div v-if="sending" class="flex items-center gap-2 text-lg text-ink-soft">
                    <Bot :size="20" class="animate-pulse text-primary" aria-hidden="true" /> Sedang membaca data toko...
                </div>
            </div>

            <div class="border-t border-line p-4">
                <div class="mb-3 flex flex-wrap gap-2">
                    <button v-for="ex in examples" :key="ex" type="button" class="pressable min-h-11 rounded-full border-2 border-line px-4 text-base font-semibold text-ink hover:bg-ink/5" :disabled="sending" @click="ask(ex)">
                        {{ ex }}
                    </button>
                </div>
                <form class="flex gap-2" @submit.prevent="ask()">
                    <label for="assistant-question" class="sr-only">Pertanyaan</label>
                    <input id="assistant-question" v-model="question" maxlength="500" class="min-h-touch flex-1 rounded-2xl border-2 border-line bg-surface px-4 text-lg text-ink" placeholder="Contoh: penjualan kemarin berapa?" :disabled="sending" />
                    <button type="submit" class="pressable flex min-h-touch min-w-touch items-center justify-center rounded-2xl bg-primary px-4 text-on-primary" :disabled="sending || !question.trim()" aria-label="Kirim pertanyaan">
                        <Send :size="24" aria-hidden="true" />
                    </button>
                </form>
                <p v-if="error" class="mt-2 text-base font-semibold text-danger">{{ error }}</p>
                <p class="mt-2 text-sm text-ink-soft">Asisten hanya membaca data, tidak mengubah apa pun. Ketik "pulsa" untuk cek sisa pulsa.</p>
            </div>
        </div>

        <section v-if="canManagePhones" class="card mt-6 p-4">
            <h2 class="flex items-center gap-2 text-xl font-bold text-ink"><MessageCircle :size="22" aria-hidden="true" /> Tanya lewat WhatsApp</h2>
            <p class="mt-1 text-lg text-ink-soft">Daftarkan nomor pemilik (maksimal {{ maxPhones }}). Nomor ini bisa mengirim pertanyaan ke nomor WhatsApp Asisten Hermes POS.</p>

            <ul v-if="phones.length" class="mt-3 divide-y divide-line">
                <li v-for="p in phones" :key="p.id" class="flex min-h-14 items-center justify-between gap-3">
                    <span class="text-lg text-ink">{{ p.phone }} <span v-if="p.label" class="text-ink-soft">· {{ p.label }}</span></span>
                    <button type="button" class="pressable flex min-h-12 items-center gap-1 rounded-xl px-3 text-base font-bold text-danger hover:bg-danger/10" @click="removePhone(p)">
                        <Trash2 :size="18" aria-hidden="true" /> Lepas
                    </button>
                </li>
            </ul>

            <form v-if="phones.length < maxPhones" class="mt-4 grid gap-3 sm:grid-cols-2" @submit.prevent="addPhone">
                <BigInput v-model="phoneForm.phone" label="Nomor WhatsApp" inputmode="tel" placeholder="0812 3456 7890" :error="phoneForm.errors.phone" />
                <BigInput v-model="phoneForm.label" label="Nama" optional placeholder="Contoh: Bapak" :error="phoneForm.errors.label" />
                <BigButton type="submit" class="sm:col-span-2" :disabled="phoneForm.processing">Daftarkan Nomor</BigButton>
            </form>
        </section>
    </template>
</template>
