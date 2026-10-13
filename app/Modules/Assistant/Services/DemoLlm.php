<?php

namespace App\Modules\Assistant\Services;

use App\Models\Tenant;

class DemoLlm
{
    public function __construct(
        protected AiTools $tools
    ) {}

    public function generate(string $prompt, Tenant $tenant): array
    {
        $lower = strtolower($prompt);

        // 1. Deteksi Laba Rugi
        if (preg_match('/(laba|rugi|untung|margin|hpp|profit)/i', $lower)) {
            $period = $this->detectPeriod($lower, 'this_month');
            $data = $this->tools->execute('laba_rugi', ['period' => $period], $tenant);
            $text = $this->formatLabaRugi($data);

            return [
                'text' => $text,
                'tools_called' => [['name' => 'laba_rugi', 'input' => ['period' => $period], 'output' => $data]],
                'input_tokens' => 120,
                'output_tokens' => 220,
                'model' => 'demo',
            ];
        }

        // 2. Deteksi Kasbon / Utang Pelanggan
        if (preg_match('/(kasbon|utang|piutang|tempo|belum bayar|tagihan)/i', $lower)) {
            $data = $this->tools->execute('kasbon_pelanggan', ['limit' => 10], $tenant);
            $text = $this->formatKasbon($data);

            return [
                'text' => $text,
                'tools_called' => [['name' => 'kasbon_pelanggan', 'input' => ['limit' => 10], 'output' => $data]],
                'input_tokens' => 110,
                'output_tokens' => 200,
                'model' => 'demo',
            ];
        }

        // 3. Deteksi Stok Menipis / Habis
        if (preg_match('/(stok|habis|menipis|kulak|restock|sisa barang)/i', $lower)) {
            $data = $this->tools->execute('stok_menipis', ['limit' => 10], $tenant);
            $text = $this->formatStok($data);

            return [
                'text' => $text,
                'tools_called' => [['name' => 'stok_menipis', 'input' => ['limit' => 10], 'output' => $data]],
                'input_tokens' => 100,
                'output_tokens' => 210,
                'model' => 'demo',
            ];
        }

        // 4. Deteksi Produk Terlaris
        if (preg_match('/(laris|populer|favorit|bestseller|best seller|paling laku)/i', $lower)) {
            $period = $this->detectPeriod($lower, 'this_month');
            $data = $this->tools->execute('produk_terlaris', ['period' => $period, 'limit' => 5], $tenant);
            $text = $this->formatProdukTerlaris($data);

            return [
                'text' => $text,
                'tools_called' => [['name' => 'produk_terlaris', 'input' => ['period' => $period, 'limit' => 5], 'output' => $data]],
                'input_tokens' => 115,
                'output_tokens' => 230,
                'model' => 'demo',
            ];
        }

        // 5. Deteksi Performa Kasir
        if (preg_match('/(kasir|shift|pegawai|karyawan)/i', $lower)) {
            $period = $this->detectPeriod($lower, 'today');
            $data = $this->tools->execute('performa_kasir', ['period' => $period], $tenant);
            $text = $this->formatPerformaKasir($data);

            return [
                'text' => $text,
                'tools_called' => [['name' => 'performa_kasir', 'input' => ['period' => $period], 'output' => $data]],
                'input_tokens' => 110,
                'output_tokens' => 200,
                'model' => 'demo',
            ];
        }

        // 6. Deteksi Omset / Penjualan (default fallback query jika ada kata omset / penjualan)
        if (preg_match('/(omset|omzet|jual|penjualan|transaksi|pendapatan|uang masuk|hari ini|kemarin)/i', $lower)) {
            $period = $this->detectPeriod($lower, 'today');
            $data = $this->tools->execute('omset_penjualan', ['period' => $period], $tenant);
            $text = $this->formatOmset($data);

            return [
                'text' => $text,
                'tools_called' => [['name' => 'omset_penjualan', 'input' => ['period' => $period], 'output' => $data]],
                'input_tokens' => 130,
                'output_tokens' => 240,
                'model' => 'demo',
            ];
        }

        // Pertanyaan umum / sapaan
        return [
            'text' => "Halo! Saya Asisten AI Toko Anda 🤖✨\n\nSaya terhubung langsung dengan sistem kasir Anda. Anda bisa menanyakan hal-hal berikut:\n\n1. 📊 *\"Berapa omset hari ini?\"* (atau kemarin / bulan ini)\n2. 🏆 *\"Apa saja produk terlaris bulan ini?\"*\n3. ⚠️ *\"Ada barang yang stoknya mau habis?\"*\n4. 👥 *\"Bagaimana performa kasir hari ini?\"*\n5. 💰 *\"Berapa laba bersih bulan ini?\"*\n6. 📋 *\"Siapa pelanggan yang masih punya kasbon?\"*\n\nSilakan ketik pertanyaan Anda!",
            'tools_called' => [],
            'input_tokens' => 50,
            'output_tokens' => 120,
            'model' => 'demo',
        ];
    }

