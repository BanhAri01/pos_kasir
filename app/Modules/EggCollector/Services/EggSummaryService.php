<?php

namespace App\Modules\EggCollector\Services;

use App\Core\Support\Qty;
use App\Modules\EggCollector\Models\EggProduct;
use App\Modules\EggCollector\Models\EggSorting;
use App\Modules\Expense\Models\Expense;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Operations\Models\Receivable;
use App\Modules\Operations\Models\ReceivablePayment;
use App\Modules\Pos\Models\Sale;
use App\Modules\Purchasing\Models\Purchase;
use App\Modules\Purchasing\Models\PurchasePayment;
use App\Modules\Report\Services\ReportFilter;
use App\Modules\Report\Services\ReportService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Ringkasan uang pengepul telur: hari ini, minggu ini, bulan ini, dibanding periode yang sama sebelumnya.
 *
 * Untung bersih = penjualan bersih − modal telur terjual − pecah saat sortir − telur rusak/hilang − pengeluaran.
 * Uang masuk = dibayar tunai saat jual + cicilan kasbon/tempo yang diterima. Uang keluar = bayar peternak + pengeluaran.
 */
class EggSummaryService
{
    public function __construct(private ReportService $reports) {}

    public function overview(string $timezone, array $outletIds = []): array
    {
        $today = Carbon::now($timezone)->startOfDay();
        $weekStart = $today->copy()->startOfWeek(Carbon::MONDAY);
        $monthStart = $today->copy()->startOfMonth();
        $sameDayLastMonth = $monthStart->copy()->subMonthNoOverflow()->addDays($today->day - 1);

        $periods = [
            'today' => ['Hari ini', $today, $today, 'Kemarin', $today->copy()->subDay(), $today->copy()->subDay()],
            'week' => ['Minggu ini', $weekStart, $today, 'Minggu lalu', $weekStart->copy()->subWeek(), $today->copy()->subWeek()],
            'month' => ['Bulan ini', $monthStart, $today, 'Bulan lalu', $monthStart->copy()->subMonthNoOverflow(), $sameDayLastMonth->min($monthStart->copy()->subDay())],
        ];

        return [
            'periods' => collect($periods)->map(function (array $p) use ($timezone, $outletIds) {
                [$label, $from, $to, $prevLabel, $prevFrom, $prevTo] = $p;
                $now = $this->period(ReportFilter::make($from->toDateString(), $to->toDateString(), $timezone, $outletIds));
                $before = $this->period(ReportFilter::make($prevFrom->toDateString(), $prevTo->toDateString(), $timezone, $outletIds), false);

                return [
                    'label' => $label,
                    'from' => $from->toDateString(),
                    'to' => $to->toDateString(),
                    ...$now,
                    'previous' => ['label' => $prevLabel, ...$before],
                    'change' => collect(['sales', 'cash_in', 'net_profit'])
                        ->mapWithKeys(fn ($key) => [$key => $before[$key] ? round(($now[$key] - $before[$key]) / abs($before[$key]) * 100, 1) : null])
                        ->all(),
                ];
            })->all(),
            ...$this->debts($today->toDateString(), $outletIds),
        ];
    }

    /** Piutang pembeli & utang ke peternak yang belum lunas (keadaan saat ini, bukan per periode). */
    public function debts(string $today, array $outletIds = []): array
    {
        return [
            'receivables' => $this->receivables($outletIds),
            'payables' => $this->payables($today, $outletIds),
        ];
    }

    public function period(ReportFilter $f, bool $detail = true): array
    {
        $base = $this->reports->summary($f);
        $sortings = $this->sortings($f);
        $damaged = $this->damagedLoss($f);
        $expenses = $base['expenses'];
        $paidFarmers = (int) PurchasePayment::query()
            ->whereBetween('paid_at', $f->utcRange())
            ->when($f->outletIds, fn ($q) => $q->whereIn('purchase_id', Purchase::query()->whereIn('outlet_id', $f->outletIds)->select('id')))
            ->sum('amount');
        $collected = (int) ReceivablePayment::query()
            ->whereBetween('paid_at', $f->utcRange())
            ->when($f->outletIds, fn ($q) => $q->whereIn('receivable_id', Receivable::query()->whereIn('sale_id', Sale::query()->whereIn('outlet_id', $f->outletIds)->select('id'))->select('id')))
            ->sum('amount');

        $eggs = $this->eggSales($f);
        $result = [
            'sales' => $base['net_sales'],
            'cash_in' => $base['total_collected'] - $base['unpaid'] + $collected,
            'cash_out' => $paidFarmers + $expenses,
            'kasbon_collected' => $collected,
            'unpaid_sales' => $base['unpaid'],
            'paid_farmers' => $paidFarmers,
            'cogs' => $base['cogs'],
            'gross_profit' => $base['gross_profit'],
            'sorting_loss' => $sortings['shrinkage_cost'],
            'damaged_loss' => $damaged,
            'expenses' => $expenses,
            'net_profit' => $base['gross_profit'] - $sortings['shrinkage_cost'] - $damaged - $expenses,
            'kg_sold' => Qty::display((string) $eggs->sum('qty')),
            'transactions' => $base['transactions'],
        ];

        if (! $detail) {
            return $result;
        }

        return [
            ...$result,
            'kg_bought' => Qty::display($this->kgBought($f)),
            'sorting' => [
                'count' => $sortings['count'],
                'input_kg' => Qty::display($sortings['input_qty']),
                'shrinkage_kg' => Qty::display($sortings['shrinkage_qty']),
                'shrinkage_percent' => (float) $sortings['input_qty'] > 0 ? round((float) $sortings['shrinkage_qty'] / (float) $sortings['input_qty'] * 100, 1) : 0.0,
            ],
            'eggs' => $eggs->map(fn ($row) => [...$row, 'qty' => Qty::display((string) $row['qty'])])->values()->all(),
        ];
    }

