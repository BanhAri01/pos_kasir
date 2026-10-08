<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    BellRing, Boxes, ChartColumn, Check, ChefHat, MessageCircle, NotebookPen, QrCode, ReceiptText, Smartphone, Users, WifiOff, X,
} from 'lucide-vue-next';
import AppLogo from '@/Components/ui/AppLogo.vue';
import CaduceusMark from '@/Components/ui/CaduceusMark.vue';
import GreekKey from '@/Components/ui/GreekKey.vue';
import { formatRupiah } from '@/composables/useRupiah';

const props = defineProps({
    catalog: { type: Object, required: true },
    trialDays: { type: Number, default: 14 },
});

const page = usePage();
const waUrl = computed(() => `https://wa.me/${page.props.adminWhatsapp}?text=${encodeURIComponent('Halo admin Hermes POS, saya ingin tanya-tanya soal aplikasi kasirnya.')}`);

const features = [
    { icon: WifiOff, title: 'Tetap jualan walau internet mati', text: 'Transaksi tersimpan di HP, terkirim otomatis saat internet kembali.' },
    { icon: QrCode, title: 'Pelanggan pesan sendiri dari meja', text: 'Scan QR di meja, pilih menu, bayar QRIS atau di kasir. Pesanan langsung masuk ke kasir dan dapur.' },
    { icon: MessageCircle, title: 'Struk & pengingat lewat WhatsApp', text: 'Struk digital, pengingat utang, dan kabar pesanan siap diambil.' },
    { icon: Boxes, title: 'Stok dan resep otomatis', text: 'Stok berkurang setiap ada penjualan, termasuk bahan baku per menu.' },
    { icon: ChartColumn, title: 'Laporan untung harian', text: 'Penjualan, modal, untung, dan barang terlaris dalam bahasa yang mudah.' },
    { icon: NotebookPen, title: 'Kasbon & jual tempo', text: 'Catat utang pelanggan, cicilan, dan batas kredit tanpa buku tulis.' },
    { icon: ChefHat, title: 'Layar dapur & antrean', text: 'Pesanan muncul di tablet dapur, nomor antrean dipanggil berurutan.' },
    { icon: Users, title: 'Banyak karyawan & cabang', text: 'Kasir masuk pakai PIN, pemilik pantau semua cabang dari HP.' },
];

const businesses = ['Warung & toko kelontong', 'Kedai kopi & rumah makan', 'Toko bangunan', 'Toko baju', 'Gudang pakan', 'Salon & barbershop', 'Laundry', 'Tempat fitness'];

const comparison = [
    ['Hitung kembalian & total', 'Manual / kalkulator', true],
    ['Stok berkurang otomatis', 'Ditulis ulang tiap hari', true],
    ['Laporan untung', 'Dihitung akhir bulan', true],
    ['Jualan saat internet mati', 'Bisa (buku)', true],
    ['Struk lewat WhatsApp', 'Tidak bisa', true],
    ['Pelanggan pesan dari meja', 'Tidak bisa', true],
    ['Data aman kalau HP hilang', 'Buku bisa hilang', true],
];

const faqs = [
    ['Apakah perlu beli alat khusus?', 'Tidak. Cukup HP Android, iPhone, tablet, atau laptop yang sudah ada. Printer struk Bluetooth bisa ditambah kapan saja.'],
    ['Bagaimana kalau internet mati?', 'Kasir tetap bisa jualan. Semua transaksi disimpan di HP dan dikirim otomatis saat internet menyala lagi.'],
    ['Biaya bayar QRIS siapa yang menanggung?', 'Pembeli. Biaya layanan pembayaran ditambahkan ke tagihan pembeli, jadi Anda menerima harga menu utuh.'],
    ['Apakah bisa dicoba dulu?', `Bisa. Coba gratis ${props.trialDays} hari dengan semua fitur paket Bisnis, tanpa kartu kredit.`],
    ['Kalau lupa kata sandi?', 'Hubungi admin lewat WhatsApp, kata sandi baru dikirim langsung. Tidak perlu email.'],
];
</script>

