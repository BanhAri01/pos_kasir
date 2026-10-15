<?php

namespace App\Modules\EggCollector\Http\Controllers;

use App\Core\Tenancy\CurrentOutlet;
use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Modules\EggCollector\Models\EggProduct;
use App\Modules\EggCollector\Services\EggPriceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Harga Hari Ini dan pengaturan jenis telur. */
class EggPriceController extends Controller
{
    public function __construct(
        private CurrentOutlet $outlet,
        private TenantContext $context,
        private EggPriceService $prices,
    ) {}

    public function index(Request $request): Response
    {
        $outlet = $this->outlet->get($request->user());
        abort_unless($outlet, 404);

        return Inertia::render('Pengepul/Prices', [
            'date' => $this->prices->today(),
            'outlet' => ['id' => $outlet->id, 'name' => $outlet->name],
            'rows' => $this->prices->board($outlet->id),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $outletId = $this->outlet->id($request->user());
        abort_unless($outletId, 404);

        $data = $request->validate([
            'prices' => ['required', 'array', 'min:1', 'max:30'],
            'prices.*.product_id' => ['required', 'integer', 'distinct', Rule::exists('egg_products', 'product_id')->where('tenant_id', $this->context->id())],
            'prices.*.buy_price' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'prices.*.sell_price' => ['required', 'integer', 'min:0', 'max:100000000'],
        ], [
            'prices.*.sell_price.required' => 'Isi harga jual.',
        ]);

        $count = $this->prices->save($outletId, $data['prices'], $request->user());

        return back()->with('success', "Harga hari ini tersimpan untuk {$count} jenis telur. Kasir langsung memakai harga baru.");
    }

    public function types(): Response
    {
        $marked = EggProduct::query()->pluck('role', 'product_id');

        return Inertia::render('Pengepul/Types', [
            'products' => \App\Modules\Catalog\Models\Product::query()
                ->where('type', 'goods')->whereNull('parent_id')->where('is_active', true)
                ->with('unit:id,name')->orderBy('name')->get(['id', 'name', 'base_unit_id'])
                ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'unit' => $p->unit?->name ?? '', 'role' => $marked[$p->id] ?? null])
                ->values(),
            'roles' => EggProduct::ROLES,
        ]);
    }

    public function updateTypes(Request $request): RedirectResponse
    {
        $tenantId = $this->context->id();
        $data = $request->validate([
            'mixed' => ['required', 'array', 'min:1', 'max:5'],
            'mixed.*' => ['integer', 'distinct', Rule::exists('products', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
            'grades' => ['required', 'array', 'min:1', 'max:15'],
            'grades.*' => ['integer', 'distinct', 'not_in:'.implode(',', array_map('intval', (array) $request->input('mixed', []))), Rule::exists('products', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
        ], [
            'mixed.required' => 'Pilih minimal satu barang sebagai telur campur.',
            'grades.required' => 'Pilih minimal satu jenis hasil sortir.',
            'grades.*.not_in' => 'Barang yang sama tidak bisa jadi telur campur sekaligus hasil sortir.',
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($data) {
            $keep = array_merge($data['mixed'], $data['grades']);
            EggProduct::query()->whereNotIn('product_id', $keep)->delete();
            foreach (['mixed' => $data['mixed'], 'grades' => $data['grades']] as $key => $ids) {
                foreach (array_values($ids) as $i => $id) {
                    EggProduct::updateOrCreate(['product_id' => $id], ['role' => $key === 'mixed' ? 'mixed' : 'grade', 'sort_order' => $i]);
                }
            }
        });

        return redirect()->route('egg.prices')->with('success', 'Jenis telur tersimpan.');
    }
}