    private function detectPeriod(string $lower, string $default): string
    {
        if (str_contains($lower, 'kemarin')) return 'yesterday';
        if (str_contains($lower, 'bulan lalu')) return 'last_month';
        if (str_contains($lower, 'bulan ini')) return 'this_month';
        if (str_contains($lower, 'minggu ini')) return 'this_week';
        if (str_contains($lower, '7 hari')) return 'last_7_days';
        if (str_contains($lower, '30 hari')) return 'last_30_days';
        if (str_contains($lower, 'hari ini')) return 'today';

        return $default;
    }

    private function formatOmset(array $d): string
    {
        $omset = number_format($d['total_omset'], 0, ',', '.');
        $transaksi = $d['total_transaksi'];
        $avg = number_format($d['rata_rata_per_transaksi'], 0, ',', '.');
        $periode = $d['periode'];

        $growthText = '';
        if ($d['pertumbuhan_persen'] !== null) {
            $sign = $d['pertumbuhan_persen'] >= 0 ? '+' : '';
            $growthText = "\n📈 *Pertumbuhan:* {$sign}{$d['pertumbuhan_persen']}% dibanding periode sebelumnya";
        }

        $kasbonText = $d['total_kasbon_baru'] > 0
            ? "\n⚠️ *Kasbon baru dicatat:* Rp " . number_format($d['total_kasbon_baru'], 0, ',', '.')
            : '';

        return "📊 *Laporan Omset ({$periode})*\n\n" .
            "• *Total Omset:* Rp {$omset}\n" .
            "• *Jumlah Transaksi:* {$transaksi} nota\n" .
            "• *Rata-rata Belanja:* Rp {$avg} / transaksi" .
            $growthText .
            $kasbonText .
            "\n\nSemoga penjualan toko terus meningkat! 🚀";
    }

    private function formatProdukTerlaris(array $d): string
    {
        $periode = $d['periode'];
        $items = $d['produk'];

        if (empty($items)) {
            return "🏆 *Produk Terlaris ({$periode})*\n\nBelum ada transaksi penjualan yang tercatat pada periode ini.";
        }

        $lines = ["🏆 *Top {$d['jumlah_produk']} Produk Terlaris ({$periode})*\n"];
        $medals = ['🥇', '🥈', '🥉', '4️⃣', '5️⃣', '6️⃣', '7️⃣', '8️⃣', '9️⃣', '🔟'];

        foreach ($items as $i => $item) {
            $icon = $medals[$i] ?? '•';
            $qty = number_format($item['terjual_qty'], 0, ',', '.');
            $omset = number_format($item['total_penjualan'], 0, ',', '.');
            $lines[] = "{$icon} *{$item['nama_produk']}*\n   Terjual: {$qty} pcs | Omset: Rp {$omset}";
        }

        $lines[] = "\n💡 *Saran:* Pastikan stok barang-barang terlaris ini selalu terjaga agar tidak kehabisan!";

        return implode("\n", $lines);
    }

