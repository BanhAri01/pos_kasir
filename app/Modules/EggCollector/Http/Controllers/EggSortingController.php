<?php

namespace App\Modules\EggCollector\Http\Controllers;

use App\Core\Support\Qty;
use App\Core\Tenancy\CurrentOutlet;
use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\EggCollector\Models\EggSorting;
use App\Modules\EggCollector\Services\EggPriceService;
use App\Modules\EggCollector\Services\EggSortingService;
use App\Modules\Inventory\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Sortir telur campur: dari menu Sortir atau dari kasir saat stok jenis yang dipesan kurang. */
class EggSortingController extends Controller
{
    public function __construct(
        private CurrentOutlet $outlet,
        private TenantContext $context,
        private EggSortingService $sorting,
        private EggPriceService $prices,
        private StockService $stock,
    ) {}

    public function create(Request $request): Response
    {
        $this->authorizeSorting($request->user());
        $outletId = $this->outlet->id($request->user());
        abort_unless($outletId, 404);

        $eggs = $this->prices->eggProducts();
        $products = $eggs->pluck('product');
        $sell = $this->prices->sellPrices($outletId, $products);
        $timezone = $this->context->get()->timezone;
        $row = fn ($egg) => [
            'product_id' => $egg->product->id,
            'name' => $egg->product->name,
            'unit' => $egg->product->unit?->name ?? 'Kg',
            'stock' => Qty::display($this->stock->qty($outletId, $egg->product)),
            'sell_price' => $sell[$egg->product->id],
        ];

        return Inertia::render('Pengepul/Sorting', [
            'mixed' => $eggs->where('role', 'mixed')->map($row)->values(),
            'grades' => $eggs->where('role', 'grade')->map($row)->values(),
            'prefill' => [
                'product_id' => $request->integer('untuk') ?: null,
                'qty' => $request->filled('kg') ? Qty::display(Qty::normalize(Qty::fromInput((string) $request->query('kg')))) : null,
            ],
            'recent' => EggSorting::query()->where('outlet_id', $outletId)->with('items.product:id,name')->latest('id')->limit(10)->get()
                ->map(fn (EggSorting $s) => $this->summary($s, $timezone))->values(),
            'can_see_cost' => $request->user()->can('view_reports'),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $this->authorizeSorting($user);
        $outletId = $this->outlet->id($user);
        abort_unless($outletId, 404);

        $request->merge([
            'input_qty' => Qty::fromInput($request->input('input_qty')),
            'outputs' => collect((array) $request->input('outputs', []))->map(fn ($row) => is_array($row) ? ['product_id' => $row['product_id'] ?? null, 'qty' => Qty::fromInput($row['qty'] ?? null)] : $row)->all(),
        ]);

        $tenantId = $this->context->id();
        $data = $request->validate([
            'mixed_product_id' => ['nullable', 'integer', Rule::exists('egg_products', 'product_id')->where('tenant_id', $tenantId)->where('role', 'mixed')],
            'input_qty' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'outputs' => ['required', 'array', 'min:1', 'max:15'],
            'outputs.*.product_id' => ['required', 'integer', Rule::exists('egg_products', 'product_id')->where('tenant_id', $tenantId)->where('role', 'grade')],
            'outputs.*.qty' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'note' => ['nullable', 'string', 'max:200'],
            'kembali' => ['nullable', 'string', Rule::in(['kasir'])],
        ], [
            'input_qty.required' => 'Isi berapa kg telur campur yang disortir.',
            'input_qty.gt' => 'Berat telur campur harus lebih dari 0.',
        ]);

        $sorting = $this->sorting->sort($data, $outletId, $user);
        $message = "Sortir {$sorting->number} tersimpan. Stok telur sudah diperbarui.";

        if ($request->expectsJson()) {
            $products = collect([$sorting->mixed_product_id])->merge($sorting->items->pluck('product_id'))->unique();

            return response()->json([
                'message' => $message,
                'number' => $sorting->number,
                'stocks' => $products->map(function (int $id) use ($outletId, $sorting) {
                    $product = $id === $sorting->mixed_product_id ? $sorting->mixed : $sorting->items->firstWhere('product_id', $id)->product;
                    $qty = $this->stock->qty($outletId, $product);

                    return ['product_id' => $id, 'stock_raw' => (float) $qty, 'stock' => Qty::display($qty)];
                })->values(),
            ], 201);
        }

        return ($data['kembali'] ?? null) === 'kasir'
            ? redirect()->route('pos.show')->with('success', $message)
            : redirect()->route('egg.sortings.create')->with('success', $message);
    }

    public function show(Request $request, string $uuid): Response
    {
        $this->authorizeSorting($request->user());
        $sorting = EggSorting::query()->where('uuid', $uuid)->with(['items.product:id,name', 'mixed:id,name', 'user:id,name'])->firstOrFail();

        return Inertia::render('Pengepul/SortingShow', [
            'sorting' => $this->summary($sorting, $this->context->get()->timezone, $request->user()->can('view_reports')),
        ]);
    }

    private function summary(EggSorting $s, string $timezone, bool $withCost = false): array
    {
        return [
            'uuid' => $s->uuid,
            'number' => $s->number,
            'date' => $s->created_at->timezone($timezone)->locale('id')->translatedFormat('j M Y, H:i'),
            'mixed' => $s->mixed?->name,
            'user' => $s->user?->name,
            'input_qty' => Qty::display($s->input_qty),
            'output_qty' => Qty::display($s->output_qty),
            'shrinkage_qty' => Qty::display($s->shrinkage_qty),
            'note' => $s->note,
            'items' => $s->items->map(fn ($i) => array_filter([
                'name' => $i->product?->name,
                'qty' => Qty::display($i->qty),
                'unit_cost' => $withCost ? $i->unit_cost : null,
                'total_cost' => $withCost ? $i->total_cost : null,
            ], fn ($v) => $v !== null))->values(),
            'input_cost' => $withCost ? $s->input_cost : null,
            'shrinkage_cost' => $withCost ? $s->shrinkage_cost : null,
        ];
    }

    private function authorizeSorting(User $user): void
    {
        abort_unless($user->can('manage_stock') || $user->can('use_pos'), 403);
    }
}
