/**
 * Isi tombol "Bantuan" per halaman. Kalimat pendek, langkah bernomor, bahasa sehari-hari.
 * `video`: link video tutorial (YouTube). Kosongkan (null) bila belum ada.
 */
export const helpContent = {
    dashboard: {
        title: 'Halaman Beranda',
        intro: 'Ini halaman pertama setelah masuk. Di sini nanti tampil ringkasan jualan hari ini.',
        steps: [
            'Ketuk tombol menu di bawah layar untuk pindah halaman.',
            'Menu "Lainnya" berisi pengaturan: karyawan, outlet, fitur, dan tampilan.',
            'Ketuk tanda tanya (?) di setiap halaman kalau bingung.',
        ],
        video: null,
    },
    more: {
        title: 'Menu Lainnya',
        intro: 'Semua pengaturan dikumpulkan di sini supaya layar utama tetap sederhana.',
        steps: [
            'Ketuk salah satu menu untuk membukanya.',
            '"Ganti Pengguna" dipakai kalau HP ini dipakai bergantian dengan karyawan lain.',
            'Kalau ada masalah, ketuk "Hubungi Admin via WhatsApp".',
        ],
        video: null,
    },
    staff: {
        title: 'Mengatur Karyawan',
        intro: 'Daftarkan orang yang bekerja di usaha Anda supaya mereka bisa masuk dengan PIN.',
        steps: [
            'Ketuk "Tambah Karyawan".',
            'Isi nama, pilih jabatan, lalu buat PIN 4-6 angka.',
            'Beri tahu karyawan PIN-nya. Mereka masuk dengan memilih nama lalu mengetik PIN.',
        ],
        video: null,
    },
    'staff-form': {
        title: 'Mengisi Data Karyawan',
        intro: 'Pilih jabatan sesuai pekerjaannya.',
        steps: [
            'Kasir: hanya untuk melayani pembeli.',
            'Manajer: bisa mengatur barang dan karyawan. Manajer masuk dengan no HP dan kata sandi.',
            'Karyawan: barista, dapur, kapster, atau trainer.',
            'PIN adalah 4-6 angka rahasia untuk masuk cepat. Jangan pakai 1234 di usaha sungguhan.',
        ],
        video: null,
    },
    outlets: {
        title: 'Mengatur Outlet',
        intro: 'Outlet adalah tempat usaha Anda berjualan. Kalau punya cabang, tambahkan di sini.',
        steps: [
            'Ketuk "Tambah Outlet" untuk menambah cabang.',
            'Ketuk "Ubah" untuk mengganti nama, alamat, atau no HP outlet.',
            'Setiap cabang punya stok barangnya sendiri.',
        ],
        video: null,
    },
    'outlet-form': {
        title: 'Mengisi Data Outlet',
        intro: 'Nama dan alamat outlet akan tampil di struk belanja.',
        steps: ['Isi nama outlet, misalnya "Cabang Pasar Baru".', 'Alamat dan no HP boleh dikosongkan.', 'Ketuk "Simpan".'],
        video: null,
    },
    modules: {
        title: 'Mengatur Fitur',
        intro: 'Nyalakan hanya fitur yang Anda butuhkan supaya aplikasi tetap sederhana.',
        steps: [
            'Ketuk sakelar di kanan untuk menyalakan atau mematikan fitur.',
            'Fitur bertanda bintang disarankan untuk jenis usaha Anda.',
            'Mematikan fitur tidak menghapus data. Data kembali saat fitur dinyalakan lagi.',
        ],
        video: null,
    },
    products: {
        title: 'Daftar Barang',
        intro: 'Semua barang atau layanan yang Anda jual ada di sini.',
        steps: [
            'Ketuk "Tambah Barang" untuk menambah barang baru.',
            'Ketik nama di kolom cari, atau ketuk kategori untuk menyaring.',
            'Ketuk barang untuk mengubah harga, foto, atau stoknya.',
            'Barang bertanda "Contoh" adalah data contoh. Hapus semuanya dengan tombol "Hapus Data Contoh".',
        ],
        video: null,
    },
    'product-form': {
        title: 'Mengisi Data Barang',
        intro: 'Cukup isi nama dan harga jual. Yang lain boleh menyusul.',
        steps: [
            'Harga jual: harga yang dibayar pembeli.',
            'Modal: harga beli Anda. Dipakai untuk menghitung untung.',
            'Stok awal: jumlah barang yang ada sekarang.',
            'Batas stok menipis: aplikasi memberi tanda kalau stok sudah sedikit.',
        ],
        video: null,
    },
    categories: {
        title: 'Kategori',
        intro: 'Kategori mengelompokkan barang supaya mudah dicari di kasir. Contoh: Makanan, Minuman.',
        steps: ['Ketik nama kategori lalu ketuk "Tambah".', 'Ketuk "Ganti Nama" untuk mengubah.', 'Menghapus kategori tidak menghapus barangnya.'],
        video: null,
    },
    stock: {
        title: 'Stok Barang',
        intro: 'Lihat sisa stok setiap barang di outlet ini.',
        steps: [
            'Barang yang habis (merah) dan menipis (kuning) tampil paling atas.',
            '"Stok Masuk": catat barang yang baru datang.',
            '"Stok Keluar": catat barang rusak, hilang, atau dipakai sendiri.',
            '"Hitung Stok": cocokkan jumlah di aplikasi dengan barang di rak.',
        ],
        video: null,
    },
    'stock-adjust': {
        title: 'Mencatat Stok Masuk / Keluar',
        intro: 'Setiap catatan tersimpan di riwayat stok, jadi selalu bisa dicek.',
        steps: ['Cari dan ketuk barangnya.', 'Isi jumlahnya dengan tombol − / +.', 'Pilih alasannya, lalu ketuk "Simpan".'],
        video: null,
    },
    'stock-opname': {
        title: 'Hitung Stok',
        intro: 'Hitung barang di rak, lalu isi jumlah sebenarnya. Aplikasi akan menyamakan stoknya.',
        steps: [
            'Hitung barang satu per satu di rak.',
            'Isi jumlah yang Anda hitung di setiap barang.',
            'Barang yang tidak diisi tidak akan diubah.',
            'Ketuk "Simpan Hasil Hitung".',
        ],
        video: null,
    },
    'stock-transfer': {
        title: 'Kirim Stok ke Cabang',
        intro: 'Pindahkan barang dari outlet ini ke outlet lain.',
        steps: [
            'Pilih outlet tujuan, lalu pilih barang dan jumlahnya.',
            'Stok outlet ini langsung berkurang.',
            'Saat barang sampai, ketuk "Sudah Diterima" supaya stok outlet tujuan bertambah.',
        ],
        video: null,
    },
    sales: {
        title: 'Transaksi',
        intro: 'Semua penjualan tercatat di sini, per hari.',
        steps: [
            'Ketuk tanda panah kiri/kanan untuk pindah hari.',
            'Ketuk transaksi untuk melihat rinciannya dan membuka struk.',
            '"Batalkan Transaksi" membatalkan seluruh nota dan mengembalikan stok.',
            '"Kembalikan Barang" untuk pembeli yang mengembalikan sebagian barang.',
            'Tab "Buka / Tutup Kasir" menunjukkan apakah uang di laci pas atau ada selisih.',
        ],
        video: null,
    },
    reports: {
        title: 'Laporan',
        intro: 'Lihat berapa uang masuk, untung, dan barang paling laku.',
        steps: [
            'Pilih periode di atas: Hari ini, 7 hari, Bulan ini, atau tanggal sendiri.',
            '"Uang masuk" adalah uang yang benar-benar diterima. "Untung bersih" sudah dikurangi modal barang dan pengeluaran.',
            'Supaya untung benar, isi "Modal" di setiap barang dan catat pengeluaran (listrik, gaji, sewa).',
            'Tombol "Excel" mengunduh laporan lengkap. "Cetak / PDF" untuk dicetak atau dikirim lewat WhatsApp.',
        ],
        video: null,
    },
    kasbon: {
        title: 'Kasbon / Utang Pelanggan',
        intro: 'Pengganti buku utang. Semua utang pelanggan tercatat rapi dan bisa dicicil.',
        steps: [
            'Di kasir, pilih pelanggan lalu ketuk "Bayar Nanti / Kasbon" saat membayar.',
            'Pelanggan datang membayar? Buka namanya di sini lalu ketuk "Terima Bayar". Boleh dicicil.',
            'Ketuk "Ingatkan via WA" untuk mengirim pengingat yang sopan.',
            'Atur batas kasbon per pelanggan di halaman ubah pelanggan.',
        ],
        video: null,
    },
    customers: {
        title: 'Pelanggan',
        intro: 'Simpan nama & no HP pelanggan untuk kirim struk dan pengingat lewat WhatsApp.',
        steps: [
            'Pelanggan bisa ditambahkan dari sini atau langsung dari layar kasir.',
            'Ketuk pelanggan untuk melihat riwayat belanjanya.',
            'Tombol "Chat WhatsApp" membuka obrolan dengan pelanggan.',
        ],
        video: null,
    },
    display: {
        title: 'Tampilan & Suara',
        intro: 'Atur supaya aplikasi nyaman dilihat dan dipakai.',
        steps: [
            'Pilih "Besar" atau "Sangat Besar" kalau tulisan terasa kecil.',
            'Mode gelap lebih nyaman di malam hari dan menghemat baterai.',
            'Suara "ting" berbunyi saat transaksi berhasil. Bisa dimatikan.',
        ],
        video: null,
    },
};
