<?php

namespace App\Modules\Report\Services;

use App\Modules\Expense\Models\Expense;
use App\Modules\Pos\Models\Refund;
use App\Modules\Pos\Models\Sale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Semua angka laporan dihitung di sini (dipakai halaman Laporan, Beranda, dan ekspor Excel).
 *
 * Istilah (bahasa sehari-hari di layar → arti):
 *   - Uang masuk (total_collected): uang yang benar-benar diterima = total nota − pengembalian
 *   - Penjualan bersih (net_sales): tanpa pajak, biaya layanan, pembulatan, dan pengembalian
 *   - Penjualan kotor (gross_sales): penjualan bersih sebelum diskon & pengembalian
 *   - Modal barang terjual (cogs) dan untung kotor (gross_profit = penjualan bersih − modal)
 *   - Untung bersih (net_profit = untung kotor − pengeluaran)
 *
 * Hanya nota berstatus "completed" yang dihitung (bukan batal / pesanan terbuka).
 */
class ReportService
{
    public function summary(ReportFilter $f): array
    {
        $sales = $this->sales($f)->selectRaw('
            COUNT(*) as trx,
            COALESCE(SUM(total), 0) as total,
            COALESCE(SUM(tax_amount), 0) as tax,
            COALESCE(SUM(service_charge_amount), 0) as service,
            COALESCE(SUM(rounding_amount), 0) as rounding,
            COALESCE(SUM(discount_amount), 0) as order_discount,
            COALESCE(SUM(due_amount), 0) as unpaid
        ')->first();

        $items = DB::table('sale_items')
            ->joinSub($this->sales($f)->select('id'), 's', 's.id', '=', 'sale_items.sale_id')
            ->selectRaw('COALESCE(SUM(sale_items.discount_amount), 0) as item_discount, COALESCE(SUM(sale_items.cost_amount), 0) as cogs')
            ->first();

        $refund = $this->refunds($f);
        $expenses = (int) $this->expenses($f)->sum('amount');

        $trx = (int) $sales->trx;
        $discounts = (int) $sales->order_discount + (int) $items->item_discount;
        $netBeforeRefund = (int) $sales->total - (int) $sales->tax - (int) $sales->service - (int) $sales->rounding;
        $netSales = $netBeforeRefund - $refund['amount'];
        $cogs = (int) $items->cogs - $refund['cogs'];
        $grossProfit = $netSales - $cogs;

        return [
            'transactions' => $trx,
            'total_collected' => (int) $sales->total - $refund['amount'],
            'gross_sales' => $netBeforeRefund + $discounts,
            'discounts' => $discounts,
            'refunds' => $refund['amount'],
            'refund_count' => $refund['count'],
            'net_sales' => $netSales,
            'tax' => (int) $sales->tax,
            'service' => (int) $sales->service,
            'rounding' => (int) $sales->rounding,
            'unpaid' => (int) $sales->unpaid,
            'cogs' => $cogs,
            'gross_profit' => $grossProfit,
            'margin' => $netSales > 0 ? round($grossProfit / $netSales * 100, 1) : 0.0,
            'expenses' => $expenses,
            'net_profit' => $grossProfit - $expenses,
            'average' => $trx ? intdiv((int) $sales->total, $trx) : 0,
        ];
    }

    /** Ringkasan + perbandingan dengan periode sebelumnya (persen naik/turun). */
    public function summaryWithComparison(ReportFilter $f): array
    {
        $now = $this->summary($f);
        $before = $this->summary($f->previous());

        $change = fn (string $key) => $before[$key] ? round(($now[$key] - $before[$key]) / abs($before[$key]) * 100, 1) : null;

        return [
            ...$now,
            'previous' => $before,
            'change' => [
                'total_collected' => $change('total_collected'),
                'transactions' => $change('transactions'),
                'gross_profit' => $change('gross_profit'),
                'net_profit' => $change('net_profit'),
            ],
        ];
    }

    /** Penjualan per hari (selalu lengkap, hari tanpa penjualan = 0). Untuk rentang > 62 hari: per bulan. */
    public function series(ReportFilter $f): array
    {
        $monthly = $f->days() > 62;
        $expr = $monthly ? $this->localMonthExpr('completed_at', $f) : $this->localDateExpr('completed_at', $f);

        $rows = $this->sales($f)
            ->selectRaw("{$expr} as period, COUNT(*) as trx, COALESCE(SUM(total), 0) as total")
            ->groupBy('period')
            ->get()
            ->keyBy('period');

        $points = [];
        $cursor = $f->from->copy()->startOfDay();
        while ($cursor <= $f->to) {
            $key = $monthly ? $cursor->format('Y-m') : $cursor->toDateString();
            $points[] = [
                'key' => $key,
                'label' => $monthly ? $cursor->locale('id')->translatedFormat('M y') : $cursor->locale('id')->translatedFormat($f->days() > 14 ? 'j/n' : 'D j'),
                'total' => (int) ($rows[$key]->total ?? 0),
                'transactions' => (int) ($rows[$key]->trx ?? 0),
            ];
            $monthly ? $cursor->addMonthNoOverflow()->startOfMonth() : $cursor->addDay();
        }

        return $points;
    }

    /** Jam ramai: jumlah nota per jam (0–23). */
    public function byHour(ReportFilter $f): array
    {
        $expr = $this->localHourExpr('completed_at', $f);
        $rows = $this->sales($f)->selectRaw("{$expr} as hr, COUNT(*) as trx, COALESCE(SUM(total), 0) as total")->groupBy('hr')->get()->keyBy(fn ($r) => (int) $r->hr);

        return collect(range(0, 23))->map(fn ($h) => ['hour' => $h, 'label' => sprintf('%02d', $h), 'transactions' => (int) ($rows[$h]->trx ?? 0), 'total' => (int) ($rows[$h]->total ?? 0)])->all();
    }

    /** Barang terlaris: jumlah (satuan dasar), penjualan, modal, untung. */
    public function products(ReportFilter $f, int $limit = 50, string $sort = 'revenue'): array
    {
        $refunded = $this->refundedPerItem($f);

        $rows = DB::table('sale_items')
            ->joinSub($this->sales($f)->select('id'), 's', 's.id', '=', 'sale_items.sale_id')
            ->leftJoin('products', 'products.id', '=', 'sale_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('units', 'units.id', '=', 'products.base_unit_id')
            ->selectRaw('sale_items.product_id, MAX(COALESCE(products.name, sale_items.name)) as name, MAX(categories.name) as category, MAX(units.name) as unit,
                SUM(sale_items.qty * sale_items.conversion_qty) as qty, SUM(sale_items.subtotal) as revenue, SUM(sale_items.cost_amount) as cost, COUNT(DISTINCT sale_items.sale_id) as trx')
            ->groupBy('sale_items.product_id')
            ->get()
            ->map(function ($r) use ($refunded) {
                $back = $refunded[$r->product_id] ?? ['amount' => 0, 'cost' => 0];
                $revenue = (int) $r->revenue - $back['amount'];
                $cost = (int) $r->cost - $back['cost'];

                return [
                    'product_id' => $r->product_id,
                    'name' => $r->name,
                    'category' => $r->category ?? 'Tanpa kategori',
                    'unit' => $r->unit,
                    'qty' => round((float) $r->qty, 3),
                    'revenue' => $revenue,
                    'cost' => $cost,
                    'profit' => $revenue - $cost,
                    'transactions' => (int) $r->trx,
                ];
            });

        return $rows->sortByDesc($sort === 'qty' ? 'qty' : ($sort === 'profit' ? 'profit' : 'revenue'))->take($limit)->values()->all();
    }

    public function categories(ReportFilter $f): array
    {
        return collect($this->products($f, PHP_INT_MAX))
            ->groupBy('category')
            ->map(fn ($rows, $name) => ['name' => $name, 'revenue' => $rows->sum('revenue'), 'profit' => $rows->sum('profit'), 'qty' => round($rows->sum('qty'), 3)])
            ->sortByDesc('revenue')->values()->all();
    }

    /** Per cara bayar. Pengembalian dikurangkan dari cara bayar yang dipakai mengembalikan. */
    public function payments(ReportFilter $f): array
    {
        $paid = DB::table('sale_payments')
            ->joinSub($this->sales($f)->select('id'), 's', 's.id', '=', 'sale_payments.sale_id')
            ->selectRaw('sale_payments.method_name as name, sale_payments.method_type as type, COUNT(*) as trx, SUM(sale_payments.amount) as total')
            ->groupBy('sale_payments.method_name', 'sale_payments.method_type')
            ->get();

        $refunds = $this->refundQuery($f)->selectRaw('method_type as type, SUM(amount) as total')->groupBy('method_type')->pluck('total', 'type');

        return $paid->map(fn ($r) => [
            'name' => $r->name,
            'type' => $r->type,
            'transactions' => (int) $r->trx,
            'total' => (int) $r->total,
            'refunds' => (int) ($refunds[$r->type] ?? 0),
            'net' => (int) $r->total - (int) ($refunds[$r->type] ?? 0),
        ])->sortByDesc('total')->values()->all();
    }

    /** Per kasir (yang menerima pembayaran). */
    public function cashiers(ReportFilter $f): array
    {
        return $this->sales($f)
            ->leftJoin('users', 'users.id', '=', 'sales.cashier_id')
            ->selectRaw('sales.cashier_id, MAX(users.name) as name, COUNT(*) as trx, SUM(sales.total) as total')
            ->groupBy('sales.cashier_id')
            ->get()
            ->map(fn ($r) => ['user_id' => $r->cashier_id, 'name' => $r->name ?? '-', 'transactions' => (int) $r->trx, 'total' => (int) $r->total, 'average' => $r->trx ? intdiv((int) $r->total, (int) $r->trx) : 0])
            ->sortByDesc('total')->values()->all();
    }

    /** Per karyawan yang mengerjakan (kapster, terapis, trainer) + komisinya. */
    public function staff(ReportFilter $f): array
    {
        $commissions = DB::table('staff_commissions')
            ->joinSub($this->sales($f)->select('id'), 's', 's.id', '=', 'staff_commissions.sale_id')
            ->where('staff_commissions.status', '!=', 'canceled')
            ->selectRaw('staff_commissions.user_id, SUM(staff_commissions.amount) as total')
            ->groupBy('staff_commissions.user_id')
            ->pluck('total', 'user_id');

        return DB::table('sale_items')
            ->joinSub($this->sales($f)->select('id'), 's', 's.id', '=', 'sale_items.sale_id')
            ->join('users', 'users.id', '=', 'sale_items.staff_id')
            ->selectRaw('sale_items.staff_id, MAX(users.name) as name, COUNT(*) as jobs, SUM(sale_items.subtotal) as revenue')
            ->groupBy('sale_items.staff_id')
            ->get()
            ->map(fn ($r) => ['user_id' => $r->staff_id, 'name' => $r->name, 'jobs' => (int) $r->jobs, 'revenue' => (int) $r->revenue, 'commission' => (int) ($commissions[$r->staff_id] ?? 0)])
            ->sortByDesc('revenue')->values()->all();
    }

    /** Perbandingan antar outlet (untuk pemilik dengan banyak cabang). */
    public function outlets(ReportFilter $f): array
    {
        $expenses = $this->expenses($f)->selectRaw('outlet_id, SUM(amount) as total')->groupBy('outlet_id')->pluck('total', 'outlet_id');

        return $this->sales($f)
            ->join('outlets', 'outlets.id', '=', 'sales.outlet_id')
            ->selectRaw('sales.outlet_id, MAX(outlets.name) as name, COUNT(*) as trx, SUM(sales.total) as total')
            ->groupBy('sales.outlet_id')
            ->get()
            ->map(fn ($r) => ['outlet_id' => $r->outlet_id, 'name' => $r->name, 'transactions' => (int) $r->trx, 'total' => (int) $r->total, 'expenses' => (int) ($expenses[$r->outlet_id] ?? 0)])
            ->sortByDesc('total')->values()->all();
    }

    public function expenseCategories(ReportFilter $f): array
    {
        return $this->expenses($f)
            ->leftJoin('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->selectRaw("COALESCE(MAX(expense_categories.name), 'Lain-lain') as name, SUM(expenses.amount) as total")
            ->groupBy('expenses.expense_category_id')
            ->get()
            ->map(fn ($r) => ['name' => $r->name, 'total' => (int) $r->total])
            ->sortByDesc('total')->values()->all();
    }

    // ---------------------------------------------------------------------

    /** Nota selesai dalam rentang & outlet. */
    private function sales(ReportFilter $f): Builder
    {
        return Sale::query()
            ->where('sales.status', 'completed')
            ->whereBetween('sales.completed_at', $f->utcRange())
            ->when($f->outletIds, fn ($q) => $q->whereIn('sales.outlet_id', $f->outletIds));
    }

    private function refundQuery(ReportFilter $f): Builder
    {
        return Refund::query()
            ->whereBetween('refunds.created_at', $f->utcRange())
            ->when($f->outletIds, fn ($q) => $q->whereHas('sale', fn ($s) => $s->whereIn('outlet_id', $f->outletIds)));
    }

    /** Pengembalian barang dihitung di hari pengembaliannya (bukan hari jualnya), seperti kas. */
    private function refunds(ReportFilter $f): array
    {
        $amount = (int) $this->refundQuery($f)->sum('amount');
        $count = $amount ? $this->refundQuery($f)->count() : 0;

        $cogs = $amount ? (int) round((float) DB::table('refund_items')
            ->joinSub($this->refundQuery($f)->select('id'), 'r', 'r.id', '=', 'refund_items.refund_id')
            ->join('sale_items', 'sale_items.id', '=', 'refund_items.sale_item_id')
            ->selectRaw('COALESCE(SUM(CASE WHEN sale_items.qty > 0 THEN refund_items.qty * 1.0 * sale_items.cost_amount / sale_items.qty ELSE 0 END), 0) as cogs')
            ->value('cogs')) : 0;

        return ['amount' => $amount, 'count' => $count, 'cogs' => $cogs];
    }

    /** @return array<int, array{amount:int, cost:int}> per product_id */
    private function refundedPerItem(ReportFilter $f): array
    {
        return DB::table('refund_items')
            ->joinSub($this->refundQuery($f)->select('id'), 'r', 'r.id', '=', 'refund_items.refund_id')
            ->join('sale_items', 'sale_items.id', '=', 'refund_items.sale_item_id')
            ->selectRaw('sale_items.product_id, SUM(refund_items.amount) as amount, SUM(CASE WHEN sale_items.qty > 0 THEN refund_items.qty * 1.0 * sale_items.cost_amount / sale_items.qty ELSE 0 END) as cost')
            ->groupBy('sale_items.product_id')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->product_id => ['amount' => (int) $r->amount, 'cost' => (int) round((float) $r->cost)]])
            ->all();
    }

    private function expenses(ReportFilter $f): Builder
    {
        return Expense::query()
            ->whereDate('expenses.spent_on', '>=', $f->from->toDateString())
            ->whereDate('expenses.spent_on', '<=', $f->to->toDateString())
            ->when($f->outletIds, fn ($q) => $q->whereIn('expenses.outlet_id', $f->outletIds));
    }

    private function localDateExpr(string $column, ReportFilter $f): string
    {
        $h = $f->offsetHours();

        return DB::getDriverName() === 'sqlite'
            ? "date({$column}, '{$this->signed($h)} hours')"
            : "DATE(DATE_ADD({$column}, INTERVAL {$h} HOUR))";
    }

    private function localMonthExpr(string $column, ReportFilter $f): string
    {
        $h = $f->offsetHours();

        return DB::getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$column}, '{$this->signed($h)} hours')"
            : "DATE_FORMAT(DATE_ADD({$column}, INTERVAL {$h} HOUR), '%Y-%m')";
    }

