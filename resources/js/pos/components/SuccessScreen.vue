<script setup>
/**
 * Layar setelah bayar: tanda centang besar, kembalian, lalu cetak struk / kirim WhatsApp.
 */
import { onMounted, ref } from 'vue';
import { CircleCheck, MessageCircle, Printer, ShoppingCart } from 'lucide-vue-next';
import BigButton from '@/Components/ui/BigButton.vue';
import BigInput from '@/Components/ui/BigInput.vue';
import { formatRupiah } from '@/composables/useRupiah';
import { store } from '../store';
import { loadPrinterSettings, printReceipt } from '../lib/printer';
import { showToast } from '../lib/toast';
import { whatsappReceiptUrl } from '../lib/whatsapp';

const props = defineProps({
    sale: { type: Object, required: true },
});

const emit = defineEmits(['done', 'printer']);

const askPhone = ref(false);
const phone = ref(props.sale.customer?.phone ?? '');
const printer = loadPrinterSettings();

async function print() {
    if (printer.method === 'none') {
        emit('printer');
        return;
    }
    try {
        showToast(await printReceipt(props.sale, store.boot, printer), 'info');
    } catch (e) {
        showToast(e.message, 'error');
    }
}

function sendWhatsapp() {
    if (!phone.value && !askPhone.value) {
        askPhone.value = true;
        return;
    }
    window.open(whatsappReceiptUrl(props.sale, store.boot, phone.value), '_blank', 'noopener');
}

onMounted(() => {
    showToast('Pembayaran berhasil!');
    if (printer.autoPrint && printer.method !== 'none') print();
});
</script>

<template>
    <div class="fixed inset-0 z-40 flex flex-col overflow-y-auto bg-page pt-safe pb-safe">
        <div class="mx-auto flex w-full max-w-lg flex-1 flex-col items-center justify-center px-5 py-8 text-center">
            <span class="flex size-28 animate-pop-in items-center justify-center rounded-full bg-primary text-on-primary shadow-float">
                <CircleCheck :size="64" :stroke-width="2.5" aria-hidden="true" />
            </span>
            <h1 class="mt-6 text-3xl font-extrabold text-ink">Pembayaran Berhasil</h1>
            <p class="mt-1 text-lg text-ink-soft">Nota {{ sale.number }} · Total {{ formatRupiah(sale.total) }}</p>
            <p v-if="sale.pending" class="mt-3 rounded-2xl bg-warn-soft px-4 py-2 text-lg font-bold text-warn-ink">
                Tersimpan aman di HP. Akan terkirim otomatis saat internet menyala.
            </p>

            <div v-if="sale.queue_number" class="mt-6 w-full rounded-3xl border-4 border-dashed border-primary p-5">
                <p class="text-xl font-bold text-ink">Nomor antrean</p>
                <p class="font-display text-7xl font-extrabold text-primary-ink">{{ sale.queue_number }}</p>
            </div>

            <div v-if="sale.due_amount > 0" class="mt-4 w-full rounded-3xl bg-accent-soft p-4 text-lg font-bold text-accent-ink">
                Sisa {{ formatRupiah(sale.due_amount) }} dicatat sebagai utang {{ sale.customer?.name }}.
            </div>

            <div v-if="sale.change_amount > 0" class="mt-6 w-full rounded-3xl bg-primary-soft p-6">
                <p class="text-xl font-bold text-primary-ink">Kembalian</p>
                <p class="font-display text-[clamp(2.25rem,13vw,3.75rem)] leading-tight font-extrabold text-primary-ink tabular-nums">{{ formatRupiah(sale.change_amount) }}</p>
            </div>

            <div class="mt-8 grid w-full gap-3 sm:grid-cols-2">
                <BigButton variant="secondary" @click="print">
                    <Printer :size="24" aria-hidden="true" />
                    {{ printer.method === 'none' ? 'Atur Printer' : 'Cetak Struk' }}
                </BigButton>
                <BigButton variant="soft" @click="sendWhatsapp">
                    <MessageCircle :size="24" aria-hidden="true" /> Kirim WhatsApp
                </BigButton>
            </div>

            <div v-if="askPhone" class="card mt-4 w-full p-4 text-left">
                <BigInput v-model="phone" label="No HP pelanggan" type="tel" inputmode="tel" placeholder="0812 3456 7890" />
                <BigButton block class="mt-3" :disabled="!phone" @click="sendWhatsapp">
                    <MessageCircle :size="22" aria-hidden="true" /> Kirim Struk
                </BigButton>
            </div>

            <BigButton size="large" block class="mt-8" @click="emit('done')">
                <ShoppingCart :size="26" aria-hidden="true" /> Transaksi Baru
            </BigButton>
        </div>
    </div>
</template>
