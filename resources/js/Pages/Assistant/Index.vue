<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Bot, Check, Copy, MessageSquare, Phone, Plus, Send,
    Sparkles, Trash2, User, Zap, BarChart2, TrendingUp,
    AlertTriangle, Award, Users, Receipt
} from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import BigButton from '@/Components/ui/BigButton.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    messages: { type: Array, default: () => [] },
    phones: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    isClaudeConfigured: { type: Boolean, default: false },
    webhookUrl: { type: String, default: '' },
});

const activeTab = ref('chat'); // 'chat' | 'whatsapp'
const chatContainer = ref(null);
const copied = ref(false);

const quickPrompts = [
    { label: 'Omset Hari Ini', query: 'Berapa omset dan penjualan hari ini?', icon: BarChart2 },
    { label: 'Produk Terlaris', query: 'Apa saja 5 produk terlaris bulan ini?', icon: Award },
    { label: 'Stok Menipis', query: 'Ada barang yang stoknya menipis atau habis?', icon: AlertTriangle },
    { label: 'Performa Kasir', query: 'Bagaimana performa penjualan kasir hari ini?', icon: Users },
    { label: 'Laba Bersih', query: 'Berapa perkiraan laba kotor dan laba bersih bulan ini?', icon: TrendingUp },
    { label: 'Kasbon Tertunggak', query: 'Siapa pelanggan yang masih punya sisa kasbon?', icon: Receipt },
];

const askForm = useForm({
    message: '',
    driver: props.isClaudeConfigured ? 'claude' : 'demo',
});

const phoneForm = useForm({
    phone: '',
    label: 'Owner Toko',
});

function scrollToBottom() {
    nextTick(() => {
        if (chatContainer.value) {
            chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
        }
    });
}

onMounted(() => {
    scrollToBottom();
});

watch(() => props.messages.length, () => {
    scrollToBottom();
});

function sendQuery(text) {
    askForm.message = text;
    submitQuery();
}

function submitQuery() {
    if (!askForm.message.trim() || askForm.processing) return;

    askForm.post(route('assistant.ask'), {
        preserveScroll: true,
        onSuccess: () => {
            askForm.reset('message');
            scrollToBottom();
        },
    });
}

function submitPhone() {
    if (!phoneForm.phone.trim() || phoneForm.processing) return;

    phoneForm.post(route('assistant.phone.store'), {
        preserveScroll: true,
        onSuccess: () => {
            phoneForm.reset();
        },
    });
}

function deletePhone(id) {
    if (!confirm('Hapus nomor WhatsApp ini dari daftar akses Asisten AI?')) return;
    router.delete(route('assistant.phone.destroy', id), {
        preserveScroll: true,
    });
}

function copyWebhook() {
    navigator.clipboard.writeText(props.webhookUrl);
    copied.value = true;
    setTimeout(() => {
        copied.value = false;
    }, 2000);
}

function formatMarkdown(text) {
    if (!text) return '';
    // Format bold *text* to <strong>text</strong>
    let formatted = text.replace(/\*(.*?)\*/g, '<strong>$1</strong>');
    // Convert newlines to <br>
    formatted = formatted.replace(/\n/g, '<br>');
    return formatted;
}
</script>

