<?php

namespace App\Modules\Warehouse\Http\Controllers;

use App\Core\Support\Qty;
use App\Core\Tenancy\CurrentOutlet;
use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Warehouse\Models\ProductionFormula;
use App\Modules\Warehouse\Models\ProductionOrder;
use App\Modules\Warehouse\Services\ProductionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kemas Ulang (repack / bongkar) dan Olah / Giling (production), dengan alur langkah demi langkah.
 */
class ProductionController extends Controller
{
    public function __construct(
        private CurrentOutlet $outlet,
        private TenantContext $context,
        private ProductionService $service,
    ) {}

    /** Riwayat olah & kemas di gudang aktif. */
    public function index(Request $request): Response
    {
        $timezone = $this->context->get()->timezone;

        return Inertia::render('Warehouse/Orders', [
            'orders' => ProductionOrder::query()
                ->where('outlet_id', $this->outlet->id($request->user()))
                ->with('output.unit:id,name')
                ->latest('id')
                ->paginate(30)
                ->through(fn (ProductionOrder $o) => [
                    'uuid' => $o->uuid,
                    'number' => $o->number,
                    'kind' => $o->kind,
                    'kind_label' => ProductionOrder::KINDS[$o->kind] ?? $o->kind,
                    'output' => $o->output?->name,
                    'output_qty' => Qty::display($o->output_qty),
                    'unit' => $o->output?->unit?->name ?? '',
                    'shrinkage' => Qty::isZero($o->shrinkage_qty) ? null : Qty::display($o->shrinkage_qty),
                    'date' => $o->created_at->timezone($timezone)->locale('id')->translatedFormat('j M Y, H:i'),
                ]),
        ]);
    }

    public function show(string $uuid): Response
    {
        $order = ProductionOrder::query()->where('uuid', $uuid)
            ->with(['items.product.unit:id,name', 'output.unit:id,name', 'user:id,name', 'formula:id,name'])->firstOrFail();
        $timezone = $this->context->get()->timezone;
        $mainUnit = $order->items->first(fn ($i) => $i->role === 'material')?->product?->unit?->name ?? '';

        return Inertia::render('Warehouse/OrderShow', [
            'order' => [
                'number' => $order->number,
                'kind' => $order->kind,
                'kind_label' => ProductionOrder::KINDS[$order->kind] ?? $order->kind,
                'formula' => $order->formula?->name,
                'date' => $order->created_at->timezone($timezone)->locale('id')->translatedFormat('l, j F Y H:i'),
                'user' => $order->user?->name,
                'output' => $order->output?->name,
                'unit' => $order->output?->unit?->name ?? '',
                'planned_output' => Qty::display($order->planned_output_qty),
                'output_qty' => Qty::display($order->output_qty),
                'shrinkage' => Qty::display($order->shrinkage_qty),
                'shrinkage_unit' => $order->kind === 'unpack' ? ($order->output?->unit?->name ?? '') : $mainUnit,
                'unit_cost' => $order->unit_cost,
                'costs' => array_filter([
                    'Bahan' => $order->materials_cost,
                    'Bahan kemas' => $order->packaging_cost,
                    'Upah' => $order->labor_cost,
                    'Listrik' => $order->utility_cost,
                    'Mesin' => $order->machine_cost,
                    'Biaya lain' => $order->other_cost,
                ]),
                'batch_no' => $order->batch_no,
                'expires_at' => $order->expires_at?->locale('id')->translatedFormat('j M Y'),
                'note' => $order->note,
                'items' => $order->items->map(fn ($i) => [
                    'role' => $i->role,
                    'name' => $i->product?->name,
                    'unit' => $i->product?->unit?->name ?? '',
                    'planned' => Qty::display($i->planned_qty),
                    'actual' => Qty::display($i->actual_qty),
                    'total_cost' => $i->total_cost,
                ]),
            ],
        ]);
    }

    /** Kemas Ulang: pilih barang karungan, berapa karung, selesai. Bisa juga bongkar. */
    public function repack(Request $request): Response
    {
        return Inertia::render('Warehouse/Repack', [
            'formulas' => $this->formulas('repack', $this->outlet->id($request->user())),
        ]);
    }

    public function storeRepack(Request $request): RedirectResponse
    {
        $data = $this->validateOrder($request, 'repack');
        $order = $this->service->produce($data, $this->outlet->id($request->user()), $request->user());

        return redirect()->route('warehouse.orders.show', $order->uuid)
            ->with('success', 'Selesai dikemas: '.Qty::display($order->output_qty).' '.($order->output?->unit?->name ?? '').' '.$order->output?->name.'. Stok sudah diperbarui.');
    }

