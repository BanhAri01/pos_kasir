# Kontrak Halaman Pengepul Telur (untuk G2)

Ditulis Claude (C1). Backend sudah jadi dan dites (`tests/Feature/EggCollector`). Gemini membuat **tampilan saja** di `resources/js/Pages/Pengepul/**` (dan komponen di `resources/js/Components/Pengepul/**`).

## Aturan untuk tampilan
- Jangan menghitung uang/modal/untung di Vue. Semua angka rupiah datang dari backend. Yang boleh dihitung di Vue hanya **pratinjau berat**: total hasil sortir dan susut (= telur campur − total hasil) supaya pengguna melihatnya sebelum simpan. Backend tetap menghitung ulang.
- Jangan menambah rute, controller, migrasi, atau mengubah file di luar folder Pengepul. Butuh data baru → tulis permintaan di papan tugas.
- Pengguna orang tua: huruf besar, tombol ≥ 48px, bahasa sehari-hari, tampil baik di HP. Ikuti gaya halaman yang ada, contoh `resources/js/Pages/Warehouse/Repack.vue` (layout, komponen form, cara `useForm`, pesan flash).
- Angka berat dari backend sudah berupa teks tampilan (`"1,5"`). Input berat boleh pakai koma (`"1,5"`), backend menerima keduanya.
- Rupiah: pakai helper format rupiah yang sudah dipakai halaman lain.
- Menu samping/bawah sudah ditambahkan Claude (`useNavigation.js`: "Harga Telur Hari Ini", "Sortir Telur").
- Setelah halaman dibuat, ubah di `tests/Feature/EggCollector/EggCollectorTest.php` `->component('Pengepul/X', false)` menjadi `->component('Pengepul/X')` (supaya tes memastikan file halaman ada). Hanya baris itu yang boleh diubah di file tes.

---

## 1. `Pengepul/Prices` — Harga Hari Ini
Rute: `GET route('egg.prices')` (hanya pemilik/manajer: izin `manage_products`).

Props:
```js
date: '2026-10-15',                 // tanggal hari ini (zona waktu usaha)
outlet: { id: 1, name: 'Gudang Utama' },
rows: [
  {
    product_id: 12,
    name: 'Telur Campur',
    role: 'mixed',                  // 'mixed' = telur campur (dibeli dari peternak), 'grade' = hasil sortir
    unit: 'Kg',
    filled_today: false,            // false = belum diisi hari ini; nilai di bawah adalah isian awal dari harga terakhir
    buy_price: 24000,               // boleh null
    sell_price: 26000,
    previous: { date: '2026-10-14', buy_price: 24000, sell_price: 26000 } | null,
  },
]
```

Simpan: `POST route('egg.prices.store')`
```js
{ prices: [ { product_id: 12, buy_price: 24000 /* atau null */, sell_price: 26000 } ] }
```
- Kirim semua baris sekaligus (satu tombol "Simpan harga hari ini").
- Harga beli boleh kosong (`null`) untuk jenis hasil sortir. Tampilkan kolom harga beli terutama untuk telur campur.
- Error validasi: `prices`, `prices.N.sell_price`, `prices.N.buy_price`, `prices.N.product_id`.
- Berhasil: redirect balik + flash `success`.
- Tampilkan tanda bila `filled_today === false` ("Harga hari ini belum diisi") dan harga kemarin (`previous`) sebagai pembanding.
- Tautan kecil ke halaman Jenis Telur: `route('egg.types')`.

## 2. `Pengepul/Types` — Jenis Telur
Rute: `GET route('egg.types')` (izin `manage_products`).

Props:
```js
products: [ { id: 12, name: 'Telur Campur', unit: 'Kg', role: 'mixed' | 'grade' | null } ],
roles: { mixed: 'Telur campur', grade: 'Hasil sortir' },
```
Simpan: `PUT route('egg.types.update')`
```js
{ mixed: [12], grades: [13, 14, 15] }   // id barang; satu barang tidak boleh di dua daftar
```
Error: `mixed`, `grades`, `mixed.N`, `grades.N`. Berhasil: redirect ke `egg.prices` + flash `success`.

## 3. `Pengepul/Sorting` — Sortir Telur
Rute: `GET route('egg.sortings.create')` (pemilik, manajer, **kasir**). Bisa dibuka dari kasir dengan `?untuk=<product_id>&kg=<berat>` (jenis yang dipesan & beratnya).

Props:
```js
mixed:  [ { product_id: 12, name: 'Telur Campur', unit: 'Kg', stock: '100', sell_price: 26000 } ],
grades: [ { product_id: 13, name: 'Telur Besar', unit: 'Kg', stock: '0', sell_price: 28000 }, ... ],
prefill: { product_id: 13 | null, qty: '30' | null },   // isi awal dari ?untuk=&kg=
recent: [ /* 10 sortir terakhir, bentuk sama dengan `sorting` di SortingShow */ ],
can_see_cost: true | false,   // false untuk kasir: JANGAN tampilkan modal (backend juga tidak mengirimnya)
```

Simpan: `POST route('egg.sortings.store')`
```js
{
  mixed_product_id: 12,        // boleh dihilangkan bila hanya ada satu telur campur
  input_qty: '40',             // kg telur campur yang disortir
  outputs: [ { product_id: 13, qty: '30' }, { product_id: 14, qty: '8' }, { product_id: 15, qty: '1,5' } ],
  note: 'untuk pesanan Bu Ketut',   // opsional
  kembali: 'kasir',            // opsional: kirim bila halaman dibuka dari kasir → setelah simpan kembali ke kasir
}
```
- Pratinjau sebelum simpan: total hasil dan **pecah/susut = input − total hasil**. Bila total hasil > input tampilkan peringatan merah (backend juga menolak).
- Error: `input_qty`, `outputs`, `outputs.N.qty`, `outputs.N.product_id`, `mixed_product_id`.
- Berhasil: redirect ke `egg.sortings.create` (atau kasir) + flash `success`.
- Tampilkan stok telur campur saat ini dan stok tiap jenis.

## 4. `Pengepul/SortingShow` — Rincian sortir
Rute: `GET route('egg.sortings.show', uuid)`.

Props:
```js
sorting: {
  uuid, number: 'SR-...', date: '15 Okt 2026, 09:30', mixed: 'Telur Campur', user: 'Ari',
  input_qty: '40', output_qty: '39,5', shrinkage_qty: '0,5', note: null,
  items: [ { name: 'Telur Besar', qty: '30', unit_cost: 24807, total_cost: 744224 } ],  // unit_cost/total_cost hanya ada untuk yang boleh lihat laporan
  input_cost: 960000 | null, shrinkage_cost: 12000 | null,
}
```
Tampilkan modal hanya bila nilainya ada (bukan null/undefined).

## 5. `Pengepul/Summary` — Ringkasan (menyusul dari C2)
Kontrak ditambahkan setelah C2 selesai.
