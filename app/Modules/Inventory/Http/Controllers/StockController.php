<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Core\Support\Qty;
use App\Core\Tenancy\CurrentOutlet;
use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Modules\Catalog\Http\Resources\ProductResource;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\ProductQuery;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\WarehouseLocation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman stok: daftar stok di outlet aktif, riwayat stok, dan form dokumen stok.
 */
class StockController extends Controller
{
    public function __construct(private CurrentOutlet $outlet) {}

    public function index(Request $request): Response
    {
        $outletId = $this->outlet->id($request->user());

        $products = ProductQuery::search(ProductQuery::forOutlet($outletId, withLocation: true), $request->string('q')->toString())
            ->where('track_stock', true)
            ->whereIn('type', ['goods', 'ingredient'])
            ->orderBy('name')
            ->get();

        $items = ProductResource::collection($products)->resolve();

        // Yang perlu perhatian (habis / menipis) ditampilkan paling atas.
        usort($items, fn ($a, $b) => [! $a['is_out_of_stock'], ! $a['is_low_stock'], $a['name']] <=> [! $b['is_out_of_stock'], ! $b['is_low_stock'], $b['name']]);

        return Inertia::render('Stock/Index', [
            'products' => $items,
            'summary' => [
                'total' => count($items),
                'low' => count(array_filter($items, fn ($p) => $p['is_low_stock'] && ! $p['is_out_of_stock'])),
                'out' => count(array_filter($items, fn ($p) => $p['is_out_of_stock'])),
            ],
            'q' => $request->string('q')->toString(),
        ]);
    }

    public function history(Request $request, Product $product, TenantContext $context): Response
    {
        $outletId = $this->outlet->id($request->user());
        $timezone = $context->get()->timezone;

        $movements = StockMovement::query()
            ->where('product_id', $product->id)
            ->where('outlet_id', $outletId)
            ->with('user:id,name')
            ->latest('id')
            ->paginate(30);

        return Inertia::render('Stock/History', [
            'product' => ProductResource::make(ProductQuery::forOutlet($outletId, withLocation: true)->findOrFail($product->id))->resolve(),
            // Blok / rak penyimpanan (modul multi_warehouse)
            'locations' => $context->get()->hasModule('multi_warehouse')
                ? WarehouseLocation::query()->where('outlet_id', $outletId)->orderBy('sort_order')->orderBy('name')->get(['id', 'name'])
                : [],
            'movements' => $movements->getCollection()->map(fn (StockMovement $m) => [
                'id' => $m->id,
                'label' => $m->label(),
                'type' => $m->type,
                'qty_change' => Qty::display($m->qty_change),
                'is_in' => ! Qty::isNegative($m->qty_change),
                'qty_after' => Qty::display($m->qty_after),
                'note' => $m->note,
                'user' => $m->user?->name,
                'at' => $m->created_at->timezone($timezone)->locale('id')->translatedFormat('j M Y, H:i'),
            ]),
            'next_url' => $movements->nextPageUrl(),
        ]);
    }

    /** /stok/masuk atau /stok/keluar */
    public function adjust(string $type, TenantContext $tenants): Response
    {
        $type = ['masuk' => 'in', 'keluar' => 'out'][$type] ?? abort(404);

        return Inertia::render('Stock/Adjust', [
            'type' => $type,
            'reasons' => collect(StockAdjustment::REASONS[$type])
                ->reject(fn ($label, $value) => in_array($value, StockAdjustment::WAREHOUSE_REASONS, true) && ! $tenants->get()->hasModule('shrinkage'))
                ->map(fn ($label, $value) => compact('value', 'label'))->values(),
        ]);
    }

    public function opname(Request $request): Response
    {
        $outletId = $this->outlet->id($request->user());

        $products = ProductQuery::forOutlet($outletId)
            ->where('track_stock', true)
            ->whereIn('type', ['goods', 'ingredient'])
            ->orderBy('name')
            ->get();

        return Inertia::render('Stock/Opname', [
            'products' => ProductResource::collection($products)->resolve(),
        ]);
    }
}
