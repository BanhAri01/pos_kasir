<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Core\Support\Qty;
use App\Core\Tenancy\CurrentOutlet;
use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\VariantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Atur ukuran & warna satu model: pilih ukuran dan warna, semua kombinasi + SKU dibuat otomatis. */
class VariantController extends Controller
{
    public function __construct(private CurrentOutlet $outlet) {}

    public function edit(Request $request, Product $product): Response
    {
        $this->authorize('update', $product);
        abort_if($product->parent_id !== null || $product->type !== 'goods', 404);

        $outletId = $this->outlet->id($request->user());
        $variants = $product->variants()->with(['stocks' => fn ($q) => $q->where('outlet_id', $outletId)])->get();

        return Inertia::render('Products/Variants', [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'code' => $product->code,
                'options' => $product->variant_options ?? [],
            ],
            'variants' => $variants->map(fn (Product $v) => [
                'id' => $v->id,
                'values' => $v->variant_values ?? [],
                'code' => $v->code,
                'barcode' => $v->barcode,
                'price' => $v->price,
                'min_stock' => $v->min_stock !== null ? Qty::display($v->min_stock) : '',
                'stock' => Qty::display($v->stocks->first()?->qty ?? '0'),
                'is_active' => $v->is_active,
            ])->values(),
        ]);
    }

    public function update(Request $request, Product $product, VariantService $service): RedirectResponse
    {
        $this->authorize('update', $product);
        abort_if($product->parent_id !== null || $product->type !== 'goods', 404);

        $request->merge(['rows' => array_map(
            fn ($row) => is_array($row) ? [...$row, 'min_stock' => Qty::fromInput($row['min_stock'] ?? null), 'initial_stock' => Qty::fromInput($row['initial_stock'] ?? null)] : $row,
            array_values((array) $request->input('rows', [])),
        )]);

        $data = $request->validate([
            'options' => ['required', 'array', 'min:1', 'max:'.VariantService::MAX_DIMENSIONS],
            'options.*.name' => ['required', 'string', 'max:30'],
            'options.*.values' => ['required', 'array', 'min:1', 'max:40'],
            'options.*.values.*' => ['required', 'string', 'max:30'],
            'rows' => ['required', 'array', 'min:1', 'max:400'],
            'rows.*.values' => ['required', 'array'],
            'rows.*.code' => ['nullable', 'string', 'max:50'],
            'rows.*.barcode' => ['nullable', 'string', 'max:64'],
            'rows.*.price' => ['required', 'integer', 'min:0'],
            'rows.*.min_stock' => ['nullable', 'numeric', 'min:0'],
            'rows.*.initial_stock' => ['nullable', 'numeric', 'min:0'],
            'auto_barcode' => ['boolean'],
        ], [
            'options.required' => 'Isi minimal satu pilihan, misalnya Ukuran: S, M, L.',
            'options.*.name.required' => 'Beri nama pilihannya, misalnya Ukuran atau Warna.',
            'options.*.values.required' => 'Isi minimal satu nilai, misalnya S atau Hitam.',
            'rows.required' => 'Belum ada kombinasi varian.',
            'rows.*.price.required' => 'Isi harga setiap varian.',
        ]);

        $model = $service->sync($product, $data['options'], $data['rows'], $this->outlet->id($request->user()), (bool) ($data['auto_barcode'] ?? false));
        $count = $model->variants->where('is_active', true)->count();

        return redirect()->route('products.index')->with('success', "{$model->name}: {$count} varian sudah disimpan.");
    }
}
