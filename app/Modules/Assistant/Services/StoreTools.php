<?php

namespace App\Modules\Assistant\Services;

use App\Core\Tenancy\TenantContext;
use App\Models\Outlet;
use App\Modules\Customer\Models\Customer;
use App\Modules\Inventory\Services\RestockService;
use App\Modules\Operations\Models\Receivable;
use App\Modules\Report\Services\ReportFilter;
use App\Modules\Report\Services\ReportService;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Alat baca untuk Asisten Toko. Semua angka berasal dari ReportService yang sama dengan
 * halaman Laporan, selalu dalam usaha aktif (TenantContext), dan tidak ada yang menulis data.
 */
class StoreTools
{
    private const MAX_RANGE_DAYS = 366;

    public function __construct(
        private readonly TenantContext $context,
        private readonly ReportService $reports,
        private readonly RestockService $restock,
    ) {}

    public function definitions(): array
    {
        $range = [
            'dari' => ['type' => 'string', 'description' => 'Tanggal awal YYYY-MM-DD'],
            'sampai' => ['type' => 'string', 'description' => 'Tanggal akhir YYYY-MM-DD'],
            'outlet' => ['type' => 'string', 'description' => 'Nama outlet/cabang (opsional, kosong = semua)'],
        ];

        return [
            $this->tool('info_toko', 'Nama usaha, tanggal hari ini, dan daftar outlet/cabang.', []),
            $this->tool('ringkasan_penjualan', 'Uang masuk, jumlah nota, rata-rata belanja, diskon, pengembalian, modal, untung kotor, pengeluaran, untung bersih, dan perbandingan dengan periode sebelumnya. Bawaan hari ini.', $range),
            $this->tool('penjualan_harian', 'Penjualan per hari (per bulan bila lebih dari 62 hari) untuk melihat naik/turun. Bawaan 7 hari terakhir.', $range),
            $this->tool('barang_terlaris', 'Barang/menu terlaris beserta jumlah, penjualan, modal, dan untungnya. Bawaan bulan ini.', $range + [
                'urut' => ['type' => 'string', 'enum' => ['penjualan', 'jumlah', 'untung'], 'description' => 'Urutkan menurut, bawaan penjualan'],
                'batas' => ['type' => 'integer', 'description' => 'Jumlah barang 1-20, bawaan 10'],
            ]),
            $this->tool('cara_bayar', 'Uang masuk per cara bayar (tunai, QRIS, transfer, dll). Bawaan hari ini.', $range),
            $this->tool('kasir', 'Penjualan per kasir yang menerima pembayaran. Bawaan hari ini.', $range),
            $this->tool('jam_ramai', 'Jumlah nota dan penjualan per jam. Bawaan 7 hari terakhir.', $range),
            $this->tool('pengeluaran', 'Pengeluaran per kategori (listrik, gaji, sewa, dll). Bawaan bulan ini.', $range),
            $this->tool('stok_menipis', 'Barang yang stoknya di bawah atau sama dengan batas minimum, per outlet.', [
                'outlet' => ['type' => 'string', 'description' => 'Nama outlet (opsional)'],
            ]),
            $this->tool('piutang', 'Pelanggan yang masih punya kasbon/utang: total, yang sudah jatuh tempo, dan saldo titip uang.', []),
        ];
    }

    public function run(string $name, array $input): array
    {
        return match ($name) {
            'info_toko' => $this->info(),
            'ringkasan_penjualan' => $this->summary($input),
            'penjualan_harian' => $this->series($input),
            'barang_terlaris' => $this->products($input),
            'cara_bayar' => $this->payments($input),
            'kasir' => $this->cashiers($input),
            'jam_ramai' => $this->hours($input),
            'pengeluaran' => $this->expenses($input),
            'stok_menipis' => $this->lowStock($input),
            'piutang' => $this->receivables(),
            default => throw new InvalidArgumentException('Alat tidak dikenal: '.$name),
        };
    }

