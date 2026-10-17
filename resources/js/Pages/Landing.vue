<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    BellRing,
    Boxes,
    ChartColumn,
    Check,
    ChefHat,
    HandCoins,
    MessageCircle,
    NotebookPen,
    Package,
    QrCode,
    ReceiptText,
    Scale,
    Smartphone,
    Users,
    Warehouse,
    WifiOff,
    X,
} from 'lucide-vue-next';
import AppLogo from '@/Components/ui/AppLogo.vue';
import KepengMark from '@/Components/ui/KepengMark.vue';
import KepengRow from '@/Components/ui/KepengRow.vue';
import { formatRupiah } from '@/composables/useRupiah';

const props = defineProps({
    catalog: { type: Object, required: true },
    trialDays: { type: Number, default: 14 },
});

const page = usePage();
const waUrl = computed(() => `https://wa.me/${page.props.adminWhatsapp}?text=${encodeURIComponent('Halo admin Kasira, saya ingin tanya-tanya soal aplikasi kasir dan POS gudangnya.')}`);


const features = [
    { icon: Warehouse, title: 'POS Gudang & Distributor Pakan', text: 'Timbang curah (kg), setara karung, kemas ulang, susut kadar air, titip uang deposit, dan tagihan WA.' },
    { icon: HandCoins, title: 'Titip Uang & Deposit Pelanggan', text: 'Simpan kelebihan transfer pembeli atau setoran uang di muka, otomatis memotong utang nota belanja berikutnya.' },
    { icon: WifiOff, title: 'Tetap jualan walau internet mati', text: 'Transaksi tersimpan di HP/komputer, terkirim otomatis saat internet kembali tersambung.' },
    { icon: MessageCircle, title: 'Struk & tagihan lewat WhatsApp', text: 'Struk digital, rincian nota utang tempo, saldo titip uang, dan tautan nota resmi langsung ke WA pelanggan.' },
    { icon: Boxes, title: 'Stok dan resep otomatis', text: 'Stok berkurang setiap penjualan, resep racik produksi dan bahan baku terhitung HPP-nya.' },
    { icon: ChartColumn, title: 'Laporan untung harian', text: 'Penjualan, modal HPP, untung bersih, dan barang terlaris dalam bahasa yang mudah.' },
    { icon: NotebookPen, title: 'Kasbon & jual tempo', text: 'Catat utang pembeli, cicilan, dan batas kredit tanpa repot buku tulis manual.' },
    { icon: QrCode, title: 'Pelanggan pesan sendiri dari meja', text: 'Scan QR di meja, pilih menu, bayar QRIS atau di kasir. Pesanan langsung masuk ke kasir dan dapur.' },
    { icon: ChefHat, title: 'Layar dapur & antrean', text: 'Pesanan muncul di tablet dapur atau barista, nomor antrean dipanggil berurutan.' },
    { icon: Users, title: 'Banyak karyawan & cabang', text: 'Kasir masuk pakai PIN, pemilik pantau semua cabang dari HP secara real-time.' },
];

const businesses = [
    'Gudang & Distributor Pakan',
    'Warung & Toko Kelontong',
    'Toko Bangunan & Bahan Bangunan',
    'Toko Baju & Fashion',
    'Kedai Kopi & Cafe',
    'Warung Makan & Resto',
    'Barbershop & Tempat Cukur',
    'Salon Kecantikan & Spa',
    'Laundry Kiloan & Satuan',
    'Gym & Tempat Fitness',
];

const warehouseHighlights = [
    {
        title: 'Timbang Curah (Kg) & Konversi Karung',
        desc: 'Timbang barang masuk dan keluar dalam kg asli, otomatis hitung setara karung dan selisih nota pemasok.',
        icon: Scale,
    },
    {
        title: 'Titip Uang (Deposit) & Overpayment',
        desc: 'Pembeli transfer melebihi utang atau setor uang dulu di gudang? Kelebihan otomatis disimpan aman dan memotong nota berikutnya.',
        icon: HandCoins,
    },
    {
        title: 'Tagihan WhatsApp & Sisa Bersih',
        desc: 'Kirim rincian tagihan nota, potongan saldo titip uang, sisa kewajiban bersih, dan tautan cek nota resmi langsung ke WA pelanggan.',
        icon: MessageCircle,
    },
    {
        title: 'Kemas Ulang, Racik Produksi & HPP',
        desc: 'Olah bahan baku, racik pakan, kemas ulang curah ke karungan, catat bahan kemas, pantau susut, dan modal HPP otomatis.',
        icon: Boxes,
    },
];

