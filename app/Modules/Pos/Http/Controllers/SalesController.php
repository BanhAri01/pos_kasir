<?php

namespace App\Modules\Pos\Http\Controllers;

use App\Core\Support\Qty;
use App\Core\Support\Rupiah;
use App\Core\Tenancy\CurrentOutlet;
use App\Core\Tenancy\TenantContext;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Pos\Http\Resources\SaleResource;
use App\Modules\Pos\Models\PaymentMethod;
use App\Modules\Pos\Models\Sale;
use App\Modules\Pos\Services\ExchangeService;
use App\Modules\Pos\Services\SaleAdjustmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman Transaksi untuk pemilik & manajer: lihat, cari, batalkan, kembalikan barang.
 */
class SalesController extends Controller
{
    public function index(Request $request, CurrentOutlet $outlet, TenantContext $context): Response
    {
        $this->ensureAllowed($request);
        $timezone = $context->get()->timezone;

        $date = $request->date('tanggal') ?? now($timezone);
        $from = Carbon::parse($date->toDateString(), $timezone)->startOfDay()->utc();
        $to = Carbon::parse($date->toDateString(), $timezone)->endOfDay()->utc();

        $query = Sale::query()
            ->where('outlet_id', $outlet->id($request->user()))
            ->whereBetween('completed_at', [$from, $to])
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where('number', 'like', "%{$term}%"))
            ->when($request->string('status')->toString() === 'void', fn ($q) => $q->where('status', 'void'));