    private function tool(string $name, string $description, array $properties): array
    {
        return [
            'name' => $name,
            'description' => $description,
            'inputSchema' => $properties === [] ? ['type' => 'object'] : ['type' => 'object', 'properties' => $properties],
        ];
    }

    private function info(): array
    {
        $tenant = $this->context->get();

        return [
            'usaha' => $tenant->name,
            'hari_ini' => $this->today()->locale('id')->translatedFormat('l, j F Y'),
            'tanggal' => $this->today()->toDateString(),
            'outlet' => Outlet::query()->orderBy('name')->pluck('name')->all(),
        ];
    }

    private function summary(array $input): array
    {
        $f = $this->filter($input, 0);
        $s = $this->reports->summaryWithComparison($f);

        return $this->period($f) + [
            'uang_masuk' => $s['total_collected'],
            'jumlah_nota' => $s['transactions'],
            'rata_rata_per_nota' => $s['average'],
            'penjualan_bersih' => $s['net_sales'],
            'diskon' => $s['discounts'],
            'pengembalian' => $s['refunds'],
            'belum_dibayar_kasbon' => $s['unpaid'],
            'modal_barang_terjual' => $s['cogs'],
            'untung_kotor' => $s['gross_profit'],
            'margin_persen' => $s['margin'],
            'pengeluaran' => $s['expenses'],
            'untung_bersih' => $s['net_profit'],
            'periode_sebelumnya' => [
                'uang_masuk' => $s['previous']['total_collected'],
                'jumlah_nota' => $s['previous']['transactions'],
                'untung_bersih' => $s['previous']['net_profit'],
            ],
            'perubahan_persen' => $s['change'],
        ];
    }

    private function series(array $input): array
    {
        $f = $this->filter($input, 6);

        return $this->period($f) + [
            'data' => array_map(fn ($p) => ['tanggal' => $p['key'], 'penjualan' => $p['total'], 'nota' => $p['transactions']], $this->reports->series($f)),
        ];
    }

    private function products(array $input): array
    {
        $f = $this->filter($input, null);
        $sort = match ($input['urut'] ?? null) {
            'jumlah' => 'qty',
            'untung' => 'profit',
            default => 'revenue',
        };
        $limit = max(1, min(20, (int) ($input['batas'] ?? 10)));

        return $this->period($f) + [
            'barang' => array_map(fn ($r) => [
                'nama' => $r['name'],
                'kategori' => $r['category'],
                'jumlah' => $r['qty'],
                'satuan' => $r['unit'],
                'penjualan' => $r['revenue'],
                'untung' => $r['profit'],
            ], $this->reports->products($f, $limit, $sort)),
        ];
    }

    private function payments(array $input): array
    {
        $f = $this->filter($input, 0);

        return $this->period($f) + [
            'cara_bayar' => array_map(fn ($r) => ['nama' => $r['name'], 'nota' => $r['transactions'], 'bersih' => $r['net'], 'pengembalian' => $r['refunds']], $this->reports->payments($f)),
        ];
    }

    private function cashiers(array $input): array
    {
        $f = $this->filter($input, 0);

        return $this->period($f) + [
            'kasir' => array_map(fn ($r) => ['nama' => $r['name'], 'nota' => $r['transactions'], 'penjualan' => $r['total'], 'rata_rata' => $r['average']], $this->reports->cashiers($f)),
        ];
    }

    private function hours(array $input): array
    {
        $f = $this->filter($input, 6);
        $rows = array_values(array_filter($this->reports->byHour($f), fn ($r) => $r['transactions'] > 0));

        return $this->period($f) + [
            'per_jam' => array_map(fn ($r) => ['jam' => $r['label'].':00', 'nota' => $r['transactions'], 'penjualan' => $r['total']], $rows),
        ];
    }

    private function expenses(array $input): array
    {
        $f = $this->filter($input, null);
        $rows = $this->reports->expenseCategories($f);

        return $this->period($f) + [
            'total' => array_sum(array_column($rows, 'total')),
            'kategori' => array_map(fn ($r) => ['nama' => $r['name'], 'total' => $r['total']], $rows),
        ];
    }

