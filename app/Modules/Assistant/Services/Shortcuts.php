<?php

namespace App\Modules\Assistant\Services;

use App\Core\Support\Rupiah;
use App\Core\Tenancy\TenantContext;
use Illuminate\Support\Carbon;

/**
 * Jawaban dasar tanpa AI (saat pulsa habis, AI belum disetel, atau gagal dihubungi).
 * Memakai alat yang sama sehingga angkanya tetap sama dengan laporan.
 */
class Shortcuts
{
    public function __construct(
        private readonly StoreTools $tools,
        private readonly TenantContext $context,
    ) {}

    public function answer(string $question): ?string
    {
        $q = mb_strtolower($question);
        $has = fn (array $words) => collect($words)->contains(fn ($w) => str_contains($q, $w));

        return match (true) {
            $has(['stok', 'habis', 'menipis', 'belanja']) => $this->lowStock(),
            $has(['utang', 'hutang', 'kasbon', 'piutang', 'belum bayar']) => $this->receivables(),
            $has(['untung', 'laba', 'rugi']) => $this->profit(),
            $has(['omzet', 'omset', 'penjualan', 'jualan', 'pendapatan', 'hari ini']) => $this->today(),
            default => null,
        };
    }

    public function menu(): string
    {
        return "Yang tetap bisa ditanyakan:\n• Penjualan hari ini\n• Stok yang menipis\n• Siapa yang masih utang\n• Untung bulan ini";
    }

    private function today(): string
    {
        $s = $this->tools->run('ringkasan_penjualan', []);

        return '🧾 Penjualan hari ini *'.Rupiah::format($s['uang_masuk']).'* dari '.$s['jumlah_nota'].' nota.';
    }

    private function profit(): string
    {
        $s = $this->tools->run('ringkasan_penjualan', ['dari' => Carbon::now($this->context->get()->timezone ?: config('app.timezone'))->startOfMonth()->toDateString()]);
        $word = $s['untung_bersih'] >= 0 ? 'Untung' : 'Rugi';

        return '📊 '.$word.' bulan ini *'.Rupiah::format(abs($s['untung_bersih'])).'*'."\n"
            .'• Uang masuk: '.Rupiah::format($s['uang_masuk'])."\n"
            .'• Pengeluaran: '.Rupiah::format($s['pengeluaran']);
    }

    private function lowStock(): string
    {
        $outlets = $this->tools->run('stok_menipis', [])['outlet'];
        $items = collect($outlets)->flatMap(fn ($o) => collect($o['barang'])->map(fn ($b) => '• '.$b['nama'].' sisa '.$b['sisa'].($b['satuan'] ? ' '.$b['satuan'] : '')));

        return $items->isEmpty()
            ? '📦 Tidak ada stok yang menipis.'
            : "📦 Stok menipis:\n".$items->take(10)->implode("\n").($items->count() > 10 ? "\n• dan ".($items->count() - 10).' barang lain' : '');
    }

    private function receivables(): string
    {
        $r = $this->tools->run('piutang', []);

        if ($r['jumlah_pelanggan'] === 0) {
            return '💰 Tidak ada pelanggan yang masih utang.';
        }

        return '💰 Utang pelanggan *'.Rupiah::format($r['total_piutang']).'* ('.$r['jumlah_pelanggan']." orang)\n"
            .collect($r['pelanggan'])->take(5)->map(fn ($p) => '• '.$p['nama'].' '.Rupiah::format($p['sisa']))->implode("\n");
    }
}