    private function localHourExpr(string $column, ReportFilter $f): string
    {
        $h = $f->offsetHours();

        return DB::getDriverName() === 'sqlite'
            ? "CAST(strftime('%H', {$column}, '{$this->signed($h)} hours') AS INTEGER)"
            : "HOUR(DATE_ADD({$column}, INTERVAL {$h} HOUR))";
    }

    private function signed(int $hours): string
    {
        return ($hours >= 0 ? '+' : '').$hours;
    }

    /** Pilihan periode cepat untuk layar. */
    public static function presets(string $timezone): array
    {
        $today = Carbon::now($timezone);

        return [
            'today' => ['label' => 'Hari ini', 'from' => $today->toDateString(), 'to' => $today->toDateString()],
            'yesterday' => ['label' => 'Kemarin', 'from' => $today->copy()->subDay()->toDateString(), 'to' => $today->copy()->subDay()->toDateString()],
            '7days' => ['label' => '7 hari', 'from' => $today->copy()->subDays(6)->toDateString(), 'to' => $today->toDateString()],
            'month' => ['label' => 'Bulan ini', 'from' => $today->copy()->startOfMonth()->toDateString(), 'to' => $today->toDateString()],
            'last_month' => ['label' => 'Bulan lalu', 'from' => $today->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(), 'to' => $today->copy()->subMonthNoOverflow()->endOfMonth()->toDateString()],
        ];
    }
}
