<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Core\Tenancy\CurrentOutlet;
use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Modules\Catalog\Http\Requests\ProductRequest;
use App\Modules\Catalog\Http\Resources\ProductResource;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\ModifierGroup;
use App\Modules\Catalog\Models\PriceLevel;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Catalog\Services\ProductExtrasService;
use App\Modules\Catalog\Services\ProductQuery;
use App\Modules\Catalog\Services\ProductService;
use App\Modules\Catalog\Services\SampleDataService;
use App\Modules\Catalog\Services\VariantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(
        private ProductService $service,
        private CurrentOutlet $outlet,
        private ProductExtrasService $extras,
        private TenantContext $context,
    ) {}

    public function index(Request $request, SampleDataService $samples): Response
    {
        $this->authorize('viewAny', Product::class);

        $outletId = $this->outlet->id($request->user());
        $filter = $request->string('filter')->toString(); // '' | low | habis

        $query = ProductQuery::search(ProductQuery::forOutlet($outletId), $request->string('q')->toString())
            ->when($request->integer('category'), fn ($q, $id) => $q->where('category_id', $id))
            // Bahan baku (untuk resep) dipisah supaya daftar barang jualan tetap rapi.
            ->where('type', $filter === 'bahan' ? '=' : '!=', 'ingredient')
            // Varian ukuran x warna tampil di bawah modelnya; langsung tampil saat dicari atau difilter stok.
            ->when(! $request->filled('q') && ! in_array($filter, ['low', 'habis'], true), fn ($q) => $q->whereNull('parent_id'))
            ->when(in_array($filter, ['low', 'habis'], true), fn ($q) => $q->where('has_variants', false))
            ->when($filter === 'low', fn ($q) => $q
                ->where('track_stock', true)
                ->whereNotNull('min_stock')
                ->whereHas('stocks', fn ($s) => $s->where('outlet_id', $outletId)->whereColumn('stocks.qty', '<=', 'products.min_stock')))
            ->when($filter === 'habis', fn ($q) => $q
                ->where('track_stock', true)
                ->whereIn('type', ['goods', 'ingredient'])
                ->where(fn ($w) => $w
                    ->whereHas('stocks', fn ($s) => $s->where('outlet_id', $outletId)->where('qty', '<=', 0))
                    ->orWhereDoesntHave('stocks', fn ($s) => $s->where('outlet_id', $outletId))))
            ->orderBy('name');

        $products = $query->paginate(30)->withQueryString();

        return Inertia::render('Products/Index', [
            'products' => ProductResource::collection($products->getCollection())->resolve(),
            'variantSummary' => $this->variantSummary($products->getCollection(), $outletId),
            'pagination' => [
                'current' => $products->currentPage(),
                'last' => $products->lastPage(),
                'total' => $products->total(),
                'next_url' => $products->nextPageUrl(),
            ],
            'categories' => Category::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'q' => $request->string('q')->toString(),
                'category' => $request->integer('category') ?: null,
                'filter' => $filter ?: null,
            ],
            'hasSamples' => $samples->hasSamples(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Product::class);

        return Inertia::render('Products/Form', $this->formData(null));
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $product = $this->service->create(
            $request->validated(),
            $this->outlet->id($request->user()),
            $request->file('image'),
        );
        $this->extras->sync($product, $this->extrasData($request), $this->context->get());

        return redirect()->route('products.index')->with('success', "{$product->name} sudah disimpan.");
    }

    public function edit(Request $request, Product $product): Response
    {
        $this->authorize('update', $product);

        $product = ProductQuery::forOutlet($this->outlet->id($request->user()))->findOrFail($product->id);

        return Inertia::render('Products/Form', $this->formData($product));
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $this->service->update($product, $request->validated(), $request->file('image'));
        $this->extras->sync($product, $this->extrasData($request), $this->context->get());
        app(VariantService::class)->refreshFromModel($product->fresh());

        return redirect()->route('products.index')->with('success', "Perubahan {$product->name} sudah disimpan.");
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        $this->service->delete($product);

        return redirect()->route('products.index')->with('undo', [
            'message' => "{$product->name} sudah dihapus.",
            'url' => route('products.restore', $product->id),
        ]);
    }

    public function restore(int $id): RedirectResponse
    {
        $product = Product::onlyTrashed()->findOrFail($id);
        $this->authorize('restore', $product);

        $this->service->restore($product);

        return redirect()->route('products.index')->with('success', "{$product->name} sudah dikembalikan.");
    }

    public function soldOut(Request $request, Product $product, TenantContext $context): RedirectResponse|JsonResponse
    {
        $this->authorize('markSoldOut', $product);
        $request->validate(['sold_out' => ['required', 'boolean']]);

        $soldOut = $request->boolean('sold_out');
        $this->service->setSoldOutToday($product, $this->outlet->id($request->user()), $soldOut, $context->get()->timezone);

        $message = $soldOut
            ? "{$product->name} ditandai habis untuk hari ini. Besok otomatis tersedia lagi."
            : "{$product->name} tersedia lagi.";

        return $request->expectsJson()
            ? response()->json(['message' => $message])
            : back()->with('success', $message);
    }

    public function destroySamples(SampleDataService $samples): RedirectResponse
    {
        $this->authorize('create', Product::class);

        $count = $samples->removeSamples();

        return redirect()->route('products.index')->with('success', "{$count} barang contoh sudah dihapus. Sekarang tambahkan barang Anda sendiri.");
    }

    /** Jumlah varian & total stok per model di halaman ini. */
    private function variantSummary(\Illuminate\Support\Collection $products, int $outletId): array
    {
        $modelIds = $products->where('has_variants', true)->pluck('id');
        if ($modelIds->isEmpty()) {
            return [];
        }

        return Product::query()->whereIn('parent_id', $modelIds)->where('is_active', true)
            ->with(['stocks' => fn ($q) => $q->where('outlet_id', $outletId)])
            ->get()->groupBy('parent_id')
            ->map(fn ($variants) => [
                'count' => $variants->count(),
                'stock' => \App\Core\Support\Qty::display($variants->reduce(fn ($sum, $v) => \App\Core\Support\Qty::add($sum, $v->stocks->first()?->qty ?? '0'), '0')),
                'low' => $variants->filter(fn ($v) => $v->min_stock !== null && \App\Core\Support\Qty::cmp($v->stocks->first()?->qty ?? '0', $v->min_stock) <= 0)->count(),
            ])->all();
    }

    private function formData(?Product $product): array
    {
        return [
            'product' => $product ? ProductResource::make($product)->resolve() : null,
            'categories' => Category::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'units' => Unit::query()->orderBy('id')->get(['id', 'name', 'symbol', 'allow_decimal']),
            'extras' => $this->extras->formData($product),
            'ingredients' => $this->context->get()->hasModule('recipe')
                ? Product::query()->whereIn('type', ['ingredient', 'goods'])->where('track_stock', true)->when($product, fn ($q) => $q->whereKeyNot($product->id))
                    ->with('unit:id,name')->orderBy('name')->get(['id', 'name', 'base_unit_id', 'cost_price'])
                    ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'unit' => $p->unit?->name, 'cost_price' => $p->cost_price])
                : [],
            'priceLevels' => $this->context->get()->hasModule('price_levels') ? PriceLevel::query()->orderBy('sort_order')->get(['id', 'name']) : [],
            // Barang titipan (modul consignment)
            'consignors' => $this->context->get()->hasModule('consignment') ? \App\Modules\Purchasing\Models\Supplier::query()->orderBy('name')->get(['id', 'name']) : [],
            'variantCount' => $product?->has_variants ? $product->variants()->where('is_active', true)->count() : 0,
            'parentName' => $product?->parent_id ? $product->parent?->name : null,
            'modifierGroups' => $this->context->get()->hasModule('variants_modifiers') ? ModifierGroup::query()->with('options:id,modifier_group_id,name')->orderBy('sort_order')->get(['id', 'name']) : [],
        ];
    }

    /**
     * Data bagian tambahan. Form mengirim `extras` = daftar bagian yang tampil, supaya daftar yang
     * dikosongkan (tidak terkirim lewat FormData) tetap dianggap "kosongkan".
     */
    private function extrasData(Request $request): array
    {
        $data = $request->validated();
        foreach ((array) $request->input('extras', []) as $section) {
            if (in_array($section, ['recipe', 'units', 'prices', 'modifier_group_ids'], true)) {
                $data[$section] ??= [];
            }
        }

        return $data;
    }
}