    public function storeUnpack(Request $request): RedirectResponse
    {
        $request->merge(['qty' => Qty::fromInput($request->input('qty')), 'received_qty' => Qty::fromInput($request->input('received_qty'))]);
        $data = $request->validate([
            'formula_id' => ['required', 'integer', Rule::exists('production_formulas', 'id')->where('tenant_id', $this->context->id())->where('kind', 'repack')],
            'qty' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'received_qty' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'reuse_packaging' => ['boolean'],
            'note' => ['nullable', 'string', 'max:255'],
        ], ['qty.gt' => 'Isi berapa karung yang dibongkar.']);

        $order = $this->service->unpack($data, $this->outlet->id($request->user()), $request->user());

        return redirect()->route('warehouse.orders.show', $order->uuid)
            ->with('success', 'Selesai dibongkar: '.Qty::display($order->output_qty).' '.($order->output?->unit?->name ?? '').' '.$order->output?->name.' masuk stok.');
    }

    /** Olah / Giling: pilih resep, berapa kali olah, catat bahan terpakai & hasil. */
    public function production(Request $request): Response
    {
        return Inertia::render('Warehouse/Production', [
            'formulas' => $this->formulas('production', $this->outlet->id($request->user())),
        ]);
    }

    public function storeProduction(Request $request): RedirectResponse
    {
        $data = $this->validateOrder($request, 'production');
        $order = $this->service->produce($data, $this->outlet->id($request->user()), $request->user());

        return redirect()->route('warehouse.orders.show', $order->uuid)
            ->with('success', 'Hasil olah '.Qty::display($order->output_qty).' '.($order->output?->unit?->name ?? '').' '.$order->output?->name.' sudah masuk stok.');
    }

    private function validateOrder(Request $request, string $kind): array
    {
        $request->merge([
            'batches' => Qty::fromInput($request->input('batches')),
            'output_qty' => Qty::fromInput($request->input('output_qty')),
            'inputs' => array_map(fn ($i) => is_array($i) ? [...$i, 'actual_qty' => Qty::fromInput($i['actual_qty'] ?? null)] : $i, array_values((array) $request->input('inputs', []))),
        ]);

        return $request->validate([
            'formula_id' => ['required', 'integer', Rule::exists('production_formulas', 'id')->where('tenant_id', $this->context->id())->where('kind', $kind)->where('is_active', true)],
            'batches' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'output_qty' => ['nullable', 'numeric', 'gt:0', 'max:99999999'],
            'inputs' => ['sometimes', 'array', 'max:30'],
            'inputs.*.product_id' => ['required', 'integer'],
            'inputs.*.actual_qty' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'labor_cost' => ['nullable', 'integer', 'min:0'],
            'utility_cost' => ['nullable', 'integer', 'min:0'],
            'machine_cost' => ['nullable', 'integer', 'min:0'],
            'other_cost' => ['nullable', 'integer', 'min:0'],
            'batch_no' => ['nullable', 'string', 'max:40'],
            'expires_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'formula_id.required' => 'Pilih dulu yang mau dibuat.',
            'batches.gt' => 'Jumlah harus lebih dari 0.',
            'output_qty.gt' => 'Hasil harus lebih dari 0.',
            'inputs.*.actual_qty.required' => 'Isi jumlah bahan yang terpakai.',
        ]);
    }

    /** Resep + stok bahan & hasil di gudang aktif, supaya layar bisa memberi tahu kalau bahan kurang. */
    private function formulas(string $kind, int $outletId): array
    {
        $formulas = ProductionFormula::query()->where('kind', $kind)->where('is_active', true)
            ->with(['output.unit:id,name,allow_decimal', 'items.product.unit:id,name,allow_decimal'])->orderBy('name')->get();

        $productIds = $formulas->flatMap(fn ($f) => [$f->output_product_id, ...$f->items->pluck('product_id')])->unique();
        $stocks = Stock::query()->where('outlet_id', $outletId)->whereIn('product_id', $productIds)->get()->keyBy('product_id');
        $stockOf = fn (int $id) => Qty::display($stocks[$id]->qty ?? '0');
        $costOf = fn ($product) => (int) ($stocks[$product->id]->avg_cost ?? $product->cost_price);

        return $formulas->map(fn (ProductionFormula $f) => [
            'id' => $f->id,
            'name' => $f->name,
            'output' => [
                'id' => $f->output_product_id, 'name' => $f->output?->name, 'unit' => $f->output?->unit?->name ?? '',
                'decimal' => (bool) $f->output?->unit?->allow_decimal, 'stock' => $stockOf($f->output_product_id),
                'cost' => $f->output ? $costOf($f->output) : 0,
                'track_batch' => (bool) $f->output?->track_batch,
            ],
            'output_qty' => Qty::display($f->output_qty),
            'cost_per_batch' => $f->cost_per_batch,
            'items' => $f->items->map(fn ($i) => [
                'product_id' => $i->product_id,
                'name' => $i->product?->name,
                'unit' => $i->product?->unit?->name ?? '',
                'decimal' => (bool) $i->product?->unit?->allow_decimal,
                'qty' => Qty::display($i->qty),
                'is_packaging' => (bool) $i->product?->is_packaging,
                'stock' => $stockOf($i->product_id),
                'cost' => $i->product ? $costOf($i->product) : 0,
            ])->all(),
        ])->all();
    }
}
