<?php

namespace App\Modules\Purchasing\Http\Controllers;

use App\Core\Support\Qty;
use App\Core\Tenancy\CurrentOutlet;
use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Pos\Models\PaymentMethod;
use App\Modules\Purchasing\Models\Purchase;
use App\Modules\Purchasing\Models\Supplier;
use App\Modules\Purchasing\Services\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Belanja ke pemasok: catat barang masuk + harga beli, lunas / utang, dan bayar utang belakangan. */
class PurchaseController extends Controller
{
    public function __construct(private CurrentOutlet $outlet, private TenantContext $context) {}

    public function index(Request $request): Response
    {
        $filter = $request->string('status')->toString();

        return Inertia::render('Purchasing/Purchases', [
            'purchases' => Purchase::query()
                ->where('outlet_id', $this->outlet->id($request->user()))
                ->when($filter === 'utang', fn ($q) => $q->where('payment_status', '!=', 'paid'))
                ->with('supplier:id,name')
                ->latest('purchased_on')->latest('id')
                ->paginate(30)->withQueryString()
                ->through(fn (Purchase $p) => [
                    'uuid' => $p->uuid, 'number' => $p->number, 'supplier' => $p->supplier?->name ?? 'Tanpa pemasok',
                    'date' => $p->purchased_on->locale('id')->translatedFormat('j M Y'), 'total' => $p->total,
                    'due' => $p->total - $p->paid_amount, 'payment_status' => $p->payment_status,
                    'due_date' => $p->due_date?->locale('id')->translatedFormat('j M Y'),
                    'overdue' => $p->due_date && $p->payment_status !== 'paid' && $p->due_date->isPast(),
                ]),
            'totalDebt' => (int) Purchase::query()->where('payment_status', '!=', 'paid')->sum(\Illuminate\Support\Facades\DB::raw('total - paid_amount')),
            'filter' => $filter,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Purchasing/PurchaseForm', [
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name', 'default_pack_weight'])
                ->map(fn (Supplier $s) => ['id' => $s->id, 'name' => $s->name, 'pack_weight' => $s->default_pack_weight !== null ? Qty::display($s->default_pack_weight) : null]),
            'products' => Product::query()->whereIn('type', ['goods', 'ingredient'])->where('track_stock', true)
                ->with(['units.unit:id,name', 'unit:id,name,allow_decimal'])->orderBy('name')->get()
                ->map(fn (Product $p) => [
                    'id' => $p->id, 'name' => $p->name, 'cost' => $p->cost_price, 'unit' => $p->unit?->name ?? 'pcs',
                    'unit_allows_decimal' => (bool) $p->unit?->allow_decimal, 'track_batch' => $p->track_batch, 'pack_size' => $p->pack_size !== null ? Qty::display($p->pack_size) : null,
                    'units' => $p->units->map(fn ($u) => ['id' => $u->id, 'name' => $u->unit?->name, 'conversion' => Qty::display($u->conversion_qty)])->values(),
                ]),
            'paymentMethods' => PaymentMethod::query()->where('is_active', true)->where('type', '!=', 'kasbon')->orderBy('sort_order')->get(['id', 'name']),
            'today' => now($this->context->get()->timezone)->toDateString(),
        ]);
    }