<template>
    <Head title="Aplikasi Kasir untuk UMKM" />
    <div class="min-h-dvh bg-page text-ink">
        <header class="relative overflow-hidden bg-brand text-white">
            <CaduceusMark class="absolute -right-24 -bottom-24 size-120 text-accent opacity-[0.07]" />
            <GreekKey class="absolute inset-x-0 bottom-0 text-accent/60" :height="12" />
            <nav class="relative mx-auto flex max-w-6xl items-center gap-3 px-4 pt-safe py-4 sm:px-6">
                <AppLogo :size="40" variant="inverse" />
                <Link :href="route('login')" class="pressable ml-auto rounded-2xl px-4 py-2 text-lg font-bold text-white hover:bg-white/10">Masuk</Link>
                <Link :href="route('register')" class="pressable hidden rounded-2xl bg-accent px-4 py-2 text-lg font-extrabold text-ink sm:inline-flex">Coba Gratis</Link>
            </nav>
            <div class="relative mx-auto grid max-w-6xl gap-10 px-4 pt-8 pb-20 sm:px-6 lg:grid-cols-2 lg:items-center lg:pt-14">
                <div>
                    <p class="mb-3 inline-flex rounded-full bg-white/10 px-4 py-1 text-base font-bold text-accent">Aplikasi kasir untuk UMKM Indonesia</p>
                    <h1 class="font-greek text-4xl leading-tight font-bold tracking-wide sm:text-5xl">Kasir cepat,<br /><span class="text-accent">kabar sampai.</span></h1>
                    <p class="mt-5 max-w-xl text-xl text-white/85">Jualan, stok, laporan untung, kasbon, sampai pesanan dari meja pelanggan. Tulisan besar dan bahasa sehari-hari, mudah dipakai siapa saja.</p>
                    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                        <Link :href="route('register')" class="pressable flex min-h-touch-lg items-center justify-center rounded-2xl bg-accent px-8 text-xl font-extrabold text-ink">Coba Gratis {{ trialDays }} Hari</Link>
                        <a :href="waUrl" target="_blank" rel="noopener" class="pressable flex min-h-touch-lg items-center justify-center gap-2 rounded-2xl border-2 border-white/30 px-6 text-xl font-bold text-white"><MessageCircle :size="24" aria-hidden="true" /> Tanya Admin</a>
                    </div>
                    <p class="mt-4 text-base text-white/70">Tanpa kartu kredit. Tanpa alat khusus. Bisa di HP yang sudah ada.</p>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-3xl bg-white/10 p-5"><Smartphone :size="30" class="text-accent" aria-hidden="true" /><p class="mt-3 text-lg font-bold">Di HP, tablet, laptop</p></div>
                    <div class="rounded-3xl bg-white/10 p-5"><WifiOff :size="30" class="text-accent" aria-hidden="true" /><p class="mt-3 text-lg font-bold">Jalan walau offline</p></div>
                    <div class="rounded-3xl bg-white/10 p-5"><QrCode :size="30" class="text-accent" aria-hidden="true" /><p class="mt-3 text-lg font-bold">Pesan lewat QR meja</p></div>
                    <div class="rounded-3xl bg-white/10 p-5"><ReceiptText :size="30" class="text-accent" aria-hidden="true" /><p class="mt-3 text-lg font-bold">Struk via WhatsApp</p></div>
                </div>
            </div>
        </header>

        <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
            <h2 class="text-center font-display text-3xl font-extrabold sm:text-4xl">Semua yang dibutuhkan usaha Anda</h2>
            <p class="mx-auto mt-3 max-w-2xl text-center text-lg text-ink-soft">Fitur menyesuaikan jenis usaha. Nyalakan yang perlu, matikan yang tidak.</p>
            <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div v-for="f in features" :key="f.title" class="card p-5">
                    <span class="flex size-12 items-center justify-center rounded-2xl bg-accent-soft text-accent-ink"><component :is="f.icon" :size="26" aria-hidden="true" /></span>
                    <h3 class="mt-4 text-lg font-extrabold">{{ f.title }}</h3>
                    <p class="mt-1 text-base text-ink-soft">{{ f.text }}</p>
                </div>
            </div>
            <div class="mt-10 flex flex-wrap justify-center gap-2">
                <span v-for="b in businesses" :key="b" class="rounded-full bg-surface px-4 py-2 text-base font-bold text-ink shadow-sm">{{ b }}</span>
            </div>
        </section>

        <section class="bg-surface py-16">
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <h2 class="text-center font-display text-3xl font-extrabold sm:text-4xl">Harga jelas, tanpa biaya tersembunyi</h2>
                <p class="mx-auto mt-3 max-w-2xl text-center text-lg text-ink-soft">Langganan 12 bulan cukup bayar 10 bulan.</p>
                <div class="mt-10 grid gap-4 md:grid-cols-3">
                    <div v-for="plan in catalog.plans" :key="plan.key" class="flex flex-col rounded-3xl border-2 p-6" :class="plan.key === 'pro' ? 'border-accent bg-accent-soft/40' : 'border-line bg-page'">
                        <p v-if="plan.key === 'pro'" class="mb-2 self-start rounded-full bg-accent px-3 py-1 text-sm font-extrabold text-ink">Paling laris</p>
                        <h3 class="font-display text-2xl font-extrabold">{{ plan.label }}</h3>
                        <p class="mt-1 text-base text-ink-soft">{{ plan.tagline }}</p>
                        <p class="mt-4 font-display text-4xl font-extrabold">{{ formatRupiah(plan.price) }}<span class="text-lg font-bold text-ink-soft">/bulan</span></p>
                        <ul class="mt-5 flex flex-1 flex-col gap-2">
                            <li v-for="h in plan.highlights" :key="h" class="flex items-start gap-2 text-base"><Check :size="20" class="mt-0.5 shrink-0 text-primary-ink" aria-hidden="true" />{{ h }}</li>
                        </ul>
                        <Link :href="route('register')" class="pressable mt-6 flex min-h-touch items-center justify-center rounded-2xl bg-primary text-lg font-extrabold text-on-primary">Mulai Coba Gratis</Link>
                    </div>
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-4xl px-4 py-16 sm:px-6">
            <h2 class="text-center font-display text-3xl font-extrabold sm:text-4xl">Dibanding buku tulis & kalkulator</h2>
            <div class="card mt-8 overflow-x-auto">
                <table class="w-full min-w-[520px] text-left text-lg">
                    <thead><tr class="border-b border-line"><th class="p-4">Pekerjaan</th><th class="p-4 text-ink-soft">Buku / kalkulator</th><th class="p-4">Hermes POS</th></tr></thead>
                    <tbody>
                        <tr v-for="[task, manual] in comparison" :key="task" class="border-b border-line last:border-0">
                            <td class="p-4 font-bold">{{ task }}</td>
                            <td class="p-4 text-ink-soft"><span class="inline-flex items-center gap-1"><X :size="18" class="text-danger-ink" aria-hidden="true" />{{ manual }}</span></td>
                            <td class="p-4"><span class="inline-flex items-center gap-1 font-bold text-primary-ink"><Check :size="20" aria-hidden="true" />Otomatis</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="mx-auto max-w-3xl px-4 pb-16 sm:px-6">
            <h2 class="text-center font-display text-3xl font-extrabold">Pertanyaan yang sering ditanya</h2>
            <div class="mt-8 flex flex-col gap-3">
                <details v-for="[q, a] in faqs" :key="q" class="card group p-5">
                    <summary class="cursor-pointer list-none text-lg font-extrabold">{{ q }}</summary>
                    <p class="mt-2 text-lg text-ink-soft">{{ a }}</p>
                </details>
            </div>
        </section>

        <section class="bg-brand px-4 py-14 text-center text-white">
            <BellRing :size="36" class="mx-auto text-accent" aria-hidden="true" />
            <h2 class="mt-3 font-display text-3xl font-extrabold">Siap jualan lebih rapi mulai hari ini?</h2>
            <div class="mx-auto mt-6 flex max-w-md flex-col gap-3 sm:flex-row">
                <Link :href="route('register')" class="pressable flex min-h-touch-lg flex-1 items-center justify-center rounded-2xl bg-accent px-6 text-xl font-extrabold text-ink">Coba Gratis</Link>
                <a :href="waUrl" target="_blank" rel="noopener" class="pressable flex min-h-touch-lg flex-1 items-center justify-center rounded-2xl border-2 border-white/30 px-6 text-xl font-bold">Tanya Admin</a>
            </div>
            <p class="mt-8 text-base text-white/60">Hermes POS · Kasir cepat, kabar sampai.</p>
        </section>
    </div>
</template>