    /** Penjualan per jenis telur (berat dalam satuan dasar, biasanya kg). */
    private function eggSales(ReportFilter $f): Collection
    {
        $eggIds = EggProduct::query()->pluck('role', 'product_id');

        return collect($this->reports->products($f, PHP_INT_MAX))
            ->filter(fn ($p) => isset($eggIds[$p['product_id']]))
            ->map(fn ($p) => [
                'product_id' => $p['product_id'],
                'name' => $p['name'],
                'unit' => $p['unit'] ?? 'Kg',
                'qty' => $p['qty'],
                'revenue' => $p['revenue'],
                'cost' => $p['cost'],
                'profit' => $p['profit'],
            ])
            ->sortByDesc('revenue')
            ->values();
    }

    private function sortings(ReportFilter $f): array
    {
        $row = EggSorting::query()
            ->whereBetween('created_at', $f->utcRange())
            ->when($f->outletIds, fn ($q) => $q->whereIn('outlet_id', $f->outletIds))
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(input_qty), 0) as input_qty, COALESCE(SUM(shrinkage_qty), 0) as shrinkage_qty, COALESCE(SUM(shrinkage_cost), 0) as shrinkage_cost')
            ->first();

        return [
            'count' => (int) $row->cnt,
            'input_qty' => Qty::normalize($row->input_qty),
            'shrinkage_qty' => Qty::normalize($row->shrinkage_qty),
            'shrinkage_cost' => (int) $row->shrinkage_cost,
        ];
    }

    /** Telur yang dikeluarkan dari stok karena rusak/hilang (menu Stok Keluar), dinilai dengan modal saat itu. */
    private function damagedLoss(ReportFilter $f): int
    {
        return (int) round((float) StockMovement::query()
            ->where('stock_movements.type', 'adjust_out')
            ->where('stock_movements.reference_type', (new StockAdjustment)->getMorphClass())
            ->join('stock_adjustments', 'stock_adjustments.id', '=', 'stock_movements.reference_id')
            ->whereIn('stock_adjustments.reason', StockAdjustment::SHRINKAGE_REASONS)
            ->whereIn('stock_movements.product_id', EggProduct::query()->select('product_id'))
            ->whereBetween('stock_movements.created_at', $f->utcRange())
            ->when($f->outletIds, fn ($q) => $q->whereIn('stock_movements.outlet_id', $f->outletIds))
            ->selectRaw('COALESCE(SUM(-stock_movements.qty_change * COALESCE(stock_movements.unit_cost, 0)), 0) as loss')
            ->value('loss'));
    }

    private function kgBought(ReportFilter $f): string
    {
        $qty = Purchase::query()
            ->join('purchase_items', 'purchase_items.purchase_id', '=', 'purchases.id')
            ->whereIn('purchase_items.product_id', EggProduct::query()->select('product_id'))
            ->whereDate('purchases.purchased_on', '>=', $f->from->toDateString())
            ->whereDate('purchases.purchased_on', '<=', $f->to->toDateString())
            ->when($f->outletIds, fn ($q) => $q->whereIn('purchases.outlet_id', $f->outletIds))
            ->selectRaw('COALESCE(SUM(purchase_items.qty * purchase_items.conversion_qty), 0) as qty')
            ->value('qty');

        return Qty::normalize($qty ?? 0);
    }

    /** Piutang pembeli yang belum lunas, per pembeli. */
    private function receivables(array $outletIds): array
    {
        $rows = Receivable::query()
            ->where('status', 'open')
            ->when($outletIds, fn ($q) => $q->whereIn('sale_id', Sale::query()->whereIn('outlet_id', $outletIds)->select('id')))
            ->with('customer:id,name,phone')
            ->get()
            ->groupBy('customer_id')
            ->map(fn (Collection $items) => [
                'customer_id' => $items->first()->customer_id,
                'name' => $items->first()->customer?->name ?? '-',
                'amount' => (int) $items->sum(fn ($r) => $r->amount - $r->paid_amount),
                'oldest_due' => $items->pluck('due_date')->filter()->map(fn ($d) => $d->toDateString())->min(),
                'count' => $items->count(),
            ])
            ->filter(fn ($r) => $r['amount'] > 0)
            ->sortByDesc('amount')
            ->values();

        return ['total' => $rows->sum('amount'), 'customers' => $rows->take(10)->all(), 'customer_count' => $rows->count()];
    }

    /** Utang ke peternak (belanja yang belum lunas), per peternak. */
    private function payables(string $today, array $outletIds): array
    {
        $rows = Purchase::query()
            ->where('payment_status', '!=', 'paid')
            ->when($outletIds, fn ($q) => $q->whereIn('outlet_id', $outletIds))
            ->with('supplier:id,name,phone')
            ->get()
            ->groupBy(fn (Purchase $p) => $p->supplier_id ?? 0)
            ->map(function (Collection $items) use ($today) {
                $oldest = $items->pluck('due_date')->filter()->map(fn ($d) => $d->toDateString())->min();

                return [
                    'supplier_id' => $items->first()->supplier_id,
                    'name' => $items->first()->supplier?->name ?? 'Tanpa nama peternak',
                    'amount' => (int) $items->sum(fn ($p) => $p->total - $p->paid_amount),
                    'oldest_due' => $oldest,
                    'overdue' => $oldest !== null && $oldest < $today,
                    'count' => $items->count(),
                ];
            })
            ->filter(fn ($r) => $r['amount'] > 0)
            ->sortByDesc('amount')
            ->values();

        return ['total' => $rows->sum('amount'), 'suppliers' => $rows->all()];
    }
}