<template>
    <Head title="Asisten Toko (AI)" />
    <PageHeader
        title="Asisten Toko (AI)"
        subtitle="Analisis omset, stok, laba & kasbon langsung dari data toko secara cerdas."
        :back-href="route('dashboard')"
    />

    <!-- Header Stats & Mode Info -->
    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <div class="card flex items-center gap-4 p-4">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-primary-soft text-primary-ink">
                <Sparkles :size="24" />
            </div>
            <div>
                <p class="text-sm font-semibold text-ink-soft">Pertanyaan Hari Ini</p>
                <p class="font-display text-2xl font-extrabold text-ink">
                    {{ stats.messages_today ?? 0 }} <span class="text-sm font-normal text-ink-soft">/ {{ stats.messages_limit ?? 100 }}</span>
                </p>
            </div>
        </div>

        <div class="card flex items-center gap-4 p-4">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-accent-soft text-accent-ink">
                <Zap :size="24" />
            </div>
            <div>
                <p class="text-sm font-semibold text-ink-soft">Driver AI Aktif</p>
                <p class="text-lg font-bold text-ink">
                    {{ isClaudeConfigured ? 'Claude 3.5 Haiku' : 'Mode Offline / Demo' }}
                </p>
                <p class="text-xs text-ink-soft">
                    {{ isClaudeConfigured ? 'Model penalaran resmi Anthropic' : 'Gratis, respon instan membaca database' }}
                </p>
            </div>
        </div>

        <div class="card flex items-center gap-4 p-4">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-surface-2 text-ink">
                <Phone :size="24" />
            </div>
            <div>
                <p class="text-sm font-semibold text-ink-soft">Akses WhatsApp</p>
                <p class="text-lg font-bold text-ink">
                    {{ phones.length }} Nomor Terdaftar
                </p>
                <p class="text-xs text-ink-soft">Bisa tanya asisten langsung dari WA</p>
            </div>
        </div>
    </div>

    <!-- Tab Selector -->
    <div class="mb-6 flex gap-2 border-b border-line pb-2">
        <button
            type="button"
            class="pressable flex items-center gap-2 rounded-xl px-4 py-2.5 text-base font-bold transition"
            :class="activeTab === 'chat' ? 'bg-primary text-on-primary' : 'bg-surface-2 text-ink hover:bg-surface-3'"
            @click="activeTab = 'chat'"
        >
            <MessageSquare :size="18" />
            Chat Asisten
        </button>
        <button
            type="button"
            class="pressable flex items-center gap-2 rounded-xl px-4 py-2.5 text-base font-bold transition"
            :class="activeTab === 'whatsapp' ? 'bg-primary text-on-primary' : 'bg-surface-2 text-ink hover:bg-surface-3'"
            @click="activeTab = 'whatsapp'"
        >
            <Phone :size="18" />
            Pengaturan WhatsApp Bot
        </button>
    </div>

    <!-- TAB 1: Chat Interface -->
    <div v-show="activeTab === 'chat'" class="flex flex-col gap-4">
        <!-- Quick Question Pills -->
        <div>
            <p class="mb-2 text-xs font-extrabold uppercase tracking-wider text-ink-soft">Pertanyaan Cepat:</p>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="q in quickPrompts"
                    :key="q.label"
                    type="button"
                    class="pressable flex items-center gap-1.5 rounded-full border border-line bg-surface px-3 py-1.5 text-sm font-bold text-ink shadow-xs hover:border-primary hover:text-primary-ink"
                    :disabled="askForm.processing"
                    @click="sendQuery(q.query)"
                >
                    <component :is="q.icon" :size="15" class="text-primary" />
                    {{ q.label }}
                </button>
            </div>
        </div>

        <!-- Chat Container Box -->
        <div class="card flex h-[500px] flex-col overflow-hidden">
            <!-- Messages Scroll Area -->
            <div ref="chatContainer" class="flex-1 space-y-4 overflow-y-auto p-4 md:p-6">
                <!-- Welcome Banner if empty -->
                <div v-if="messages.length === 0" class="flex h-full flex-col items-center justify-center text-center p-6 text-ink-soft">
                    <div class="mb-3 flex size-16 items-center justify-center rounded-3xl bg-primary-soft text-primary">
                        <Bot :size="36" />
                    </div>
                    <h3 class="text-xl font-extrabold text-ink">Halo! Ada yang bisa saya bantu hari ini?</h3>
                    <p class="mt-1 max-w-md text-sm text-ink-soft">
                        Tanyakan omset hari ini, stok yang mau habis, menu terlaris, kasir paling produktif, atau daftar kasbon pelanggan.
                    </p>
                </div>

                <!-- Chat History Messages -->
                <div
                    v-for="msg in messages"
                    :key="msg.id"
                    class="flex gap-3"
                    :class="msg.sender_type === 'user' ? 'justify-end' : 'justify-start'"
                >
                    <!-- Bot Avatar -->
                    <div
                        v-if="msg.sender_type === 'assistant'"
                        class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary-soft text-primary"
                    >
                        <Bot :size="20" />
                    </div>

                    <!-- Message Bubble -->
                    <div
                        class="max-w-[85%] rounded-2xl px-4 py-3 text-base shadow-xs md:max-w-[75%]"
                        :class="msg.sender_type === 'user' ? 'bg-primary text-on-primary rounded-tr-xs' : 'bg-surface-2 text-ink rounded-tl-xs'"
                    >
                        <!-- Content -->
                        <div class="leading-relaxed" v-html="formatMarkdown(msg.message)" />

                        <!-- Meta Info (tools used & time) -->
                        <div
                            class="mt-2 flex items-center justify-between gap-3 text-xs opacity-75"
                            :class="msg.sender_type === 'user' ? 'text-on-primary' : 'text-ink-soft'"
                        >
                            <span v-if="msg.tools_called && msg.tools_called.length > 0" class="inline-flex items-center gap-1 font-semibold">
                                <Zap :size="12" /> Data riil sistem
                            </span>
                            <span v-else />
                            <span>{{ new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}</span>
                        </div>
                    </div>

                    <!-- User Avatar -->
                    <div
                        v-if="msg.sender_type === 'user'"
                        class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-ink/10 text-ink"
                    >
                        <User :size="20" />
                    </div>
                </div>

                <!-- Loading Bubble -->
                <div v-if="askForm.processing" class="flex items-center gap-3">
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary-soft text-primary">
                        <Bot :size="20" />
                    </div>
                    <div class="rounded-2xl rounded-tl-xs bg-surface-2 px-4 py-3 text-sm text-ink-soft shadow-xs">
                        <span class="inline-flex items-center gap-2">
                            <span class="size-2 animate-ping rounded-full bg-primary" />
                            Sedang menganalisis data kasir...
                        </span>
                    </div>
                </div>
            </div>

            <!-- Input Bar -->
            <div class="border-t border-line bg-surface p-3">
                <form class="flex items-center gap-2" @submit.prevent="submitQuery">
                    <input
                        v-model="askForm.message"
                        type="text"
                        placeholder="Tanya omset, stok, produk terlaris, kasbon..."
                        class="min-h-12 flex-1 rounded-xl border border-line bg-surface-2 px-4 text-base text-ink focus:border-primary focus:bg-surface focus:outline-none"
                        :disabled="askForm.processing"
                    />
                    <button
                        type="submit"
                        class="pressable flex size-12 shrink-0 items-center justify-center rounded-xl bg-primary text-on-primary transition hover:opacity-90 disabled:opacity-50"
                        :disabled="!askForm.message.trim() || askForm.processing"
                    >
                        <Send :size="20" />
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- TAB 2: WhatsApp Bot Integration -->
    <div v-show="activeTab === 'whatsapp'" class="space-y-6">
        <!-- Tutorial Card -->
        <div class="card p-6">
            <h2 class="text-xl font-extrabold text-ink">Hubungkan dengan WhatsApp Toko (Fonnte)</h2>
            <p class="mt-1 text-base text-ink-soft">
                Anda dan manajer toko dapat bertanya langsung kepada Asisten AI melalui chat WhatsApp tanpa perlu membuka aplikasi kasir.
            </p>

            <div class="mt-4 rounded-2xl bg-surface-2 p-4">
                <p class="text-sm font-bold text-ink">Webhook URL untuk Fonnte:</p>
                <div class="mt-2 flex items-center gap-2">
                    <code class="flex-1 overflow-x-auto rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink">
                        {{ webhookUrl }}
                    </code>
                    <button
                        type="button"
                        class="pressable flex items-center gap-1.5 rounded-xl border border-line bg-surface px-3 py-2 text-sm font-bold text-ink hover:bg-surface-2"
                        @click="copyWebhook"
                    >
                        <Check v-if="copied" :size="16" class="text-success" />
                        <Copy v-else :size="16" />
                        {{ copied ? 'Tersalin' : 'Salin' }}
                    </button>
                </div>
                <p class="mt-2 text-xs text-ink-soft">
                    Tempelkan tautan ini di dashboard Fonnte.com pada menu <strong>Device &gt; Webhook URL</strong>.
                </p>
            </div>
        </div>

        <!-- Authorized Phones List -->
        <div class="card p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h3 class="text-xl font-extrabold text-ink">Nomor WhatsApp yang Diizinkan</h3>
                    <p class="text-sm text-ink-soft">
                        Hanya nomor yang terdaftar di bawah ini yang dapat menerima balasan data keuangan toko via WhatsApp demi keamanan privasi.
                    </p>
                </div>
            </div>

            <!-- Form Tambah Nomor -->
            <form class="mt-6 flex flex-wrap gap-3" @submit.prevent="submitPhone">
                <input
                    v-model="phoneForm.phone"
                    type="text"
                    placeholder="Contoh: 081234567890"
                    class="min-h-12 w-full flex-1 rounded-xl border border-line bg-surface-2 px-4 text-base text-ink focus:border-primary focus:bg-surface focus:outline-none sm:w-auto"
                    required
                />
                <input
                    v-model="phoneForm.label"
                    type="text"
                    placeholder="Label (Contoh: Pak Budi - Owner)"
                    class="min-h-12 w-full flex-1 rounded-xl border border-line bg-surface-2 px-4 text-base text-ink focus:border-primary focus:bg-surface focus:outline-none sm:w-auto"
                />
                <BigButton type="submit" :loading="phoneForm.processing" class="min-h-12">
                    <Plus :size="18" /> Tambah Nomor
                </BigButton>
            </form>

            <!-- Tabel / List Nomor -->
            <div class="mt-6 divide-y divide-line">
                <div v-if="phones.length === 0" class="py-6 text-center text-ink-soft">
                    Belum ada nomor WhatsApp yang didaftarkan.
                </div>
                <div
                    v-for="p in phones"
                    :key="p.id"
                    class="flex items-center justify-between py-3"
                >
                    <div class="flex items-center gap-3">
                        <div class="flex size-10 items-center justify-center rounded-xl bg-primary-soft text-primary">
                            <Phone :size="20" />
                        </div>
                        <div>
                            <p class="font-bold text-ink">+{{ p.phone_number }}</p>
                            <p class="text-sm text-ink-soft">{{ p.label || 'Tanpa label' }}</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="pressable flex size-10 items-center justify-center rounded-xl text-danger-ink hover:bg-danger-soft"
                        aria-label="Hapus nomor"
                        @click="deletePhone(p.id)"
                    >
                        <Trash2 :size="20" />
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