const comparison = [
    ['Hitung kembalian & total', 'Manual / kalkulator', true],
    ['Kelola stok timbangan curah & gudang', 'Buku tulis & rawan selisih', true],
    ['Titip uang & kelebihan transfer', 'Sering lupa dicatat', true],
    ['Stok berkurang otomatis', 'Ditulis ulang tiap hari', true],
    ['Laporan untung & modal HPP', 'Dihitung akhir bulan', true],
    ['Jualan saat internet mati', 'Bisa (buku)', true],
    ['Struk & tagihan lewat WhatsApp', 'Tidak bisa', true],
    ['Pelanggan pesan dari meja', 'Tidak bisa', true],
    ['Data aman kalau HP hilang', 'Buku bisa hilang', true],
];

const faqs = [
    ['Apakah Kasira cocok untuk gudang dan toko grosir?', 'Sangat cocok. Dilengkapi fitur stok timbangan curah (kg), konversi karungan, hitung susut, bahan kemas, kemas ulang, formula racik pakan, titip uang/deposit pelanggan, dan tagihan WA resmi.'],
    ['Apakah bisa untuk jenis usaha lain seperti toko kelontong, resto, atau jasa?', 'Bisa. Kasira mendukung multi-usaha: toko retail, toko bangunan, fashion, kedai kopi/resto (layar dapur & meja), barbershop, salon, hingga laundry. Modul bisa dinyalakan atau dimatikan sesuai kebutuhan.'],
    ['Bagaimana jika pembeli transfer melebihi total utang nota?', 'Sistem secara otomatis melunasi nota dan menyimpan sisa kelebihan uang ke Saldo Titip Uang (Deposit) pelanggan untuk memotong transaksi selanjutnya.'],
    ['Apakah perlu beli alat khusus?', 'Tidak. Cukup HP Android, iPhone, tablet, atau laptop yang sudah ada. Printer struk Bluetooth atau barcode scanner bisa ditambah kapan saja.'],
    ['Bagaimana kalau internet mati?', 'Kasir dan gudang tetap bisa mencatat transaksi secara offline. Semua data disimpan di HP/komputer dan otomatis disinkronkan saat online kembali.'],
    ['Biaya bayar QRIS siapa yang menanggung?', 'Pembeli. Biaya layanan pembayaran ditambahkan ke tagihan pembeli, jadi Anda menerima harga menu utuh.'],
    ['Apakah bisa dicoba dulu?', `Bisa. Coba gratis ${props.trialDays} hari dengan semua fitur lengkap tanpa kartu kredit.`],
    ['Kalau lupa kata sandi?', 'Hubungi admin lewat WhatsApp, kata sandi baru dikirim langsung. Tidak perlu email.'],
];

const faqJsonLd = computed(() => ({
    '@context': 'https://schema.org',
    '@type': 'FAQPage',
    mainEntity: faqs.map(([q, a]) => ({
        '@type': 'Question',
        name: q,
        acceptedAnswer: {
            '@type': 'Answer',
            text: a,
        },
    })),
}));
</script>

