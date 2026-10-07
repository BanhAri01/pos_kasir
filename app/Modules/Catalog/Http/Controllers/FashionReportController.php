<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Core\Support\Qty;
use App\Core\Tenancy\CurrentOutlet;
use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Report\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Laporan toko baju:
 *  - Laporan varian: varian terlaris, varian yang menumpuk (slow moving), penjualan per ukuran & per warna.
 *  - Laporan titipan (konsinyasi): barang titipan yang terjual dan yang harus dibayar ke pemiliknya.
 */
class FashionReportController extends Controller
{
    public function __construct(private TenantContext $context, private CurrentOutlet $outlet) {}

    public function variants(Request $request): Response
    {
        [$period, $periods, $from, $to] = $this->period($request);
        $outletId = $this->outlet->id($request->user());
        $sold = $this->soldVariants($outletId, $from, $to);

        $variants = Product::query()->whereNotNull('parent_id')->whereIn('id', $sold->keys())->with('unit:id,name')->get()->keyBy('id');

        $best = $sold->sortByDesc('qty')->take(10)->map(fn ($row, $id) => [
            'name' => $variants[$id]->name ?? 'Barang dihapus',
            'qty' => Qty::display($row->qty),
            'revenue' => (int) $row->revenue,
        ])->values();

        // Total per dimensi (Ukuran, Warna, ...) dari nilai varian yang terjual.
        $dimensions = [];
        foreach ($sold as $id => $row) {
            foreach ($variants[$id]->variant_values ?? [] as $dim => $value) {
                $dimensions[$dim][$value] ??= ['value' => $value, 'qty' => '0', 'revenue' => 0];
                $dimensions[$dim][$value]['qty'] = Qty::add($dimensions[$dim][$value]['qty'], $row->qty);
                $dimensions[$dim][$value]['revenue'] += (int) $row->revenue;
            }
        }

        // Menumpuk: stok masih ada, tapi paling sedikit terjual dalam periode ini.
        $slow = Product::query()->whereNotNull('parent_id')->where('is_active', true)
            ->join('stocks', fn ($j) => $j->on('stocks.product_id', '=', 'products.id')->where('stocks.outlet_id', $outletId))
            ->where('stocks.qty', '>', 0)
            ->get(['products.id', 'products.name', 'stocks.qty as stock_qty', 'stocks.avg_cost'])
            ->map(fn ($p) => [
                'name' => $p->name,
                'stock' => Qty::display($p->stock_qty),
                'sold' => Qty::display($sold[$p->id]->qty ?? '0'),
                'sold_raw' => (float) ($sold[$p->id]->qty ?? 0),
                'value' => Qty::money((int) $p->avg_cost, Qty::normalize($p->stock_qty)),
            ])
            ->sortBy([['sold_raw', 'asc'], ['value', 'desc']])
            ->take(15)->values();

        return Inertia::render('Reports/Variants', [
            'period' => $period,
            'periods' => $periods,
            'best' => $best,
            'dimensions' => collect($dimensions)->map(fn ($values, $name) => [
                'name' => $name,
                'rows' => collect($values)->sortByDesc(fn ($v) => (float) $v['qty'])->map(fn ($v) => [...$v, 'qty' => Qty::display($v['qty'])])->values(),
            ])->values(),
            'slow' => $slow,
        ]);
    }

    public function consignment(Request $request): Response
    {
        [$period, $periods, $from, $to] = $this->period($request);

        $rows = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'products.consignor_id')
            ->where('sale_items.tenant_id', $this->context->id())
            ->where('sales.status', 'completed')
            ->whereBetween('sales.completed_at', [$from, $to])
            ->whereNotNull('products.consignor_id')
            ->groupBy('products.consignor_id', 'suppliers.name', 'sale_items.product_id', 'products.name', 'products.consignment_share_bp')
            ->get([
                'products.consignor_id', 'suppliers.name as consignor', 'sale_items.product_id', 'products.name', 'products.consignment_share_bp',
                DB::raw('SUM(sale_items.qty - sale_items.refunded_qty) as qty'),
                DB::raw('SUM(sale_items.subtotal * (sale_items.qty - sale_items.refunded_qty) / sale_items.qty) as revenue'),
            ]);

        $consignors = $rows->groupBy('consignor_id')->map(function (Collection $items) {
            $lines = $items->map(function ($r) {
                $revenue = (int) round((float) $r->revenue);
                $shopShare = intdiv($revenue * (int) $r->consignment_share_bp, 10000);

                return ['name' => $r->name, 'qty' => Qty::display($r->qty), 'revenue' => $revenue, 'shop_share' => $shopShare, 'payable' => $revenue - $shopShare];
            })->values();

            return [
                'name' => $items->first()->consignor ?? 'Pemasok dihapus',
                'items' => $lines,
                'revenue' => $lines->sum('revenue'),
                'shop_share' => $lines->sum('shop_share'),
                'payable' => $lines->sum('payable'),
            ];
        })->sortByDesc('payable')->values();

        return Inertia::render('Reports/Consignment', [
            'period' => $period,
            'periods' => $periods,
            'consignors' => $consignors,
            'total_payable' => $consignors->sum('payable'),
        ]);
    }

    /** Qty bersih (dikurangi yang dikembalikan) & omzet varian yang terjual dalam periode. */
    private function soldVariants(int $outletId, Carbon $from, Carbon $to): Collection
    {
        return DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sale_items.tenant_id', $this->context->id())
            ->where('sales.outlet_id', $outletId)
            ->where('sales.status', 'completed')
            ->whereBetween('sales.completed_at', [$from, $to])
            ->whereNotNull('products.parent_id')
            ->groupBy('sale_items.product_id')
            ->get([
                'sale_items.product_id',
                DB::raw('SUM(sale_items.qty - sale_items.refunded_qty) as qty'),
                DB::raw('SUM(sale_items.subtotal * (sale_items.qty - sale_items.refunded_qty) / sale_items.qty) as revenue'),
            ])
            ->keyBy('product_id');
    }

    /** @return array{0:string, 1:list<array>, 2:Carbon, 3:Carbon} */
    private function period(Request $request): array
    {
        $timezone = $this->context->get()->timezone;
        $presets = array_intersect_key(ReportService::presets($timezone), array_flip(['7days', 'month', 'last_month']));
        $presets['90days'] = ['label' => '3 bulan', 'from' => now($timezone)->subDays(89)->toDateString(), 'to' => now($timezone)->toDateString()];
        $period = array_key_exists($request->query('period'), $presets) ? $request->query('period') : 'month';

        return [
            $period,
            collect($presets)->map(fn ($p, $key) => ['value' => $key, 'label' => $p['label']])->values()->all(),
            Carbon::parse($presets[$period]['from'], $timezone)->startOfDay()->utc(),
            Carbon::parse($presets[$period]['to'], $timezone)->endOfDay()->utc(),
        ];
    }
}