    private function lowStock(array $input): array
    {
        $outlets = $this->outletIds($input['outlet'] ?? null) ?: Outlet::query()->orderBy('name')->pluck('id')->all();
        $names = Outlet::query()->whereIn('id', $outlets)->pluck('name', 'id');

        return [
            'outlet' => collect($outlets)->map(function (int $id) use ($names) {
                $items = $this->restock->lowStock($id);

                return [
                    'nama' => $names[$id] ?? '-',
                    'jumlah_barang' => $items->count(),
                    'barang' => $items->take(25)->map(fn ($p) => [
                        'nama' => $p->name,
                        'sisa' => (float) ($p->stocks->first()?->qty ?? 0),
                        'minimum' => (float) $p->min_stock,
                        'satuan' => $p->unit?->name,
                    ])->values()->all(),
                ];
            })->all(),
        ];
    }

    private function receivables(): array
    {
        $today = $this->today()->toDateString();

        $rows = Receivable::query()
            ->where('status', 'open')
            ->selectRaw('customer_id, SUM(amount - paid_amount) as sisa, SUM(CASE WHEN due_date IS NULL OR due_date <= ? THEN amount - paid_amount ELSE 0 END) as jatuh_tempo, MIN(created_at) as tertua', [$today])
            ->groupBy('customer_id')
            ->orderByDesc('sisa')
            ->get();

        $customers = Customer::query()->whereIn('id', $rows->pluck('customer_id'))->get(['id', 'name', 'deposit_balance'])->keyBy('id');

        return [
            'total_piutang' => (int) $rows->sum('sisa'),
            'sudah_jatuh_tempo' => (int) $rows->sum('jatuh_tempo'),
            'jumlah_pelanggan' => $rows->count(),
            'pelanggan' => $rows->take(15)->map(fn ($r) => [
                'nama' => $customers[$r->customer_id]->name ?? 'Tanpa nama',
                'sisa' => (int) $r->sisa,
                'jatuh_tempo' => (int) $r->jatuh_tempo,
                'titip_uang' => (int) ($customers[$r->customer_id]->deposit_balance ?? 0),
                'utang_sejak' => $r->tertua ? Carbon::parse($r->tertua)->toDateString() : null,
            ])->values()->all(),
        ];
    }

    /** @param int|null $defaultDays null = awal bulan ini sampai hari ini */
    private function filter(array $input, ?int $defaultDays): ReportFilter
    {
        $today = $this->today();
        $to = $this->date($input['sampai'] ?? null) ?? $today->copy();
        $from = $this->date($input['dari'] ?? null)
            ?? ($defaultDays === null ? $to->copy()->startOfMonth() : $to->copy()->subDays($defaultDays));

        if ($to->gt($today)) {
            $to = $today->copy();
        }
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }
        if ($from->diffInDays($to) >= self::MAX_RANGE_DAYS) {
            $from = $to->copy()->subDays(self::MAX_RANGE_DAYS - 1);
        }

        return ReportFilter::make($from->toDateString(), $to->toDateString(), $this->timezone(), $this->outletIds($input['outlet'] ?? null));
    }

    private function outletIds(?string $name): array
    {
        $name = trim((string) $name);
        if ($name === '') {
            return [];
        }

        $ids = Outlet::query()->where('name', 'like', '%'.addcslashes($name, '%_\\').'%')->pluck('id')->all();
        if ($ids === []) {
            throw new InvalidArgumentException("Outlet \"{$name}\" tidak ditemukan. Panggil info_toko untuk melihat nama outlet.");
        }

        return $ids;
    }

    private function period(ReportFilter $f): array
    {
        return ['periode' => $f->from->toDateString().' s/d '.$f->to->toDateString()];
    }

    private function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value, $this->timezone())->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function today(): Carbon
    {
        return Carbon::now($this->timezone())->startOfDay();
    }

    private function timezone(): string
    {
        return $this->context->get()->timezone ?: config('app.timezone');
    }
}
