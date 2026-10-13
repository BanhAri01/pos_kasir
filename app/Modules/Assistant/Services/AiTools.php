<?php

namespace App\Modules\Assistant\Services;

use App\Models\Tenant;
use App\Modules\Catalog\Models\Product;
use App\Modules\Expense\Models\Expense;
use App\Modules\Operations\Models\Receivable;
use App\Modules\Pos\Models\Sale;
use App\Modules\Pos\Models\SaleItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AiTools
{
    /**
     * Definisi deklarasi tool untuk Claude Tool Use.
     */
    public static function getToolDefinitions(): array
    {
        return [
            [
                'name' => 'omset_penjualan',
                'description' => 'Melihat ringkasan omset penjualan, jumlah transaksi, rata-rata belanja, dan perbandingannya.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'period' => [
                            'type' => 'string',
                            'enum' => ['today', 'yesterday', 'this_week', 'this_month', 'last_month', 'last_7_days', 'last_30_days'],
                            'description' => 'Periode waktu yang ingin dianalisis (default: today)',
                        ],
                        'outlet_id' => [
                            'type' => 'integer',
                            'description' => 'ID cabang/outlet spesifik jika ingin difilter',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'produk_terlaris',
                'description' => 'Melihat daftar produk/menu paling laris berdasarkan jumlah terjual (qty) dan nilai penjualan.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'period' => [
                            'type' => 'string',
                            'enum' => ['today', 'yesterday', 'this_week', 'this_month', 'last_month', 'last_7_days', 'last_30_days'],
                            'description' => 'Periode waktu (default: this_month)',
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Jumlah produk teratas (default: 5, maks: 20)',
                        ],
                        'outlet_id' => [
                            'type' => 'integer',
                            'description' => 'ID cabang jika ingin difilter',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'stok_menipis',
                'description' => 'Mengecek produk yang stoknya habis atau menipis di bawah batas minimum.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Jumlah produk yang ditampilkan (default: 10)',
                        ],
                        'outlet_id' => [
                            'type' => 'integer',
                            'description' => 'ID outlet spesifik jika ada',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'performa_kasir',
                'description' => 'Menganalisis performa penjualan kasir atau shift kerja (siapa yang melayani paling banyak).',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'period' => [
                            'type' => 'string',
                            'enum' => ['today', 'yesterday', 'this_week', 'this_month', 'last_month'],
                            'description' => 'Periode waktu analisis performa (default: today)',
                        ],
                        'outlet_id' => [
                            'type' => 'integer',
                            'description' => 'ID outlet',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'laba_rugi',
                'description' => 'Menghitung estimasi laba kotor (omset dikurangi HPP modal) dan laba bersih (setelah beban operasional).',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'period' => [
                            'type' => 'string',
                            'enum' => ['today', 'yesterday', 'this_week', 'this_month', 'last_month'],
                            'description' => 'Periode waktu laba rugi (default: this_month)',
                        ],
                        'outlet_id' => [
                            'type' => 'integer',
                            'description' => 'ID outlet',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'kasbon_pelanggan',
                'description' => 'Melihat daftar pelanggan yang memiliki utang/kasbon belum lunas beserta total piutang usaha.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Jumlah pelanggan teratas (default: 10)',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Eksekusi tool berdasarkan nama.
     */
    public function execute(string $toolName, array $args, Tenant $tenant): array
    {
        return app(\App\Core\Tenancy\TenantContext::class)->runAs($tenant, function () use ($toolName, $args, $tenant) {
            return match ($toolName) {
                'omset_penjualan' => $this->omsetPenjualan($tenant, $args),
                'produk_terlaris' => $this->produkTerlaris($tenant, $args),
                'stok_menipis' => $this->stokMenipis($tenant, $args),
                'performa_kasir' => $this->performaKasir($tenant, $args),
                'laba_rugi' => $this->labaRugi($tenant, $args),
                'kasbon_pelanggan' => $this->kasbonPelanggan($tenant, $args),
                default => ['error' => "Tool {$toolName} tidak ditemukan."],
            };
        });
    }

    /**
     * 1. Omset Penjualan
     */
    public function omsetPenjualan(Tenant $tenant, array $args = []): array
    {
        $period = $args['period'] ?? 'today';
        [$start, $end, $label, $prevStart, $prevEnd] = $this->resolveDateRange($period);

        $query = Sale::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'completed')
            ->whereBetween(DB::raw('COALESCE(completed_at, created_at)'), [$start, $end]);

        if (! empty($args['outlet_id'])) {
            $query->where('outlet_id', (int) $args['outlet_id']);
        }

        $totalOmset = (int) $query->sum('total');
        $totalTransaksi = (int) $query->count();
        $totalDiskon = (int) $query->sum('discount_amount');
        $totalKasbon = (int) $query->sum('due_amount');
        $avgBasket = $totalTransaksi > 0 ? (int) round($totalOmset / $totalTransaksi) : 0;

        // Periode sebelumnya untuk perbandingan
        $prevQuery = Sale::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'completed')
            ->whereBetween(DB::raw('COALESCE(completed_at, created_at)'), [$prevStart, $prevEnd]);

        if (! empty($args['outlet_id'])) {
            $prevQuery->where('outlet_id', (int) $args['outlet_id']);
        }

        $prevOmset = (int) $prevQuery->sum('total');
        $growth = $prevOmset > 0 ? round((($totalOmset - $prevOmset) / $prevOmset) * 100, 1) : null;

        return [
            'periode' => $label,
            'rentang_tanggal' => [$start->toDateTimeString(), $end->toDateTimeString()],
            'total_omset' => $totalOmset,
            'total_transaksi' => $totalTransaksi,
            'rata_rata_per_transaksi' => $avgBasket,
            'total_diskon' => $totalDiskon,
            'total_kasbon_baru' => $totalKasbon,
            'omset_periode_sebelumnya' => $prevOmset,
            'pertumbuhan_persen' => $growth,
        ];
    }

    /**
     * 2. Produk Terlaris
     */
    public function produkTerlaris(Tenant $tenant, array $args = []): array
    {
        $period = $args['period'] ?? 'this_month';
        $limit = min(20, max(1, (int) ($args['limit'] ?? 5)));
        [$start, $end, $label] = $this->resolveDateRange($period);

        $query = SaleItem::withoutGlobalScopes()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sale_items.tenant_id', $tenant->id)
            ->where('sales.status', 'completed')
            ->whereBetween(DB::raw('COALESCE(sales.completed_at, sales.created_at)'), [$start, $end]);

        if (! empty($args['outlet_id'])) {
            $query->where('sales.outlet_id', (int) $args['outlet_id']);
        }

        $items = $query->groupBy('sale_items.product_id', 'sale_items.name')
            ->select(
                'sale_items.name',
                DB::raw('SUM(sale_items.qty) as total_qty'),
                DB::raw('SUM(sale_items.subtotal) as total_omset')
            )
            ->orderByDesc('total_qty')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'nama_produk' => $row->name,
                'terjual_qty' => (float) $row->total_qty,
                'total_penjualan' => (int) $row->total_omset,
            ])
            ->values()
            ->all();

        return [
            'periode' => $label,
            'jumlah_produk' => count($items),
            'produk' => $items,
        ];
    }

    /**
     * 3. Stok Menipis
     */
    public function stokMenipis(Tenant $tenant, array $args = []): array
    {
        $limit = min(30, max(1, (int) ($args['limit'] ?? 10)));

        $query = Product::withoutGlobalScopes()
            ->leftJoin('stocks', 'stocks.product_id', '=', 'products.id')
            ->leftJoin('units', 'units.id', '=', 'products.base_unit_id')
            ->where('products.tenant_id', $tenant->id)
            ->where('products.track_stock', true)
            ->where('products.is_active', true)
            ->whereNull('products.deleted_at');

        if (! empty($args['outlet_id'])) {
            $query->where('stocks.outlet_id', (int) $args['outlet_id']);
        }

        $items = $query->select(
            'products.id',
            'products.name',
            'products.min_stock',
            'units.symbol as unit_symbol',
            DB::raw('COALESCE(stocks.qty, 0) as sisa_stok')
        )
            ->where(function ($q) {
                $q->whereRaw('COALESCE(stocks.qty, 0) <= COALESCE(products.min_stock, 5)')
                    ->orWhereRaw('COALESCE(stocks.qty, 0) <= 0');
            })
            ->orderBy('sisa_stok', 'asc')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'nama' => $row->name,
                'sisa_stok' => (float) $row->sisa_stok,
                'satuan' => $row->unit_symbol ?? 'pcs',
                'batas_minimum' => (float) ($row->min_stock ?? 5),
                'status' => (float) $row->sisa_stok <= 0 ? 'HABIS' : 'MENIPIS',
            ])
            ->values()
            ->all();

        return [
            'total_ditemukan' => count($items),
            'stok_kritis' => $items,
        ];
    }

    /**
     * 4. Performa Kasir
     */
    public function performaKasir(Tenant $tenant, array $args = []): array
    {
        $period = $args['period'] ?? 'today';
        [$start, $end, $label] = $this->resolveDateRange($period);

        $query = Sale::withoutGlobalScopes()
            ->join('users', 'users.id', '=', 'sales.cashier_id')
            ->where('sales.tenant_id', $tenant->id)
            ->where('sales.status', 'completed')
            ->whereBetween(DB::raw('COALESCE(sales.completed_at, sales.created_at)'), [$start, $end]);

        if (! empty($args['outlet_id'])) {
            $query->where('sales.outlet_id', (int) $args['outlet_id']);
        }

        $data = $query->groupBy('sales.cashier_id', 'users.name')
            ->select(
                'users.name as kasir',
                DB::raw('COUNT(sales.id) as total_transaksi'),
                DB::raw('SUM(sales.total) as total_omset')
            )
            ->orderByDesc('total_omset')
            ->get()
            ->map(fn ($row) => [
                'nama_kasir' => $row->kasir,
                'total_transaksi' => (int) $row->total_transaksi,
                'total_omset' => (int) $row->total_omset,
            ])
            ->values()
            ->all();

        return [
            'periode' => $label,
            'kasir' => $data,
        ];
    }

    /**
     * 5. Laba Rugi
     */
    public function labaRugi(Tenant $tenant, array $args = []): array
    {
        $period = $args['period'] ?? 'this_month';
        [$start, $end, $label] = $this->resolveDateRange($period);

        // Omset penjualan
        $salesQuery = Sale::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'completed')
            ->whereBetween(DB::raw('COALESCE(completed_at, created_at)'), [$start, $end]);

        if (! empty($args['outlet_id'])) {
            $salesQuery->where('outlet_id', (int) $args['outlet_id']);
        }

        $omset = (int) $salesQuery->sum('total');

        // Modal / HPP
        $itemQuery = SaleItem::withoutGlobalScopes()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sale_items.tenant_id', $tenant->id)
            ->where('sales.status', 'completed')
            ->whereBetween(DB::raw('COALESCE(sales.completed_at, sales.created_at)'), [$start, $end]);

        if (! empty($args['outlet_id'])) {
            $itemQuery->where('sales.outlet_id', (int) $args['outlet_id']);
        }

        $hpp = (int) $itemQuery->sum('sale_items.cost_amount');
        $labaKotor = $omset - $hpp;
        $grossMargin = $omset > 0 ? round(($labaKotor / $omset) * 100, 1) : 0;

        // Beban operasional
        $expenseQuery = Expense::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereDate('spent_on', '>=', $start)
            ->whereDate('spent_on', '<=', $end);

        if (! empty($args['outlet_id'])) {
            $expenseQuery->where('outlet_id', (int) $args['outlet_id']);
        }

        $pengeluaran = (int) $expenseQuery->sum('amount');
        $labaBersih = $labaKotor - $pengeluaran;
        $netMargin = $omset > 0 ? round(($labaBersih / $omset) * 100, 1) : 0;

        return [
            'periode' => $label,
            'total_omset' => $omset,
            'total_hpp_modal' => $hpp,
            'laba_kotor' => $labaKotor,
            'margin_kotor_persen' => $grossMargin,
            'beban_pengeluaran' => $pengeluaran,
            'laba_bersih' => $labaBersih,
            'margin_bersih_persen' => $netMargin,
        ];
    }

    /**
     * 6. Kasbon Pelanggan
     */
    public function kasbonPelanggan(Tenant $tenant, array $args = []): array
    {
        $limit = min(30, max(1, (int) ($args['limit'] ?? 10)));

        $totalPiutang = (int) Receivable::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'open')
            ->selectRaw('SUM(amount - paid_amount) as sisa')
            ->value('sisa');

        $pelanggan = Receivable::withoutGlobalScopes()
            ->join('customers', 'customers.id', '=', 'receivables.customer_id')
            ->where('receivables.tenant_id', $tenant->id)
            ->where('receivables.status', 'open')
            ->groupBy('customers.id', 'customers.name', 'customers.phone')
            ->select(
                'customers.name',
                'customers.phone',
                DB::raw('SUM(receivables.amount - receivables.paid_amount) as sisa_utang'),
                DB::raw('COUNT(receivables.id) as total_nota'),
                DB::raw('MIN(receivables.due_date) as jatuh_tempo_terdekat')
            )
            ->orderByDesc('sisa_utang')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'nama' => $row->name,
                'telepon' => $row->phone ?? '-',
                'sisa_utang' => (int) $row->sisa_utang,
                'total_nota' => (int) $row->total_nota,
                'jatuh_tempo' => $row->jatuh_tempo_terdekat,
            ])
            ->values()
            ->all();

        return [
            'total_seluruh_piutang' => $totalPiutang,
            'jumlah_pelanggan_tercatat' => count($pelanggan),
            'pelanggan' => $pelanggan,
        ];
    }

    private function resolveDateRange(string $period): array
    {
        $now = Carbon::now();

        return match ($period) {
            'yesterday' => [
                Carbon::yesterday()->startOfDay(),
                Carbon::yesterday()->endOfDay(),
                'Kemarin',
                Carbon::yesterday()->subDay()->startOfDay(),
                Carbon::yesterday()->subDay()->endOfDay(),
            ],
            'this_week' => [
                $now->copy()->startOfWeek(),
                $now->copy()->endOfWeek(),
                'Minggu Ini',
                $now->copy()->subWeek()->startOfWeek(),
                $now->copy()->subWeek()->endOfWeek(),
            ],
            'last_7_days' => [
                $now->copy()->subDays(6)->startOfDay(),
                $now->copy()->endOfDay(),
                '7 Hari Terakhir',
                $now->copy()->subDays(13)->startOfDay(),
                $now->copy()->subDays(7)->endOfDay(),
            ],
            'this_month' => [
                $now->copy()->startOfMonth(),
                $now->copy()->endOfMonth(),
                'Bulan Ini',
                $now->copy()->subMonth()->startOfMonth(),
                $now->copy()->subMonth()->endOfMonth(),
            ],
            'last_month' => [
                $now->copy()->subMonth()->startOfMonth(),
                $now->copy()->subMonth()->endOfMonth(),
                'Bulan Lalu',
                $now->copy()->subMonths(2)->startOfMonth(),
                $now->copy()->subMonths(2)->endOfMonth(),
            ],
            'last_30_days' => [
                $now->copy()->subDays(29)->startOfDay(),
                $now->copy()->endOfDay(),
                '30 Hari Terakhir',
                $now->copy()->subDays(59)->startOfDay(),
                $now->copy()->subDays(30)->endOfDay(),
            ],
            default => [ // today
                Carbon::today()->startOfDay(),
                Carbon::today()->endOfDay(),
                'Hari Ini',
                Carbon::yesterday()->startOfDay(),
                Carbon::yesterday()->endOfDay(),
            ],
        };
    }
}
