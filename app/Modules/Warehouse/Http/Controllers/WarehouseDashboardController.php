<?php

namespace App\Modules\Warehouse\Http\Controllers;

use App\Core\Support\Qty;
use App\Core\Tenancy\CurrentOutlet;
use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\StockBatch;
use App\Modules\Warehouse\Models\ProductionOrder;
use App\Modules\Warehouse\Services\WarehouseReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Beranda Gudang: tombol alur kerja (Terima Barang -> Olah -> Kemas -> Jual -> Cek Stok) dan ringkasan:
 * stok per gudang (kg & setara karung), barang hampir habis, batch hampir kedaluwarsa, susut bulan ini, HPP per barang.
 */
class WarehouseDashboardController extends Controller
{
    /** Batch dianggap "hampir kedaluwarsa" kalau tinggal sekian hari. */
    public const EXPIRY_WARNING_DAYS = 30;

    /** Beranda gudang tersedia kalau salah satu modul gudang menyala. */
    public const MODULES = ['multi_warehouse', 'weighed_receiving', 'repack', 'production', 'batch_lot', 'shrinkage'];

    public function __invoke(Request $request, CurrentOutlet $outlet, TenantContext $context, WarehouseReportService $reports): Response
    {
        $tenant = $context->get();
        abort_unless(collect(self::MODULES)->contains(fn ($code) => $tenant->hasModule($code)), 404);
        $outletId = $outlet->id($request->user());
        $timezone = $tenant->timezone;
        $outlets = Outlet::query()->where('is_active', true)->orderByRaw("CASE WHEN type = 'warehouse' THEN 0 ELSE 1 END")->orderBy('name')->get(['id', 'name', 'type']);

        return Inertia::render('Warehouse/Dashboard', [
            'outlets' => $outlets->map->only(['id', 'name', 'type']),
            'bulk' => Inertia::defer(fn () => $this->bulkStock($outlets->pluck('id')->all())),
            'low' => Inertia::defer(fn () => $this->lowStock($outletId)),
            'expiring' => Inertia::defer(fn () => $tenant->hasModule('batch_lot') ? $this->expiring($timezone) : null),
            'shrinkage' => Inertia::defer(fn () => $this->shrinkage($outlets->pluck('id')->all(), $timezone, $reports)),
            'costs' => Inertia::defer(fn () => $this->costs($outletId)),
        ]);
    }

    /** Stok barang curah / berat di setiap gudang: kg dan setara karung. */
    private function bulkStock(array $outletIds): array
    {
        $products = Product::query()->where('track_stock', true)->where('is_packaging', false)
            ->where(fn ($q) => $q->whereNotNull('pack_size')->orWhereHas('unit', fn ($u) => $u->where('allow_decimal', true)))
            ->with('unit:id,name')->orderBy('name')->get();

        $stocks = Stock::query()->whereIn('outlet_id', $outletIds)->whereIn('product_id', $products->pluck('id'))->get()
            ->groupBy('product_id');

        return $products->map(function (Product $p) use ($stocks, $outletIds) {
            $rows = $stocks->get($p->id, collect())->keyBy('outlet_id');
            $total = '0';
            $cells = [];
            foreach ($outletIds as $id) {
                $qty = Qty::normalize($rows[$id]->qty ?? '0');
                $total = Qty::add($total, $qty);
                $cells[$id] = $this->qtyWithPacks($qty, $p);
            }

            return ['name' => $p->name, 'unit' => $p->unit?->name ?? '', 'cells' => $cells, 'total' => $this->qtyWithPacks($total, $p)];
        })->all();
    }

    private function qtyWithPacks(string $qty, Product $p): array
    {
        $packs = $p->pack_size !== null && Qty::cmp($p->pack_size, '0') > 0
            ? Qty::display(bcdiv($qty, Qty::normalize($p->pack_size), 1)).' '.$p->pack_name
            : null;

        return ['qty' => Qty::display($qty), 'packs' => $packs, 'negative' => Qty::isNegative($qty)];
    }

    /** Barang & bahan kemas yang sudah mencapai batas menipis di gudang aktif. */
    private function lowStock(int $outletId): array
    {
        return Stock::query()->where('outlet_id', $outletId)
            ->join('products', 'products.id', '=', 'stocks.product_id')
            ->whereNull('products.deleted_at')->where('products.track_stock', true)->whereNotNull('products.min_stock')
            ->whereColumn('stocks.qty', '<=', 'products.min_stock')
            ->orderBy('stocks.qty')
            ->limit(10)
            ->with('product.unit:id,name')
            ->get(['stocks.*'])
            ->map(fn (Stock $s) => [
                'name' => $s->product?->name,
                'qty' => Qty::display($s->qty),
                'unit' => $s->product?->unit?->name ?? '',
                'is_packaging' => (bool) $s->product?->is_packaging,
                'out' => Qty::cmp($s->qty, '0') <= 0,
            ])->all();
    }

    private function expiring(string $timezone): array
    {
        $today = Carbon::now($timezone)->startOfDay();

        return StockBatch::query()->available()->whereNotNull('expires_at')
            ->whereDate('expires_at', '<=', $today->copy()->addDays(self::EXPIRY_WARNING_DAYS)->toDateString())
            ->with(['product.unit:id,name', 'outlet:id,name'])
            ->orderBy('expires_at')->limit(10)->get()
            ->map(fn (StockBatch $b) => [
                'product' => $b->product?->name,
                'batch_no' => $b->batch_no,
                'outlet' => $b->outlet?->name,
                'qty' => Qty::display($b->qty),
                'unit' => $b->product?->unit?->name ?? '',
                'expires_at' => $b->expires_at->locale('id')->translatedFormat('j M Y'),
                'days_left' => (int) $today->diffInDays($b->expires_at, false),
            ])->all();
    }

    /** Susut bulan ini di semua gudang (rupiah) + susut olah (kg). */
    private function shrinkage(array $outletIds, string $timezone, WarehouseReportService $reports): array
    {
        $from = Carbon::now($timezone)->startOfMonth()->utc();
        $to = Carbon::now($timezone)->endOfDay()->utc();

        $value = 0;
        foreach ($outletIds as $id) {
            $value += $reports->shrinkage($id, $from, $to)['total_value'];
        }

        $production = ProductionOrder::query()->whereIn('kind', ['production', 'repack'])->whereBetween('created_at', [$from, $to])->get(['shrinkage_qty']);

        return [
            'value' => $value,
            'production_qty' => Qty::display($production->reduce(fn ($sum, $o) => Qty::add($sum, $o->shrinkage_qty), '0')),
        ];
    }

    /** HPP (modal rata-rata) per barang jual di gudang aktif, dibanding harga jual. */
    private function costs(int $outletId): array
    {
        return Stock::query()->where('outlet_id', $outletId)
            ->whereHas('product', fn ($q) => $q->where('type', 'goods')->where('price', '>', 0))
            ->with('product.unit:id,name')
            ->get()
            ->sortBy(fn (Stock $s) => $s->product->name)
            ->map(fn (Stock $s) => [
                'name' => $s->product->name,
                'unit' => $s->product->unit?->name ?? '',
                'cost' => $s->avg_cost,
                'price' => $s->product->price,
                'margin' => $s->product->price - $s->avg_cost,
            ])->values()->all();
    }
}