        $summary = (clone $query)->where('status', 'completed')
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total), 0) as total, COALESCE(SUM(refunded_amount), 0) as refunded')
            ->first();

        $sales = $query->with(['cashier:id,name', 'customer', 'items', 'payments'])->latest('completed_at')->paginate(30)->withQueryString();

        return Inertia::render('Sales/Index', [
            'sales' => SaleResource::collection($sales->getCollection())->resolve(),
            'pagination' => ['current' => $sales->currentPage(), 'last' => $sales->lastPage()],
            'summary' => [
                'count' => (int) $summary->count,
                'total' => (int) $summary->total - (int) $summary->refunded,
            ],
            'filters' => [
                'tanggal' => $date->toDateString(),
                'q' => $request->string('q')->toString(),
                'status' => $request->string('status')->toString() ?: null,
            ],
        ]);
    }

    public function show(Request $request, string $uuid): Response
    {
        $this->ensureAllowed($request);
        $sale = Sale::query()->where('uuid', $uuid)->with(['items', 'payments', 'cashier:id,name', 'customer', 'outlet:id,name', 'refunds.user:id,name'])->firstOrFail();

        return Inertia::render('Sales/Show', [
            'sale' => SaleResource::make($sale)->resolve(),
            'refunds' => $sale->refunds->map(fn ($r) => [
                'number' => $r->number, 'amount' => $r->amount, 'reason' => $r->reason, 'user' => $r->user?->name,
            ]),
            'paymentMethods' => PaymentMethod::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'can' => [
                'void' => $request->user()->can(Permission::VoidSale->value),
                'refund' => $request->user()->can(Permission::RefundSale->value),
            ],
        ]);
    }

    public function void(Request $request, string $uuid, SaleAdjustmentService $service): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']], ['reason.required' => 'Tulis alasan pembatalan.']);
        $sale = Sale::query()->where('uuid', $uuid)->firstOrFail();

        $service->void($sale, $data['reason'], $request->user());

        return back()->with('success', "Transaksi {$sale->number} sudah dibatalkan. Stok sudah dikembalikan.");
    }

    public function refund(Request $request, string $uuid, SaleAdjustmentService $service): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'restock' => ['boolean'],
            'payment_method_id' => ['nullable', 'integer'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sale_item_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
        ], [
            'reason.required' => 'Tulis alasan pengembalian.',
            'items.required' => 'Isi jumlah barang yang dikembalikan.',
        ]);

        $sale = Sale::query()->where('uuid', $uuid)->firstOrFail();
        $refund = $service->refund($sale, $data['items'], $data['reason'], $data['restock'] ?? true, $data['payment_method_id'] ?? null, $request->user());

        return back()->with('success', 'Pengembalian dicatat. Kembalikan uang '.Rupiah::format($refund->amount).' ke pembeli.');
    }

    /** Tukar barang (modul returns_exchange): pilih barang yang dikembalikan, lalu barang penggantinya. */
    public function exchange(Request $request, string $uuid): Response
    {
        $this->ensureAllowed($request);
        abort_unless($request->user()->can(Permission::RefundSale->value), 403);
        $sale = Sale::query()->where('uuid', $uuid)->with(['items.product:id,parent_id', 'customer'])->firstOrFail();
        abort_if($sale->isVoid(), 404);

        $itemsNetTotal = max(1, (int) $sale->items->sum('subtotal'));
        $outletId = $sale->outlet_id;

        return Inertia::render('Sales/Exchange', [
            'sale' => [
                'uuid' => $sale->uuid,
                'number' => $sale->number,
                'customer' => $sale->customer?->name,
                'items' => $sale->items->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'unit' => $item->unit_name,
                    'parent_id' => $item->product?->parent_id,
                    'refundable_qty' => (float) Qty::sub($item->qty, $item->refunded_qty),
                    // Nilai kembali per 1 barang, rumus sama dengan pengembalian (sudah termasuk diskon & pajak nota).
                    'unit_value' => (int) round($sale->total * ($item->subtotal / max(0.001, (float) $item->qty)) / $itemsNetTotal),
                ])->filter(fn ($i) => $i['refundable_qty'] > 0)->values(),
            ],
            'products' => Product::query()->where('is_active', true)->where('has_variants', false)->where('type', 'goods')
                ->with(['stocks' => fn ($q) => $q->where('outlet_id', $outletId)])
                ->orderBy('name')->get()
                ->map(fn (Product $p) => [
                    'id' => $p->id, 'name' => $p->name, 'price' => $p->price, 'parent_id' => $p->parent_id,
                    'barcode' => $p->barcode, 'code' => $p->code,
                    'stock' => $p->tracksStock() ? Qty::display($p->stocks->first()?->qty ?? '0') : null,
                ]),
            'paymentMethods' => PaymentMethod::query()->where('is_active', true)->where('type', '!=', 'kasbon')->orderBy('sort_order')->get(['id', 'name', 'type']),
        ]);
    }

    public function storeExchange(Request $request, string $uuid, ExchangeService $service, TenantContext $context): RedirectResponse
    {
        $this->ensureAllowed($request);
        $clean = fn (string $key) => array_map(
            fn ($row) => is_array($row) ? [...$row, 'qty' => Qty::fromInput($row['qty'] ?? null)] : $row,
            array_values((array) $request->input($key, [])),
        );
        $request->merge(['returns' => $clean('returns'), 'items' => $clean('items')]);

        $data = $request->validate([
            'returns' => ['required', 'array', 'min:1'],
            'returns.*.sale_item_id' => ['required', 'integer'],
            'returns.*.qty' => ['required', 'numeric', 'gt:0'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('tenant_id', $context->id())],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'payment_method_id' => ['nullable', 'integer', Rule::exists('payment_methods', 'id')->where('tenant_id', $context->id())],
            'reason' => ['nullable', 'string', 'max:255'],
        ], [
            'returns.required' => 'Pilih barang yang dikembalikan pembeli.',
            'items.required' => 'Pilih barang penggantinya.',
        ]);

        $sale = Sale::query()->where('uuid', $uuid)->firstOrFail();
        $result = $service->exchange($sale, $data['returns'], $data['items'], $data['payment_method_id'] ?? null, $request->user(), $data['reason'] ?? null);

        $diff = $result['difference'];
        $message = match (true) {
            $diff > 0 => 'Pembeli menambah '.Rupiah::format($diff).'.',
            $diff < 0 => 'Kembalikan uang '.Rupiah::format(-$diff).' ke pembeli.',
            default => 'Harga sama, tidak ada uang yang ditambah atau dikembalikan.',
        };

        return redirect()->route('sales.show', $result['sale']->uuid)->with('success', "Tukar barang selesai (nota baru {$result['sale']->number}). {$message}");
    }

    private function ensureAllowed(Request $request): void
    {
        abort_unless($request->user()->can(Permission::ViewReports->value), 403);
    }
}
