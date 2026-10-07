<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Core\Tenancy\CurrentOutlet;
use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\VariantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cetak label barcode (modul barcode_label): pilih barang/varian + jumlah label,
 * lalu cetak di printer label thermal atau kertas stiker A4 lewat menu cetak browser.
 */
class LabelController extends Controller
{
    public function index(Request $request, CurrentOutlet $outlet, TenantContext $context): Response
    {
        $outletId = $outlet->id($request->user());

        $products = Product::query()
            ->where('is_active', true)->where('has_variants', false)->where('type', 'goods')
            ->with(['stocks' => fn ($q) => $q->where('outlet_id', $outletId)])
            ->orderBy('name')->get();

        return Inertia::render('Products/Labels', [
            'products' => $products->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'parent_id' => $p->parent_id,
                'code' => $p->code,
                'barcode' => $p->barcode,
                'price' => $p->price,
                'stock' => (int) max(0, floor((float) ($p->stocks->first()?->qty ?? 0))),
            ]),
            'shopName' => $context->get()->name,
            'preselect' => array_map('intval', (array) $request->query('ids', [])),
        ]);
    }

    /** Barang yang belum punya barcode diberi barcode toko otomatis, supaya labelnya bisa dicetak. */
    public function generateBarcodes(Request $request, TenantContext $context): RedirectResponse
    {
        $data = $request->validate([
            'product_ids' => ['required', 'array', 'min:1', 'max:500'],
            'product_ids.*' => ['integer', Rule::exists('products', 'id')->where('tenant_id', $context->id())],
        ]);

        $count = 0;
        Product::query()->whereIn('id', $data['product_ids'])->whereNull('barcode')->get()
            ->each(function (Product $p) use (&$count) {
                $p->update(['barcode' => VariantService::internalBarcode($p->id)]);
                $count++;
            });

        return back()->with('success', $count ? "{$count} barang sudah diberi barcode otomatis." : 'Semua barang yang dipilih sudah punya barcode.');
    }
}