<template>
    <Head>
        <title>Aplikasi Kasir & Gudang untuk UMKM</title>
        <meta name="description" content="Aplikasi kasir (POS) dan manajemen gudang untuk UMKM Indonesia. Cocok untuk toko ritel, gudang & distributor pakan, F&B, dan jasa. Bisa jualan offline, struk WhatsApp, titip uang deposit, dan stok timbangan otomatis." />
        <meta name="keywords" content="aplikasi kasir, aplikasi pos kasir, aplikasi gudang, pos gudang, gudang pakan, kasir toko kelontong, software kasir umkm, aplikasi kasbon, kasir offline, titip uang deposit, hermes pos" />
        <meta property="og:title" content="Kasira - Aplikasi Kasir & Gudang untuk UMKM" />
        <meta property="og:description" content="Kasir cepat, kabar sampai. Jualan offline, struk & tagihan WhatsApp, titip uang deposit, dan manajemen gudang/toko lengkap." />
        <component :is="'script'" type="application/ld+json">{{ JSON.stringify(faqJsonLd) }}</component>
    </Head>

    <div class="min-h-dvh bg-page text-ink">
        <header class="relative overflow-hidden bg-brand text-white">
            <KepengMark class="absolute -right-24 -bottom-24 size-120 text-accent opacity-[0.07]" />
            <KepengRow class="absolute inset-x-0 bottom-0 text-accent/60" :height="12" />
            <nav class="relative mx-auto flex max-w-6xl items-center gap-3 px-4 pt-safe py-4 sm:px-6">
                <AppLogo :size="40" variant="inverse" />
                <Link :href="route('login')" class="pressable ml-auto rounded-2xl px-4 py-2 text-lg font-bold text-white hover:bg-white/10">Masuk</Link>
                <Link :href="route('register')" class="pressable hidden rounded-2xl bg-accent px-4 py-2 text-lg font-extrabold text-ink sm:inline-flex">Coba Gratis</Link>
            </nav>
            <div class="relative mx-auto grid max-w-6xl gap-10 px-4 pt-8 pb-20 sm:px-6 lg:grid-cols-2 lg:items-center lg:pt-14">
                <div>
                    <p class="mb-3 inline-flex rounded-full bg-white/10 px-4 py-1 text-base font-bold text-accent">
                        Aplikasi Kasir POS & Manajemen Gudang Modern di Indonesia
                    </p>
                    <h1 class="font-display text-4xl leading-tight font-extrabold tracking-tight sm:text-5xl">
                        Kasir cepat,<br /><span class="text-accent">kabar sampai.</span>
                    </h1>
                    <p class="mt-5 max-w-xl text-xl text-white/85">
                        Jualan kasir cepat, stok curah timbangan & karungan, titip uang deposit pelanggan, hingga tagihan WhatsApp otomatis. Cocok untuk toko ritel, gudang distributor pakan, F&B, dan jasa.
                    </p>
                    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                        <Link :href="route('register')" class="pressable flex min-h-touch-lg items-center justify-center rounded-2xl bg-accent px-8 text-xl font-extrabold text-ink shadow-md">
                            Coba Gratis {{ trialDays }} Hari
                        </Link>
                        <a :href="waUrl" target="_blank" rel="noopener" class="pressable flex min-h-touch-lg items-center justify-center gap-2 rounded-2xl border-2 border-white/30 px-6 text-xl font-bold text-white hover:bg-white/10">
                            <MessageCircle :size="24" aria-hidden="true" /> Tanya Admin
                        </a>
                    </div>
                    <p class="mt-4 text-base text-white/70">Tanpa kartu kredit. Tanpa alat khusus. Bisa di HP yang sudah ada.</p>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-3xl bg-white/10 p-5">
                        <Smartphone :size="30" class="text-accent" aria-hidden="true" />
                        <p class="mt-3 text-lg font-bold">Di HP, tablet, laptop</p>
                    </div>
                    <div class="rounded-3xl bg-white/10 p-5">
                        <WifiOff :size="30" class="text-accent" aria-hidden="true" />
                        <p class="mt-3 text-lg font-bold">Jalan walau offline</p>
                    </div>
                    <div class="rounded-3xl bg-white/10 p-5">
                        <Warehouse :size="30" class="text-accent" aria-hidden="true" />
                        <p class="mt-3 text-lg font-bold">POS Gudang & Grosir</p>
                    </div>
                    <div class="rounded-3xl bg-white/10 p-5">
                        <ReceiptText :size="30" class="text-accent" aria-hidden="true" />
                        <p class="mt-3 text-lg font-bold">Struk via WhatsApp</p>
                    </div>
                </div>
            </div>
        </header>

        <!-- SEKSI KHUSUS: POS GUDANG, GROSIR & DISTRIBUTOR PAKAN -->
        <section class="border-b border-line bg-surface-2/60 py-16">
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <div class="text-center">
                    <span class="inline-flex rounded-full bg-emerald-100 px-4 py-1 text-base font-bold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                        Spesialisasi Gudang & Grosir
                    </span>
                    <h2 class="mt-3 font-display text-3xl font-extrabold sm:text-4xl text-ink">
                        Solusi POS Gudang, Grosir & Distributor Pakan
                    </h2>
                    <p class="mx-auto mt-3 max-w-2xl text-lg text-ink-soft">
                        Didesain untuk operasional gudang dan toko grosir: timbangan curah, konversi karungan, kelebihan transfer/titip uang, hingga tagihan tempo transparan via WhatsApp.
                    </p>
                </div>

                <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <div v-for="item in warehouseHighlights" :key="item.title" class="card flex flex-col justify-between border-t-4 border-t-emerald-500 p-6">
                        <div>
                            <span class="flex size-14 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300">
                                <component :is="item.icon" :size="28" aria-hidden="true" />
                            </span>
                            <h3 class="mt-4 font-display text-xl font-extrabold text-ink">{{ item.title }}</h3>
                            <p class="mt-2 text-base text-ink-soft leading-relaxed">{{ item.desc }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- SEKSI SEMUA FITUR -->
        <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
            <h2 class="text-center font-display text-3xl font-extrabold sm:text-4xl">Semua yang dibutuhkan usaha Anda</h2>
            <p class="mx-auto mt-3 max-w-2xl text-center text-lg text-ink-soft">Fitur otomatis menyesuaikan jenis usaha Anda. Nyalakan yang perlu, matikan yang tidak.</p>
            <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="f in features" :key="f.title" class="card p-5 hover:border-line-active transition-all">
                    <span class="flex size-12 items-center justify-center rounded-2xl bg-accent-soft text-accent-ink">
                        <component :is="f.icon" :size="26" aria-hidden="true" />
                    </span>
                    <h3 class="mt-4 text-lg font-extrabold">{{ f.title }}</h3>
                    <p class="mt-1 text-base text-ink-soft">{{ f.text }}</p>
                </div>
            </div>
            <div class="mt-10 flex flex-wrap justify-center gap-2">
                <span v-for="b in businesses" :key="b" class="rounded-full bg-surface px-4 py-2 text-base font-bold text-ink shadow-sm border border-line">
                    {{ b }}
                </span>
            </div>
        </section>

        <!-- SEKSI HARGA -->
        <section class="bg-surface py-16">
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <h2 class="text-center font-display text-3xl font-extrabold sm:text-4xl">Harga jelas, tanpa biaya tersembunyi</h2>
                <p class="mx-auto mt-3 max-w-2xl text-center text-lg text-ink-soft">Langganan 12 bulan cukup bayar 10 bulan.</p>
                <div class="mt-10 grid gap-4 md:grid-cols-3">
                    <div v-for="plan in catalog.plans" :key="plan.key" class="flex flex-col rounded-3xl border-2 p-6" :class="plan.key === 'pro' ? 'border-accent bg-accent-soft/40 shadow-md' : 'border-line bg-page'">
                        <p v-if="plan.key === 'pro'" class="mb-2 self-start rounded-full bg-accent px-3 py-1 text-sm font-extrabold text-ink">Paling laris</p>
                        <h3 class="font-display text-2xl font-extrabold">{{ plan.label }}</h3>
                        <p class="mt-1 text-base text-ink-soft">{{ plan.tagline }}</p>
                        <p class="mt-4 font-display text-4xl font-extrabold">{{ formatRupiah(plan.price) }}<span class="text-lg font-bold text-ink-soft">/bulan</span></p>
                        <ul class="mt-5 flex flex-1 flex-col gap-2">
                            <li v-for="h in plan.highlights" :key="h" class="flex items-start gap-2 text-base">
                                <Check :size="20" class="mt-0.5 shrink-0 text-primary-ink" aria-hidden="true" />{{ h }}
                            </li>
                        </ul>
                        <Link :href="route('register')" class="pressable mt-6 flex min-h-touch items-center justify-center rounded-2xl bg-primary text-lg font-extrabold text-on-primary">Mulai Coba Gratis</Link>
                    </div>
                </div>
            </div>
        </section>

        <!-- SEKSI PERBANDINGAN -->
        <section class="mx-auto max-w-4xl px-4 py-16 sm:px-6">
            <h2 class="text-center font-display text-3xl font-extrabold sm:text-4xl">Dibanding buku tulis & kalkulator</h2>
            <div class="card mt-8 overflow-x-auto">
                <table class="w-full min-w-[520px] text-left text-lg">
                    <thead>
                        <tr class="border-b border-line">
                            <th class="p-4">Pekerjaan</th>
                            <th class="p-4 text-ink-soft">Buku / kalkulator</th>
                            <th class="p-4">Kasira</th>
                        </tr>
                    </thead>
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

        <!-- SEKSI FAQ (DENGAN SCHEMA RICH SNIPPETS) -->
        <section class="mx-auto max-w-3xl px-4 pb-16 sm:px-6">
            <h2 class="text-center font-display text-3xl font-extrabold">Pertanyaan yang sering ditanya (FAQ)</h2>
            <div class="mt-8 flex flex-col gap-3">
                <details v-for="[q, a] in faqs" :key="q" class="card group p-5">
                    <summary class="cursor-pointer list-none text-lg font-extrabold">{{ q }}</summary>
                    <p class="mt-2 text-lg text-ink-soft leading-relaxed">{{ a }}</p>
                </details>
            </div>
        </section>

        <!-- SEKSI CTA -->
        <section class="bg-brand px-4 py-14 text-center text-white">
            <BellRing :size="36" class="mx-auto text-accent" aria-hidden="true" />
            <h2 class="mt-3 font-display text-3xl font-extrabold">Siap kelola toko & gudang lebih rapi?</h2>
            <p class="mx-auto mt-2 max-w-lg text-lg text-white/80">Coba gratis dulu, tanpa kartu kredit. Kalau cocok, baru langganan.</p>
            <div class="mx-auto mt-6 flex max-w-md flex-col gap-3 sm:flex-row">
                <Link :href="route('register')" class="pressable flex min-h-touch-lg flex-1 items-center justify-center rounded-2xl bg-accent px-6 text-xl font-extrabold text-ink">Coba Gratis</Link>
                <a :href="waUrl" target="_blank" rel="noopener" class="pressable flex min-h-touch-lg flex-1 items-center justify-center rounded-2xl border-2 border-white/30 px-6 text-xl font-bold">Tanya Admin</a>
            </div>
            <p class="mt-8 text-base text-white/60">Kasira · Kasir cepat, kabar sampai.</p>
        </section>
    </div>
</template>
