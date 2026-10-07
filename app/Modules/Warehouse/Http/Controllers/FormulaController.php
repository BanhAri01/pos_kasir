<?php

namespace App\Modules\Warehouse\Http\Controllers;

use App\Core\Support\Qty;
use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Warehouse\Models\ProductionFormula;
use App\Modules\Warehouse\Services\ProductionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Resep kemas ulang & olah: bahan per 1 kali olah -> hasil. */
class FormulaController extends Controller
{
    public function __construct(private TenantContext $context) {}

    public function index(): Response
    {
        return Inertia::render('Warehouse/Formulas', [
            'formulas' => ProductionFormula::query()->where('is_active', true)->whereIn('kind', $this->kinds())
                ->with(['output.unit:id,name', 'items.product.unit:id,name'])->orderBy('kind')->orderBy('name')->get()
                ->map(fn (ProductionFormula $f) => [
                    'id' => $f->id,
                    'kind' => $f->kind,
                    'name' => $f->name,
                    'output' => $f->output?->name,
                    'output_qty' => Qty::display($f->output_qty),
                    'output_unit' => $f->output?->unit?->name ?? '',
                    'items' => $f->items->map(fn ($i) => Qty::display($i->qty).' '.($i->product?->unit?->name ?? '').' '.$i->product?->name)->all(),
                ]),
            'kinds' => $this->kindOptions(),
        ]);
    }

    public function create(Request $request): Response
    {
        $kind = in_array($request->query('jenis'), $this->kinds(), true) ? $request->query('jenis') : $this->kinds()[0];

        return $this->form(null, $kind);
    }

    public function edit(ProductionFormula $formula): Response
    {
        return $this->form($formula->load('items'), $formula->kind);
    }

    public function store(Request $request, ProductionService $service): RedirectResponse
    {
        $formula = $service->saveFormula($this->validated($request));

        return redirect()->route('warehouse.formulas')->with('success', "Resep {$formula->name} sudah disimpan.");
    }

    public function update(Request $request, ProductionFormula $formula, ProductionService $service): RedirectResponse
    {
        $service->saveFormula($this->validated($request), $formula);

        return redirect()->route('warehouse.formulas')->with('success', "Resep {$formula->name} sudah disimpan.");
    }

    /** Disembunyikan (riwayat olah yang memakai resep ini tetap utuh). */
    public function destroy(ProductionFormula $formula): RedirectResponse
    {
        $formula->update(['is_active' => false]);

        return redirect()->route('warehouse.formulas')->with('success', "Resep {$formula->name} dihapus.");
    }

    private function form(?ProductionFormula $formula, string $kind): Response
    {
        return Inertia::render('Warehouse/FormulaForm', [
            'formula' => $formula ? [
                'id' => $formula->id,
                'kind' => $formula->kind,
                'name' => $formula->name,
                'output_product_id' => $formula->output_product_id,
                'output_qty' => Qty::display($formula->output_qty),
                'cost_per_batch' => $formula->cost_per_batch,
                'note' => $formula->note,
                'items' => $formula->items->map(fn ($i) => ['product_id' => $i->product_id, 'qty' => Qty::display($i->qty)])->all(),
            ] : null,
            'kind' => $kind,
            'kinds' => $this->kindOptions(),
            'products' => Product::query()->whereIn('type', ['goods', 'ingredient'])->where('track_stock', true)
                ->with('unit:id,name')->orderBy('name')->get()
                ->map(fn (Product $p) => ['id' => $p->id, 'name' => $p->name, 'unit' => $p->unit?->name ?? 'pcs', 'is_packaging' => $p->is_packaging, 'pack_size' => $p->pack_size !== null ? Qty::display($p->pack_size) : null]),
        ]);
    }

    private function validated(Request $request): array
    {
        $tenantId = $this->context->id();
        $request->merge([
            'output_qty' => Qty::fromInput($request->input('output_qty')),
            'items' => array_map(fn ($i) => is_array($i) ? [...$i, 'qty' => Qty::fromInput($i['qty'] ?? null)] : $i, array_values((array) $request->input('items', []))),
        ]);

        return $request->validate([
            'kind' => ['required', Rule::in($this->kinds())],
            'name' => ['required', 'string', 'max:100'],
            'output_product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
            'output_qty' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'cost_per_batch' => ['nullable', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.product_id' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
            'items.*.qty' => ['required', 'numeric', 'gt:0', 'max:9999999'],
        ], [
            'name.required' => 'Beri nama resepnya. Contoh: Kemas Jagung 25 kg.',
            'output_product_id.required' => 'Pilih barang hasilnya.',
            'output_qty.gt' => 'Jumlah hasil harus lebih dari 0.',
            'items.required' => 'Tambahkan minimal satu bahan.',
            'items.*.product_id.distinct' => 'Bahan yang sama tidak boleh dimasukkan dua kali.',
            'items.*.qty.gt' => 'Jumlah bahan harus lebih dari 0.',
        ]);
    }

    /** Jenis resep yang boleh dibuat sesuai modul yang menyala. */
    private function kinds(): array
    {
        $tenant = $this->context->get();
        $kinds = array_keys(array_filter(['repack' => $tenant->hasModule('repack'), 'production' => $tenant->hasModule('production')]));
        abort_if($kinds === [], 404);

        return $kinds;
    }

    private function kindOptions(): array
    {
        return array_map(fn ($k) => ['value' => $k, 'label' => ProductionFormula::KINDS[$k]], $this->kinds());
    }
}
