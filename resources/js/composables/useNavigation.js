import { computed } from 'vue';
import {
    Armchair, Bike, Boxes, Calculator, CalendarDays, ChefHat, CreditCard, HandCoins, House, IdCard, LayoutGrid, ListChecks, ListOrdered,
    MessageCircle, NotebookPen, Package, Palette, ReceiptText, SlidersHorizontal, Store, Tags, Truck, UserRound, Users, Wallet, ChartColumn,
    BadgePercent, CalendarClock, ClipboardList, Grid3x3, Handshake, MapPin, Printer, TrendingDown, Warehouse,
} from 'lucide-vue-next';
import { useAuth } from './useAuth';

/**
 * Satu daftar menu untuk navigasi bawah (HP) dan menu samping (laptop),
 * supaya keduanya selalu sama.
 *
 * - main: menu utama. Navigasi bawah HP maks. 5 menu (yang `desktopOnly` tidak ikut).
 * - settings: tampil langsung di menu samping laptop; di HP ada di halaman "Lainnya".
 * - external: halaman di luar Inertia (layar kasir), dibuka dengan link biasa.
 */
export function useNavigation() {
    const { can, hasModule } = useAuth();

    const businessRoutes = ['expenses.*', 'kitchen.*', 'queue.*', 'bookings.*', 'orders.*', 'members.*', 'receivables.*', 'deliveries.*', 'purchases.*', 'suppliers.*', 'commissions.*', 'tables.*', 'modifiers.*', 'price-levels.*', 'settings.whatsapp*', 'warehouse.*', 'promotions.*', 'labels.*'];

    const main = computed(() =>
        [
            { key: 'dashboard', label: 'Beranda', href: route('dashboard'), icon: House, match: ['dashboard'], show: true },
            { key: 'pos', label: 'Kasir', href: route('pos.show'), icon: Calculator, match: [], external: true, show: can('use_pos') },
            {
                key: 'products',
                label: 'Barang',
                href: route('products.index'),
                icon: Package,
                match: ['products.*', 'categories.*', ...(can('manage_products') ? [] : ['stock.*'])],
                show: can('manage_products'),
            },
            // Di HP, Stok dibuka lewat tab di halaman Barang (supaya menu bawah tidak penuh).
            { key: 'stock', label: 'Stok', href: route('stock.index'), icon: Boxes, match: ['stock.*'], show: can('manage_stock'), desktopOnly: can('manage_products') },
            { key: 'sales', label: 'Transaksi', href: route('sales.index'), icon: ReceiptText, match: ['sales.*', 'shifts.*'], mobileMatch: ['reports.*'], show: can('view_reports') },
            { key: 'reports', label: 'Laporan', href: route('reports.index'), icon: ChartColumn, match: ['reports.*'], show: can('view_reports'), desktopOnly: true },
            {
                key: 'more',
                label: 'Lainnya',
                href: route('more'),
                icon: LayoutGrid,
                match: ['more', 'outlets.*', 'staff.*', 'modules.*', 'preferences.*', 'dev.*', 'customers.*', 'payment-methods.*', ...businessRoutes],
                mobileOnly: true,
                show: true,
            },
        ].filter((item) => item.show),
    );

    const settings = computed(() =>
        [
            { key: 'customers', label: 'Pelanggan', description: 'Data & riwayat belanja pelanggan', href: route('customers.index'), icon: UserRound, match: ['customers.*'], show: can('manage_customers') },
            { key: 'staff', label: 'Karyawan', description: 'Tambah karyawan dan atur PIN', href: route('staff.index'), icon: Users, match: ['staff.*'], show: can('manage_staff') },
            { key: 'outlets', label: 'Outlet / Cabang', description: 'Tempat usaha, pajak, dan struk', href: route('outlets.index'), icon: Store, match: ['outlets.*'], show: can('manage_outlets') },
            { key: 'payment-methods', label: 'Cara Bayar', description: 'Tunai, QRIS, transfer, e-wallet', href: route('payment-methods.index'), icon: CreditCard, match: ['payment-methods.*'], show: can('manage_business') },
            { key: 'modules', label: 'Atur Fitur', description: 'Nyalakan atau matikan fitur', href: route('modules.index'), icon: SlidersHorizontal, match: ['modules.*'], show: can('manage_modules') },
            { key: 'display', label: 'Tampilan & Suara', description: 'Ukuran huruf, mode gelap, suara', href: route('preferences.edit'), icon: Palette, match: ['preferences.*'], show: true },
        ].filter((item) => item.show),
    );

    /**
     * Fitur sesuai jenis usaha: hanya muncul kalau modulnya dinyalakan di "Atur Fitur".
     */
    const business = computed(() =>
        [
            { key: 'expenses', label: 'Pengeluaran', description: 'Catat listrik, gaji, sewa, belanja kecil', href: route('expenses.index'), icon: Wallet, match: ['expenses.*'], show: hasModule('expenses') && can('manage_expenses') },
            { key: 'kitchen', label: 'Layar Dapur', description: 'Pesanan masuk untuk dapur / barista', href: route('kitchen.index'), icon: ChefHat, match: ['kitchen.*'], show: hasModule('kitchen_display') && can('use_pos') },
            { key: 'queue', label: 'Antrean', description: 'Ambil & panggil nomor antrean', href: route('queue.index'), icon: ListOrdered, match: ['queue.*'], show: hasModule('queue') && can('use_pos') },
            { key: 'bookings', label: 'Janji Temu', description: 'Jadwal pelanggan per karyawan', href: route('bookings.index'), icon: CalendarDays, match: ['bookings.*'], show: hasModule('booking') && can('use_pos') },
            { key: 'orders', label: 'Status Pesanan', description: 'Cucian / pesanan: proses sampai diambil', href: route('orders.index'), icon: ListChecks, match: ['orders.*'], show: hasModule('order_status') && can('use_pos') },
            { key: 'members', label: 'Member', description: 'Paket member & absen masuk', href: route('members.index'), icon: IdCard, match: ['members.*'], show: hasModule('membership') && can('use_pos') },
            { key: 'receivables', label: 'Kasbon / Utang', description: 'Siapa yang masih berutang', href: route('receivables.index'), icon: NotebookPen, match: ['receivables.*'], show: hasModule('kasbon') && can('manage_customers') },
            { key: 'deliveries', label: 'Pengiriman', description: 'Antar barang & surat jalan', href: route('deliveries.index'), icon: Bike, match: ['deliveries.*'], show: hasModule('delivery') && can('use_pos') },
            {
                key: 'warehouse', label: 'Beranda Gudang', description: 'Terima, olah, kemas, cek stok semua gudang', href: route('warehouse.dashboard'), icon: Warehouse,
                match: ['warehouse.dashboard', 'warehouse.repack', 'warehouse.production', 'warehouse.orders*'],
                show: ['multi_warehouse', 'weighed_receiving', 'repack', 'production', 'batch_lot', 'shrinkage'].some(hasModule) && can('manage_stock'),
            },
            { key: 'warehouse-formulas', label: 'Resep Kemas & Olah', description: 'Isi 1 karung, resep giling & racik', href: route('warehouse.formulas'), icon: ClipboardList, match: ['warehouse.formulas*'], show: (hasModule('repack') || hasModule('production')) && can('manage_stock') },
            { key: 'warehouse-batches', label: 'Batch & Kedaluwarsa', description: 'Nomor batch, kadar air, kedaluwarsa', href: route('warehouse.batches'), icon: CalendarClock, match: ['warehouse.batches'], show: hasModule('batch_lot') && can('manage_stock') },
            { key: 'warehouse-reports', label: 'Laporan Gudang', description: 'Susut, selisih timbang, hitung stok', href: route('warehouse.reports'), icon: TrendingDown, match: ['warehouse.reports'], show: (hasModule('shrinkage') || hasModule('weighed_receiving')) && can('manage_stock') },
            { key: 'warehouse-locations', label: 'Blok Gudang', description: 'Tempat simpan barang di gudang', href: route('warehouse.locations'), icon: MapPin, match: ['warehouse.locations*'], show: hasModule('multi_warehouse') && can('manage_stock') },
            { key: 'purchases', label: hasModule('weighed_receiving') ? 'Terima Barang' : 'Belanja ke Pemasok', description: 'Barang masuk & utang ke pemasok', href: route('purchases.index'), icon: Truck, match: ['purchases.*', 'suppliers.*'], show: hasModule('supplier_purchase') && can('manage_stock') },
            { key: 'commissions', label: 'Komisi Karyawan', description: 'Rekap komisi per karyawan', href: route('commissions.index'), icon: HandCoins, match: ['commissions.*'], show: hasModule('staff_commission') && can('view_reports') },
            { key: 'tables', label: 'Meja', description: 'Daftar meja untuk makan di tempat', href: route('tables.index'), icon: Armchair, match: ['tables.*'], show: hasModule('tables') && can('manage_business') },
            { key: 'modifiers', label: 'Pilihan & Tambahan', description: 'Ukuran, level gula, topping', href: route('modifiers.index'), icon: SlidersHorizontal, match: ['modifiers.*'], show: hasModule('variants_modifiers') && can('manage_products') },
            { key: 'promotions', label: 'Promo & Diskon', description: 'Diskon musiman otomatis di kasir', href: route('promotions.index'), icon: BadgePercent, match: ['promotions.*'], show: hasModule('promotions') && can('manage_products') },
            { key: 'labels', label: 'Cetak Label Barcode', description: 'Label untuk ditempel di barang', href: route('labels.index'), icon: Printer, match: ['labels.*'], show: hasModule('barcode_label') && can('manage_products') },
            { key: 'report-variants', label: 'Laporan Varian', description: 'Ukuran & warna terlaris dan menumpuk', href: route('reports.variants'), icon: Grid3x3, match: ['reports.variants'], show: hasModule('variant_matrix') && can('view_reports') },
            { key: 'report-consignment', label: 'Barang Titipan', description: 'Bagi hasil barang titipan', href: route('reports.consignment'), icon: Handshake, match: ['reports.consignment'], show: hasModule('consignment') && can('view_reports') },
            { key: 'price-levels', label: 'Tipe Harga', description: 'Harga grosir / harga khusus', href: route('price-levels.index'), icon: Tags, match: ['price-levels.*'], show: hasModule('price_levels') && can('manage_products') },
            { key: 'whatsapp', label: 'WhatsApp', description: 'Nomor pengirim & isi pesan', href: route('settings.whatsapp'), icon: MessageCircle, match: ['settings.whatsapp*'], show: hasModule('whatsapp_notification') && can('manage_business') },
        ].filter((item) => item.show),
    );

    /** mobile: di HP, Laporan dibuka lewat tab di halaman Transaksi, jadi menu Transaksi ikut menyala. */
    const isActive = (item, mobile = false) => [...item.match, ...(mobile ? (item.mobileMatch ?? []) : [])].some((name) => route().current(name));

    return { main, business, settings, isActive };
}