    public function store(Request $request, PurchaseService $service): RedirectResponse
    {
        $tenantId = $this->context->id();

        // Jumlah & berat boleh diketik dengan koma ("1,5" / "1.245,5").
        if (is_array($request->input('items'))) {
            $request->merge(['items' => array_map(
                fn ($item) => is_array($item) ? array_merge($item, array_map(
                    [Qty::class, 'fromInput'],
                    array_intersect_key($item, array_flip(['qty', 'pack_count', 'pack_weight', 'received_qty', 'moisture'])),
                )) : $item,
                array_values($request->input('items')),
            )]);
        }

        $data = $request->validate([
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->where('tenant_id', $tenantId)],
            'supplier_invoice_no' => ['nullable', 'string', 'max:50'],
            'purchased_on' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
            'paid_amount' => ['required', 'integer', 'min:0'],
            'payment_method_id' => ['nullable', 'integer', Rule::exists('payment_methods', 'id')->where('tenant_id', $tenantId)],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('tenant_id', $tenantId)],
            'items.*.unit_id' => ['nullable', 'integer', Rule::exists('product_units', 'id')->where('tenant_id', $tenantId)],
            'items.*.qty' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'items.*.unit_cost' => ['required', 'integer', 'min:0'],
            // Timbangan (weighed_receiving) & biaya tambahan (landed_cost)
            'items.*.pack_count' => ['nullable', 'numeric', 'gt:0', 'max:9999999'],
            'items.*.pack_weight' => ['nullable', 'numeric', 'gt:0', 'max:9999999'],
            'items.*.received_qty' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'items.*.batch_no' => ['nullable', 'string', 'max:40'],
            'items.*.expires_at' => ['nullable', 'date'],
            'items.*.moisture' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.quality_note' => ['nullable', 'string', 'max:255'],
            'freight_cost' => ['nullable', 'integer', 'min:0'],
            'unloading_cost' => ['nullable', 'integer', 'min:0'],
            'other_cost' => ['nullable', 'integer', 'min:0'],
        ], [
            'items.required' => 'Tambahkan minimal satu barang yang dibeli.',
            'items.*.qty.gt' => 'Jumlah harus lebih dari 0.',
            'items.*.unit_cost.required' => 'Isi harga belinya.',
            'items.*.pack_count.gt' => 'Jumlah karung harus lebih dari 0.',
            'items.*.pack_weight.gt' => 'Berat per karung harus lebih dari 0.',
            'items.*.received_qty.numeric' => 'Berat timbangan harus berupa angka. Contoh: 1.245,5',
            'items.*.moisture.max' => 'Kadar air maksimal 100%.',
        ]);

        $purchase = $service->create([...$data, 'outlet_id' => $this->outlet->id($request->user())], $request->user());

        return redirect()->route('purchases.show', $purchase->uuid)->with('success', "Belanja {$purchase->number} tersimpan. Stok sudah bertambah.");
    }

    public function show(string $uuid): Response
    {
        $purchase = Purchase::query()->where('uuid', $uuid)->with(['items.product:id,name,base_unit_id', 'items.product.unit:id,name', 'payments', 'supplier', 'user:id,name'])->firstOrFail();
        $timezone = $this->context->get()->timezone;

        return Inertia::render('Purchasing/PurchaseShow', [
            'purchase' => [
                'uuid' => $purchase->uuid, 'number' => $purchase->number, 'supplier' => $purchase->supplier?->name,
                'supplier_invoice_no' => $purchase->supplier_invoice_no,
                'date' => $purchase->purchased_on->locale('id')->translatedFormat('l, j F Y'),
                'total' => $purchase->total, 'paid_amount' => $purchase->paid_amount, 'due' => $purchase->total - $purchase->paid_amount,
                'payment_status' => $purchase->payment_status, 'due_date' => $purchase->due_date?->locale('id')->translatedFormat('j M Y'),
                'note' => $purchase->note, 'user' => $purchase->user?->name,
                'extra_costs' => array_filter([
                    'Ongkos angkut' => $purchase->freight_cost,
                    'Bongkar muat' => $purchase->unloading_cost,
                    'Biaya lain' => $purchase->other_cost,
                ]),
                'items' => $purchase->items->map(fn ($i) => [
                    'name' => $i->product?->name, 'qty' => Qty::display($i->qty), 'unit_cost' => $i->unit_cost, 'subtotal' => $i->subtotal,
                    'base_unit' => $i->product?->unit?->name ?? '',
                    'pack_count' => $i->pack_count !== null ? Qty::display($i->pack_count) : null,
                    'pack_weight' => $i->pack_weight !== null ? Qty::display($i->pack_weight) : null,
                    'received_qty' => $i->received_qty !== null ? Qty::display($i->received_qty) : null,
                    'weight_diff' => Qty::isZero($i->weight_diff) ? null : Qty::display($i->weight_diff),
                    'weight_short' => Qty::isNegative($i->weight_diff),
                    'extra_cost' => $i->extra_cost,
                    'landed_unit_cost' => $i->landed_unit_cost,
                    'unit' => Qty::cmp($i->conversion_qty, '1') === 0 ? ($i->product?->unit?->name ?? '') : 'x'.Qty::display($i->conversion_qty),
                ]),
                'payments' => $purchase->payments->map(fn ($p) => ['amount' => $p->amount, 'date' => $p->paid_at->timezone($timezone)->locale('id')->translatedFormat('j M Y H:i')]),
            ],
            'paymentMethods' => PaymentMethod::query()->where('is_active', true)->where('type', '!=', 'kasbon')->orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    public function pay(Request $request, string $uuid, PurchaseService $service): RedirectResponse
    {
        $data = $request->validate(['amount' => ['required', 'integer', 'min:1'], 'payment_method_id' => ['nullable', 'integer']]);
        $purchase = $service->pay(Purchase::query()->where('uuid', $uuid)->firstOrFail(), $data['amount'], $data['payment_method_id'] ?? null, $request->user());

        return back()->with('success', $purchase->payment_status === 'paid' ? 'Utang ke pemasok sudah lunas.' : 'Pembayaran ke pemasok dicatat.');
    }
}
