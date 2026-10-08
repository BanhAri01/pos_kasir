<?php

namespace App\Modules\Operations\Services;

use App\Core\Support\Phone;
use App\Core\Support\Rupiah;
use App\Core\Tenancy\TenantContext;
use App\Models\Outlet;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Billing\Payments\ChargeRequest;
use App\Modules\Billing\Payments\Gateways;
use App\Modules\Billing\Payments\PaymentGateway;
use App\Modules\Billing\Payments\PaymentResult;
use App\Modules\Billing\Services\PaymentFee;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\ProductQuery;
use App\Modules\Operations\Models\DiningTable;
use App\Modules\Operations\Models\SelfOrder;
use App\Modules\Pos\Models\PaymentMethod;
use App\Modules\Pos\Models\Sale;
use App\Modules\Pos\Services\PriceResolver;
use App\Modules\Pos\Services\SaleCalculator;
use App\Modules\Pos\Services\SaleService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SelfOrderService
{
    public const MAX_OPEN_PER_TABLE = 4;

    public function __construct(
        private TenantContext $context,
        private PriceResolver $prices,
        private SaleCalculator $calculator,
        private Gateways $gateways,
    ) {}

    public static function tableByToken(string $token): ?DiningTable
    {
        if (! preg_match('/^[A-Za-z0-9]{20,40}$/', $token)) {
            return null;
        }

        return DiningTable::query()->withoutGlobalScopes()->where('qr_token', $token)->where('is_active', true)->first();
    }

    public function isOpen(Tenant $tenant): bool
    {
        return $tenant->hasAccess() && $tenant->hasModule('qr_order') && $tenant->hasModule('tables');
    }

    public function onlinePaymentAvailable(Tenant $tenant): bool
    {
        return $tenant->qr_payment === 'online' && $this->gateways->forTenant($tenant) !== null;
    }

    public function menuProducts(Outlet $outlet): Collection
    {
        $tenant = $this->context->get();
        $today = now($tenant->timezone)->toDateString();

        return ProductQuery::forOutlet($outlet->id)
            ->where('is_active', true)
            ->whereIn('type', ['goods', 'service', 'package'])
            ->where('pricing_mode', 'fixed')
            ->where('has_variants', false)
            ->when($tenant->hasModule('variants_modifiers'), fn ($q) => $q->with('modifierGroups.options'))
            ->orderBy('name')
            ->get()
            ->filter(function (Product $product) use ($today) {
                $pivot = $product->outlets->first()?->pivot;
                if ($pivot && (! $pivot->is_available || substr((string) $pivot->sold_out_on, 0, 10) === $today)) {
                    return false;
                }
                $stock = $product->stocks->first()?->qty;

                return ! ($product->tracksStock() && $stock !== null && (float) $stock <= 0);
            })
            ->keyBy('id');
    }

    public function menu(Outlet $outlet): array
    {
        $tenant = $this->context->get();
        $products = $this->menuProducts($outlet);
        $this->prices->prepare($tenant, $outlet->id, $products, null, now($tenant->timezone)->toDateString());

        $items = $products->map(function (Product $product) use ($tenant) {
            $resolved = $this->prices->resolve($product, '1.000', null, [], false);

            return [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'category_id' => $product->category_id,
                'price' => $resolved['unit_price'],
                'promo' => $resolved['promo']['name'] ?? null,
                'image_url' => $product->imageUrl(),
                'modifier_groups' => $tenant->hasModule('variants_modifiers') ? $product->modifierGroups->map(fn ($g) => [
                    'id' => $g->id,
                    'name' => $g->name,
                    'selection' => $g->selection,
                    'is_required' => (bool) $g->is_required,
                    'options' => $g->options->where('is_active', true)->map(fn ($o) => ['id' => $o->id, 'name' => $o->name, 'price_delta' => $o->price_delta])->values(),
                ])->values() : [],
            ];
        })->values();

        $categoryIds = $items->pluck('category_id')->filter()->unique();

        return [
            'categories' => Category::query()->whereIn('id', $categoryIds)->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'products' => $items,
        ];
    }

    public function place(DiningTable $table, array $data, ?string $ip): SelfOrder
    {
        $outlet = Outlet::query()->findOrFail($table->outlet_id);

        $open = SelfOrder::query()->where('table_id', $table->id)->where('status', 'new')
            ->where('created_at', '>=', now()->subHours(2))->count();
        if ($open >= self::MAX_OPEN_PER_TABLE) {
            throw ValidationException::withMessages(['items' => 'Masih ada beberapa pesanan dari meja ini yang belum diproses. Tunggu sebentar atau panggil pelayan.']);
        }

        return $this->build($outlet, $table, $data, $ip, ['order_type' => 'dine_in']);
    }

    public function isStoreOpen(Tenant $tenant, Outlet $outlet): bool
    {
        return $tenant->hasAccess() && $tenant->hasModule('online_order') && $outlet->online_open && ($outlet->online_pickup || $outlet->online_delivery);
    }

    public function placeOnline(Outlet $outlet, array $data, ?string $ip): SelfOrder
    {
        $phone = Phone::normalize($data['customer_phone'] ?? null);
        if (! $phone) {
            throw ValidationException::withMessages(['customer_phone' => 'No WhatsApp sepertinya salah. Contoh: 0812 3456 7890.']);
        }

        $delivery = ($data['order_type'] ?? 'take_away') === 'delivery';
        if (($delivery && ! $outlet->online_delivery) || (! $delivery && ! $outlet->online_pickup)) {
            throw ValidationException::withMessages(['order_type' => $delivery ? 'Toko ini sedang tidak melayani pesan antar.' : 'Toko ini sedang tidak melayani ambil sendiri.']);
        }
        if ($delivery && blank($data['address'] ?? null)) {
            throw ValidationException::withMessages(['address' => 'Tulis alamat pengantaran.']);
        }

        $open = SelfOrder::query()->whereNull('table_id')->where('customer_phone', $phone)->where('status', 'new')
            ->where('created_at', '>=', now()->subHours(2))->count();
        if ($open >= self::MAX_OPEN_PER_TABLE) {
            throw ValidationException::withMessages(['items' => 'Masih ada pesanan Anda yang belum diproses. Tunggu sebentar atau hubungi toko.']);
        }

        return $this->build($outlet, null, $data, $ip, [
            'order_type' => $delivery ? 'delivery' : 'take_away',
            'customer_phone' => $phone,
            'address' => $delivery ? Str::limit(trim($data['address']), 300, '') : null,
            'delivery_fee' => $delivery ? (int) $outlet->delivery_fee : 0,
            'min_order' => (int) $outlet->online_min_order,
        ]);
    }

    private function build(Outlet $outlet, ?DiningTable $table, array $data, ?string $ip, array $extra): SelfOrder
    {
        $tenant = $this->context->get();

        $payOnline = ($data['pay_method'] ?? 'cashier') === 'online';
        if ($payOnline && ! $this->onlinePaymentAvailable($tenant)) {
            throw ValidationException::withMessages(['pay_method' => 'Bayar online sedang tidak tersedia. Silakan bayar di kasir.']);
        }

        $products = $this->menuProducts($outlet);
        $this->prices->prepare($tenant, $outlet->id, $products, null, now($tenant->timezone)->toDateString());

        $lines = [];
        foreach ($data['items'] as $item) {
            $product = $products->get((int) $item['product_id']);
            if (! $product) {
                throw ValidationException::withMessages(['items' => 'Ada menu yang baru saja habis. Muat ulang halaman lalu pesan lagi.']);
            }

            $qty = (int) $item['qty'];
            $resolved = $this->prices->resolve($product, $qty.'.000', null, array_map('intval', $item['modifier_ids'] ?? []), true);

            $lines[] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'qty' => $qty,
                'unit_price' => $resolved['unit_price'],
                'modifier_ids' => array_column($resolved['modifiers'], 'modifier_option_id'),
                'modifiers' => array_column($resolved['modifiers'], 'name'),
                'note' => filled($item['note'] ?? null) ? Str::limit(trim($item['note']), 150, '') : null,
                'subtotal' => $resolved['unit_price'] * $qty,
            ];
        }

        $itemsTotal = array_sum(array_column($lines, 'subtotal'));
        if (($extra['min_order'] ?? 0) > 0 && $itemsTotal < $extra['min_order']) {
            throw ValidationException::withMessages(['items' => 'Minimal pesanan '.Rupiah::format($extra['min_order']).'.']);
        }

        $deliveryFee = (int) ($extra['delivery_fee'] ?? 0);
        $calcLines = $deliveryFee > 0 ? [...$lines, ['unit_price' => $deliveryFee, 'qty' => 1]] : $lines;
        $totals = $this->calculator->calculate($calcLines, null, 0, $outlet->service_charge_bp, $outlet->tax_rate_bp, $outlet->tax_inclusive);
        $fee = $payOnline ? PaymentFee::feeFor($totals['total'], 'qris') : 0;

        $order = SelfOrder::create([
            'outlet_id' => $outlet->id,
            'table_id' => $table?->id,
            'order_type' => $extra['order_type'],
            'customer_phone' => $extra['customer_phone'] ?? null,
            'address' => $extra['address'] ?? null,
            'delivery_fee' => $deliveryFee,
            'uuid' => (string) Str::uuid(),
            'code' => ($table ? 'Q' : 'T').Str::upper(Str::random(4)),
            'customer_name' => Str::limit(trim($data['customer_name']), 60, ''),
            'note' => filled($data['note'] ?? null) ? Str::limit(trim($data['note']), 200, '') : null,
            'items' => $lines,
            'subtotal' => $itemsTotal,
            'service_charge_amount' => $totals['service_charge_amount'],
            'tax_amount' => $totals['tax_amount'],
            'total' => $totals['total'],
            'fee_amount' => $fee,
            'pay_method' => $payOnline ? 'online' : 'cashier',
            'payment_status' => $payOnline ? 'pending' : 'unpaid',
            'status' => 'new',
            'ip_address' => $ip,
        ]);

        if ($payOnline) {
            $this->startPayment($tenant, $order);
        }

        return $order;
    }

    public function startPayment(Tenant $tenant, SelfOrder $order): void
    {
        $gateway = $this->gateways->forTenant($tenant);
        $order->forceFill(['payment_reference' => 'QR-'.$tenant->id.'-'.$order->id.'-'.Str::upper(Str::random(5))])->save();

        $items = array_map(fn (array $line) => [
            'id' => 'M'.$line['product_id'],
            'name' => $line['name'].($line['modifiers'] ? ' ('.implode(', ', $line['modifiers']).')' : ''),
            'price' => $line['unit_price'],
            'quantity' => $line['qty'],
        ], $order->items);
        if ($order->delivery_fee > 0) {
            $items[] = ['id' => 'ONGKIR', 'name' => 'Ongkos kirim', 'price' => $order->delivery_fee, 'quantity' => 1];
        }
        $extra = $order->total - $order->subtotal - $order->delivery_fee;
        if ($extra > 0) {
            $items[] = ['id' => 'PAJAK-LAYANAN', 'name' => 'Pajak & biaya layanan', 'price' => $extra, 'quantity' => 1];
        }
        if ($order->fee_amount > 0) {
            $items[] = ['id' => 'BIAYA-BAYAR', 'name' => 'Biaya layanan pembayaran', 'price' => $order->fee_amount, 'quantity' => 1];
        }

        try {
            $url = $gateway->createCharge(new ChargeRequest(
                reference: $order->payment_reference,
                amount: $order->total + $order->fee_amount,
                items: $items,
                customer: ['name' => $order->customer_name, 'phone' => $order->customer_phone],
                enabledPayments: PaymentFee::enabledPayments('qris'),
                finishUrl: route('self-order.status', $order->uuid),
                notificationUrl: route('self-order.webhook', $tenant->uuid),
                expiryMinutes: 30,
            ));
        } catch (RuntimeException $e) {
            Log::warning("Gagal membuat tagihan QRIS pesanan meja {$order->uuid}: {$e->getMessage()}");
            $order->forceFill(['pay_method' => 'cashier', 'payment_status' => 'unpaid', 'fee_amount' => 0, 'payment_reference' => null])->save();

            return;
        }

        $order->forceFill(['payment_url' => $url])->save();
    }

    public function refreshPayment(Tenant $tenant, SelfOrder $order): SelfOrder
    {
        if ($order->payment_status !== 'pending' || ! $order->payment_reference) {
            return $order;
        }

        $result = $this->gateways->forTenant($tenant)?->fetchStatus($order->payment_reference);

        return $result ? ($this->applyPayment($result) ?? $order) : $order;
    }

    public function gatewayFor(Tenant $tenant): ?PaymentGateway
    {
        return $this->gateways->forTenant($tenant);
    }

    public function applyPayment(PaymentResult $result): ?SelfOrder
    {
        return DB::transaction(function () use ($result) {
            $order = SelfOrder::query()->where('payment_reference', $result->reference)->lockForUpdate()->first();
            if (! $order) {
                return null;
            }

            $order->payment_payload = $result->raw;

            if ($order->payment_status === 'paid') {
                $order->save();

                return $order;
            }

            if ($result->isPaid() && $result->amount !== null && $result->amount !== $order->total + $order->fee_amount) {
                Log::error("Nominal QRIS pesanan meja {$order->uuid} tidak cocok: {$result->amount}");
                $order->save();

                return $order;
            }

            if ($result->isPaid()) {
                $order->fill(['payment_status' => 'paid', 'paid_at' => now(), 'payment_channel' => $result->method]);
            } elseif (in_array($result->status, ['expired', 'failed'], true)) {
                $order->fill(['payment_status' => $result->status, 'status' => $order->status === 'new' ? 'rejected' : $order->status, 'reject_reason' => 'Pembayaran tidak selesai.']);
            }
            $order->save();

            return $order;
        });
    }

    public function pending(int $outletId): Collection
    {
        return SelfOrder::query()->with('diningTable:id,name')
            ->where('outlet_id', $outletId)
            ->where('status', 'new')
            ->where(fn ($q) => $q->where('pay_method', 'cashier')->orWhere('payment_status', 'paid'))
            ->oldest('id')
            ->limit(50)
            ->get();
    }

    public function accept(SelfOrder $order, User $user, ?string $shiftUuid): ?Sale
    {
        return DB::transaction(function () use ($order, $user, $shiftUuid) {
            $order = SelfOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! $order->visibleToCashier()) {
                throw ValidationException::withMessages(['order' => 'Pesanan ini sudah diproses.']);
            }

            $sale = null;
            if ($order->isPaidOnline()) {
                if (! $shiftUuid) {
                    throw ValidationException::withMessages(['order' => 'Buka kasir dulu untuk menerima pesanan yang sudah dibayar.']);
                }
                $sale = $this->recordPaidSale($order, $user, $shiftUuid);
            }

            $order->forceFill([
                'status' => $sale ? 'done' : 'accepted',
                'handled_by' => $user->id,
                'handled_at' => now(),
            ])->save();

            return $sale;
        });
    }

    public function reject(SelfOrder $order, User $user, string $reason): SelfOrder
    {
        if (! $order->visibleToCashier()) {
            throw ValidationException::withMessages(['order' => 'Pesanan ini sudah diproses.']);
        }

        $order->forceFill([
            'status' => 'rejected',
            'reject_reason' => Str::limit($reason, 200, ''),
            'handled_by' => $user->id,
            'handled_at' => now(),
        ])->save();

        return $order;
    }

    public function deliveryProduct(): Product
    {
        return Product::query()->where('code', 'ONGKIR')->first()
            ?? Product::create([
                'uuid' => (string) Str::uuid(),
                'name' => 'Ongkos Kirim',
                'code' => 'ONGKIR',
                'type' => 'service',
                'pricing_mode' => 'open_price',
                'price' => 0,
                'cost_price' => 0,
                'track_stock' => false,
                'is_active' => true,
            ]);
    }

    public function saleNote(SelfOrder $order): string
    {
        $label = match ($order->order_type) {
            'delivery' => 'Pesan antar',
            'take_away' => 'Ambil sendiri',
            default => 'Pesan lewat QR',
        };

        return mb_substr(implode(' · ', array_filter([
            "{$label} {$order->code}",
            $order->customer_name,
            $order->customer_phone ? Phone::display($order->customer_phone) : null,
            $order->address,
            $order->note,
        ])), 0, 255);
    }

    private function recordPaidSale(SelfOrder $order, User $user, string $shiftUuid): Sale
    {
        $method = PaymentMethod::query()->where('is_active', true)->where('type', 'qris')->orderBy('sort_order')->first()
            ?? PaymentMethod::query()->where('type', 'qris')->first()
            ?? PaymentMethod::create(['name' => 'QRIS', 'type' => 'qris', 'is_active' => true, 'sort_order' => 1]);

        return app(SaleService::class)->record([
            'uuid' => (string) Str::uuid(),
            'shift_uuid' => $shiftUuid,
            'order_type' => $order->order_type ?: 'dine_in',
            'table_id' => $order->table_id,
            'note' => $this->saleNote($order),
            'created_at' => now()->toIso8601String(),
            'send_to_kitchen' => true,
            'items' => [...array_map(fn (array $line) => [
                'product_id' => $line['product_id'],
                'qty' => $line['qty'],
                'unit_price' => $line['unit_price'],
                'modifiers' => $line['modifier_ids'],
                'note' => $line['note'],
            ], $order->items), ...($order->delivery_fee > 0 ? [[
                'product_id' => $this->deliveryProduct()->id,
                'qty' => 1,
                'unit_price' => $order->delivery_fee,
                'note' => null,
            ]] : [])],
            'payments' => [[
                'uuid' => (string) Str::uuid(),
                'payment_method_id' => $method->id,
                'amount' => $order->total,
                'reference' => $order->payment_reference,
            ]],
        ], $user, null, false);
    }
}