    private function formatStok(array $d): string
    {
        $items = $d['stok_kritis'];

        if (empty($items)) {
            return "✅ *Kondisi Stok Aman!*\n\nSemua barang saat ini berada di atas batas minimum stok. Tidak ada barang yang habis atau kritis.";
        }

        $lines = ["⚠️ *Perhatian: " . count($items) . " Produk Perlu Segera Direstock!*\n"];

        foreach ($items as $item) {
            $statusIcon = $item['status'] === 'HABIS' ? '🔴' : '🟡';
            $lines[] = "{$statusIcon} *{$item['nama']}*\n   Sisa: *{$item['sisa_stok']} {$item['satuan']}* (Batas aman: {$item['batas_minimum']})";
        }

        $lines[] = "\n📦 *Saran:* Segera buat pesanan belanja ke pemasok untuk menghindari potensi kehilangan penjualan.";

        return implode("\n", $lines);
    }

    private function formatPerformaKasir(array $d): string
    {
        $periode = $d['periode'];
        $kasir = $d['kasir'];

        if (empty($kasir)) {
            return "👥 *Performa Kasir ({$periode})*\n\nBelum ada transaksi kasir pada periode ini.";
        }

        $lines = ["👥 *Performa Kasir ({$periode})*\n"];

        foreach ($kasir as $i => $k) {
            $omset = number_format($k['total_omset'], 0, ',', '.');
            $lines[] = ($i + 1) . ". *{$k['nama_kasir']}*\n   {$k['total_transaksi']} transaksi | Total Rp {$omset}";
        }

        return implode("\n", $lines);
    }

    private function formatLabaRugi(array $d): string
    {
        $periode = $d['periode'];
        $omset = number_format($d['total_omset'], 0, ',', '.');
        $hpp = number_format($d['total_hpp_modal'], 0, ',', '.');
        $kotor = number_format($d['laba_kotor'], 0, ',', '.');
        $beban = number_format($d['beban_pengeluaran'], 0, ',', '.');
        $bersih = number_format($d['laba_bersih'], 0, ',', '.');

        $statusLaba = $d['laba_bersih'] >= 0 ? 'Surplus / Untung ✅' : 'Defisit / Rugi ⚠️';

        return "💰 *Ringkasan Laba Rugi ({$periode})*\n\n" .
            "• *Total Penjualan (Omset):* Rp {$omset}\n" .
            "• *Beban Pokok Modal (HPP):* Rp {$hpp}\n" .
            "-------------------------------\n" .
            "• *Laba Kotor:* Rp {$kotor} (Margin {$d['margin_kotor_persen']}%)\n" .
            "• *Beban Pengeluaran Usaha:* Rp {$beban}\n" .
            "===============================\n" .
            "• *Laba Bersih:* *Rp {$bersih}* (Net {$d['margin_bersih_persen']}%)\n" .
            "• *Status:* {$statusLaba}\n\n" .
            "💡 Catatan: HPP dihitung dari harga modal barang yang terjual, beban dihitung dari catatan pengeluaran.";
    }

    private function formatKasbon(array $d): string
    {
        $total = number_format($d['total_seluruh_piutang'], 0, ',', '.');
        $pelanggan = $d['pelanggan'];

        if (empty($pelanggan)) {
            return "🎉 *Tidak Ada Kasbon Tertunggak!*\n\nSemua tagihan pelanggan saat ini telah lunas.";
        }

        $lines = ["📋 *Daftar Kasbon Pelanggan Belum Lunas*\n"];
        $lines[] = "💵 *Total Seluruh Piutang:* Rp {$total}\n";

        foreach ($pelanggan as $p) {
            $sisa = number_format($p['sisa_utang'], 0, ',', '.');
            $due = $p['jatuh_tempo'] ? " (Jatuh tempo: {$p['jatuh_tempo']})" : "";
            $lines[] = "• *{$p['nama']}* ({$p['telepon']})\n   Utang: *Rp {$sisa}* ({$p['total_nota']} nota){$due}";
        }

        $lines[] = "\n📲 Anda bisa mengirimkan pengingat WhatsApp tagihan melalui menu Kasbon / Pelanggan.";

        return implode("\n", $lines);
    }
}
