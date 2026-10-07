<?php

namespace App\Modules\Pos\Services;

use App\Core\Support\Phone;
use App\Core\Support\Qty;
use App\Models\Device;
use App\Models\Outlet;
use App\Models\User;
use App\Modules\Catalog\Http\Resources\ProductResource;
use App\Modules\Catalog\Models\Promotion;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\ProductQuery;
use App\Modules\Customer\Models\Customer;
use App\Modules\Operations\Models\DiningTable;
use App\Modules\Operations\Models\MembershipPlan;
use App\Modules\Operations\Models\Receivable;
use App\Modules\Pos\Models\PaymentMethod;
use Illuminate\Support\Facades\DB;

/**
 * Semua data yang dibutuhkan layar kasir dalam satu kali ambil.
 * Data ini disimpan di HP (IndexedDB) supaya kasir tetap jalan saat offline.
 * Data modul (satuan, harga khusus, pilihan, meja, ...) hanya disertakan kalau modulnya menyala.
 */
class PosBootstrapService
{
    public function __construct(
        private ShiftService $shifts,
        private ApprovalService $approval,
    ) {}

    public function build(User $user, Outlet $outlet, ?Device $device): array
    {
        $tenant = $user->tenant;
        $has = fn (string $code) => $tenant->hasModule($code);
        $shift = $this->shifts->currentFor($user, $outlet->id);

        $products = ProductQuery::forOutlet($outlet->id)
            ->where('is_active', true)
            ->where('type', '!=', 'ingredient')
            ->when($has('multi_unit'), fn ($q) => $q->with('units.unit:id,name'))
            ->when($has('price_levels'), fn ($q) => $q->with('prices'))
            ->when($has('variants_modifiers'), fn ($q) => $q->with('modifierGroups.options'))
            ->orderBy('name')
            ->get()
            ->keyBy('id');

        $outletPrices = DB::table('product_outlet')->where('outlet_id', $outlet->id)->whereNotNull('price')->pluck('price', 'product_id');
        $membershipProducts = $has('membership') ? MembershipPlan::query()->where('is_active', true)->pluck('product_id')->flip() : collect();

        $productData = collect(ProductResource::collection($products->values())->resolve())->map(function (array $p) use ($products, $outletPrices, $membershipProducts, $has) {
            /** @var Product $model */
            $model = $products[$p['id']];

            return [
                ...$p,
                'price' => (int) ($outletPrices[$p['id']] ?? $p['price']),
                'is_membership' => $membershipProducts->has($p['id']),
                'units' => $has('multi_unit') ? $model->units->map(fn ($u) => [
                    'id' => $u->id, 'name' => $u->unit?->name, 'conversion' => (float) $u->conversion_qty, 'price' => $u->price, 'barcode' => $u->barcode,
                ])->values() : [],
                'prices' => $has('price_levels') ? $model->prices->map(fn ($t) => [
                    'unit_id' => $t->product_unit_id, 'level_id' => $t->price_level_id, 'min_qty' => (float) $t->min_qty, 'price' => $t->price,
                ])->values() : [],
                'modifier_groups' => $has('variants_modifiers') ? $model->modifierGroups->map(fn ($g) => [
                    'id' => $g->id, 'name' => $g->name, 'selection' => $g->selection, 'is_required' => $g->is_required,
                    'options' => $g->options->where('is_active', true)->map(fn ($o) => ['id' => $o->id, 'name' => $o->name, 'price_delta' => $o->price_delta])->values(),
                ])->values() : [],
            ];
        })->values();

        // Sisa utang per pelanggan (untuk terima pembayaran kasbon di kasir).
        $balances = $has('kasbon') || $has('credit_sales')
            ? Receivable::query()->where('status', 'open')->groupBy('customer_id')->selectRaw('customer_id, SUM(amount - paid_amount) as balance')->pluck('balance', 'customer_id')
            : collect();

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'role_label' => $user->roleLabel(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
                'preferences' => $user->preferences ?? [],
            ],
            'tenant' => [
                'name' => $tenant->name,
                'business_type' => $tenant->businessType->code,
                'pos_layout' => $tenant->businessType->pos_layout,
                'timezone' => $tenant->timezone,
                'modules' => $tenant->enabledModuleCodes(),
            ],
            'outlet' => [
                'id' => $outlet->id,
                'name' => $outlet->name,
                'address' => $outlet->address,
                'phone' => $outlet->phone,
                'receipt_header' => $outlet->receipt_header,
                'receipt_footer' => $outlet->receipt_footer,
                'receipt_paper' => $outlet->receipt_paper,
                'tax_rate_bp' => $outlet->tax_rate_bp,
                'tax_inclusive' => $outlet->tax_inclusive,
                'service_charge_bp' => $outlet->service_charge_bp,
            ],
            'device' => $device ? ['id' => $device->id, 'code' => $device->code] : null,
            'categories' => Category::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'products' => $productData,
            // Promo yang berlaku hari ini atau nanti (HP kasir memilih sendiri sesuai tanggal, juga saat offline).
            'promotions' => $has('promotions') ? Promotion::query()->where('is_active', true)
                ->whereDate('ends_on', '>=', now($tenant->timezone)->toDateString())->get()
                ->map(fn (Promotion $p) => [
                    'name' => $p->name, 'type' => $p->type, 'value' => $p->value, 'scope' => $p->scope,
                    'category_ids' => $p->category_ids ?? [], 'product_ids' => $p->product_ids ?? [],
                    'starts_on' => $p->starts_on->toDateString(), 'ends_on' => $p->ends_on->toDateString(),
                ])->values() : [],
            'payment_methods' => PaymentMethod::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'type']),
            // Pelanggan disimpan di HP supaya bisa dicari walau offline (dibatasi 2.000 terbaru).
            'customers' => Customer::query()->latest('updated_at')->limit(2000)->get(['id', 'uuid', 'name', 'phone', 'price_level_id', 'credit_limit'])
                ->map(fn (Customer $c) => [
                    'uuid' => $c->uuid, 'name' => $c->name, 'phone' => $c->phone, 'phone_display' => Phone::display($c->phone),
                    'price_level_id' => $c->price_level_id, 'credit_limit' => $c->credit_limit,
                    'balance' => (int) ($balances[$c->id] ?? 0),
                ]),
            // Karyawan yang bisa dipilih sebagai pengerja layanan (kapster, terapis, trainer).
            'staff' => User::query()->where('tenant_id', $tenant->id)->where('is_active', true)
                ->whereHas('outlets', fn ($q) => $q->where('outlets.id', $outlet->id))
                ->orderBy('name')->get(['id', 'name', 'job_title'])
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'job_title' => $u->job_title]),
            'tables' => $has('tables') ? DiningTable::query()->where('outlet_id', $outlet->id)->where('is_active', true)
                ->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'area']) : [],
            'shift' => $shift ? [
                'uuid' => $shift->uuid,
                'opened_at' => $shift->opened_at->toIso8601String(),
                'opening_cash' => $shift->opening_cash,
            ] : null,
            'approvers' => $this->approval->approvers($user->tenant_id),
            'server_time' => now()->toIso8601String(),
        ];
    }
}
