<?php

namespace App\Modules\Pos\Services;

use App\Core\Support\Qty;
use App\Core\Support\Rupiah;
use App\Core\Tenancy\TenantContext;
use App\Enums\Permission;
use App\Models\Outlet;
use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customer\Models\Customer;
use App\Modules\Pos\Contracts\SaleHook;
use App\Modules\Pos\Models\PaymentMethod;
use App\Modules\Pos\Models\Sale;
use App\Modules\Pos\Models\Shift;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Mencatat penjualan dari kasir.
 *
 * - IDEMPOTENT: UUID dibuat di HP kasir. Kalau penjualan dengan UUID itu sudah ada
 *   (misalnya kirim ulang karena internet putus), data lama dikembalikan, tidak dobel.
 * - Harga ditentukan ulang di server (PriceResolver) dan total dihitung ulang (SaleCalculator).
 * - Semua (nota, barang, pembayaran, potong stok, langkah tiap modul) dalam satu database transaction.
 *
 * $strict = true  : penjualan langsung dari kasir online. Aturan ditegakkan (shift harus buka, uang cukup).
 * $strict = false : penjualan yang SUDAH terjadi di kasir offline lalu disinkronkan.
 *                   Tidak boleh ditolak; kejanggalan dicatat di activity log.
 */
class SaleService
{
    /** @param  iterable<SaleHook>  $hooks */
    public function __construct(
        private SaleCalculator $calculator,
        private PriceResolver $prices,
        private SaleStockService $stock,
        private TenantContext $context,
        private iterable $hooks = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data  data tervalidasi (lihat SaleRules)
     */
    public function record(array $data, User $cashier, ?int $deviceId = null, bool $strict = true): Sale
    {
        if ($existing = Sale::query()->where('uuid', $data['uuid'])->first()) {
            return $existing;
        }

        return DB::transaction(function () use ($data, $cashier, $deviceId, $strict) {
            $tenant = $this->context->get();
            $shift = Shift::query()->where('uuid', $data['shift_uuid'])->lockForUpdate()->first();

            if (! $shift || ($strict && ! $shift->isOpen())) {
                throw ValidationException::withMessages([
                    'shift' => 'Kasir belum dibuka. Buka kasir dulu sebelum mulai jualan.',
                ]);
            }

            $outlet = Outlet::query()->findOrFail($shift->outlet_id);
            $customer = ! empty($data['customer_uuid']) ? Customer::withTrashed()->where('uuid', $data['customer_uuid'])->first() : null;
            $products = Product::withTrashed()->with(['unit:id,name', 'modifierGroups'])
                ->whereIn('id', collect($data['items'])->pluck('product_id'))->get()->keyBy('id');

            // Promo dipilih sesuai tanggal transaksi terjadi (penting untuk penjualan offline yang baru disinkron).
            $saleDate = \Illuminate\Support\Carbon::parse($data['created_at'] ?? now())->timezone($tenant->timezone)->toDateString();
            $this->prices->prepare($tenant, $outlet->id, $products, $customer, $saleDate);

            $priceWarnings = [];
            $lines = [];

            foreach ($data['items'] as $item) {
                $product = $products[$item['product_id']];
                $qty = Qty::normalize($item['qty']);
                $resolved = $this->prices->resolve($product, $qty, $item['unit_id'] ?? null, $item['modifiers'] ?? [], $strict);
                $unitPrice = $this->resolvePrice($product, $resolved['unit_price'], (int) ($item['unit_price'] ?? $resolved['unit_price']), $cashier, $strict);

                if ($unitPrice !== $resolved['unit_price'] && $product->pricing_mode === 'fixed') {
                    $priceWarnings[] = "{$product->name}: ".Rupiah::format($resolved['unit_price']).' → '.Rupiah::format($unitPrice);
                }

                $lines[] = [
                    'product' => $product,
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'original_price' => $resolved['unit_price'],
                    'discount_amount' => (int) ($item['discount_amount'] ?? 0),
                    'conversion_qty' => $resolved['conversion_qty'],
                    'unit_id' => $resolved['unit_id'],
                    'unit_name' => $resolved['unit_name'],
                    'modifiers' => $resolved['modifiers'],
                    'promo' => $resolved['promo'],
                    'staff_id' => $item['staff_id'] ?? null,
                    'note' => $item['note'] ?? null,
                ];
            }

            $totals = $this->calculator->calculate(
                $lines,
                $data['discount_type'] ?? null,
                (int) ($data['discount_value'] ?? 0),
                $outlet->service_charge_bp,
                $outlet->tax_rate_bp,
                $outlet->tax_inclusive,
            );

            $payLater = $this->allowsPayLater($data, $customer, $tenant->hasModule('kasbon') || $tenant->hasModule('credit_sales') || $tenant->hasModule('order_status'), $strict);
            [$payments, $tendered, $change, $due] = $this->resolvePayments($data['payments'] ?? [], $totals['total'], $strict && ! $payLater);

            if ($due > 0 && $strict) {
                $this->ensureCreditLimit($customer, $due, $tenant->hasModule('kasbon'));
            }

            $sale = Sale::create([
                'outlet_id' => $outlet->id,
                'uuid' => $data['uuid'],
                'number' => $this->uniqueNumber($data['number'] ?? null),
                'device_id' => $deviceId,
                'shift_id' => $shift->id,
                'cashier_id' => $cashier->id,
                'customer_id' => $customer?->id,
                'table_id' => $data['table_id'] ?? null,
                'order_type' => $data['order_type'] ?? 'walk_in',
                'status' => 'completed',
                'payment_status' => $due > 0 ? ($tendered > 0 ? 'partial' : 'unpaid') : 'paid',
                'subtotal' => $totals['subtotal'],
                'discount_type' => $totals['discount_amount'] > 0 ? ($data['discount_type'] ?? null) : null,
                'discount_value' => $totals['discount_amount'] > 0 ? (int) ($data['discount_value'] ?? 0) : 0,
                'discount_amount' => $totals['discount_amount'],
                'service_charge_amount' => $totals['service_charge_amount'],
                'tax_amount' => $totals['tax_amount'],
                'total' => $totals['total'],
                'paid_amount' => $tendered,
                'change_amount' => $change,
                'due_amount' => $due,
                'due_date' => $due > 0 ? ($data['due_date'] ?? null) : null,
                'queue_number' => $data['queue_number'] ?? null,
                'note' => $data['note'] ?? null,
                'device_created_at' => $data['created_at'] ?? null,
                'synced_at' => $strict ? null : now(),
                'completed_at' => $data['created_at'] ?? now(),
            ]);

            foreach ($lines as $i => $line) {
                /** @var Product $product */
                $product = $line['product'];

                $saleItem = $sale->items()->create([
                    'product_id' => $product->id,
                    'product_unit_id' => $line['unit_id'],
                    'name' => $product->name,
                    'unit_name' => $line['unit_name'],
                    'qty' => $line['qty'],
                    'conversion_qty' => $line['conversion_qty'],
                    'unit_price' => $line['unit_price'],
                    'original_price' => $line['original_price'],
                    'discount_amount' => $totals['lines'][$i]['discount'],
                    'subtotal' => $totals['lines'][$i]['subtotal'],
                    'cost_amount' => 0,
                    'note' => $line['note'],
                    'staff_id' => $line['staff_id'],
                    'meta' => $line['promo'] && $line['unit_price'] === $line['original_price'] ? ['promo' => $line['promo']] : null,
                ]);

                foreach ($line['modifiers'] as $modifier) {
                    $saleItem->modifiers()->create($modifier);
                }

                // Stok boleh minus (keputusan yang disetujui): penjualan yang terjadi tidak ditolak.
                $cost = $this->stock->deduct($tenant, $sale, $product, $line['qty'], $line['conversion_qty'], $cashier->id);
                $saleItem->update(['cost_amount' => $cost]);
            }

            foreach ($payments as $payment) {
                $sale->payments()->create([
                    ...$payment,
                    'shift_id' => $shift->id,
                    'received_by' => $cashier->id,
                    'paid_at' => $sale->completed_at,
                ]);
            }

            foreach ($this->hooks as $hook) {
                $hook->handle($sale, $data, $cashier, $strict);
            }

            $this->logAnomalies($sale, $cashier, $priceWarnings, $totals);

            return $sale;
        });
    }

    /**
     * Harga tetap tidak bisa diubah kasir tanpa izin "ubah harga" (saat online).
     * Saat sinkron offline, harga yang sudah dibayar pembeli tetap dipakai.
     */
    private function resolvePrice(Product $product, int $resolved, int $requested, User $cashier, bool $strict): int
    {
        if ($product->pricing_mode === 'open_price') {
            return max(0, $requested);
        }

        if ($requested === $resolved) {
            return $resolved;
        }

        if (! $strict || $cashier->can(Permission::ChangePrice->value)) {
            return max(0, $requested);
        }

        return $resolved;
    }

    /** "Bayar nanti" (kasbon / tempo / bayar saat ambil laundry) butuh pelanggan & modulnya menyala. */
    private function allowsPayLater(array $data, ?Customer $customer, bool $moduleOn, bool $strict): bool
    {
        if (empty($data['pay_later'])) {
            return false;
        }

        if ($strict && (! $moduleOn || ! $customer)) {
            throw ValidationException::withMessages([
                'customer' => $moduleOn
                    ? 'Pilih pelanggan dulu untuk bayar nanti / kasbon, supaya utangnya tercatat atas namanya.'
                    : 'Fitur bayar nanti belum dinyalakan.',
            ]);
        }

        return true;
    }

    private function ensureCreditLimit(?Customer $customer, int $due, bool $isKasbon): void
    {
        if (! $isKasbon || ! $customer || $customer->credit_limit === null) {
            return;
        }

        $outstanding = $customer->outstandingBalance();
        if ($outstanding + $due > $customer->credit_limit) {
            throw ValidationException::withMessages([
                'customer' => "Batas kasbon {$customer->name} ".Rupiah::format($customer->credit_limit)
                    .' terlewati (utang sekarang '.Rupiah::format($outstanding).'). Minta bayar sebagian dulu.',
            ]);
        }
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: int, 2: int, 3: int} [pembayaran, diterima, kembalian, sisa]
     */
    private function resolvePayments(array $input, int $total, bool $requireFull): array
    {
        $methods = PaymentMethod::query()->whereIn('id', collect($input)->pluck('payment_method_id'))->get()->keyBy('id');

        $tendered = 0;
        $cashTendered = 0;

        foreach ($input as $payment) {
            $amount = max(0, (int) $payment['amount']);
            $tendered += $amount;
            if ($methods[$payment['payment_method_id']]?->isCash()) {
                $cashTendered += $amount;
            }
        }

        $change = max(0, $tendered - $total);
        $due = max(0, $total - $tendered);

        if ($requireFull && $due > 0) {
            throw ValidationException::withMessages([
                'payments' => 'Uang yang dibayar kurang '.Rupiah::format($due).'.',
            ]);
        }

        if ($change > $cashTendered) {
            throw ValidationException::withMessages([
                'payments' => 'Pembayaran non-tunai melebihi total belanja. Kembalian hanya bisa dari uang tunai.',
            ]);
        }

        // Yang dicatat: nilai yang benar-benar dipakai membayar (uang tunai dikurangi kembalian).
        $remainingChange = $change;
        $payments = [];

        foreach ($input as $payment) {
            $method = $methods[$payment['payment_method_id']];
            $amount = max(0, (int) $payment['amount']);

            if ($method->isCash() && $remainingChange > 0) {
                $deduct = min($amount, $remainingChange);
                $amount -= $deduct;
                $remainingChange -= $deduct;
            }

            if ($amount === 0 && $total > 0) {
                continue;
            }

            $payments[] = [
                'uuid' => $payment['uuid'] ?? (string) Str::uuid(),
                'payment_method_id' => $method->id,
                'method_type' => $method->type,
                'method_name' => $method->name,
                'amount' => $amount,
                'reference' => $payment['reference'] ?? null,
            ];
        }

        return [$payments, $tendered, $change, $due];
    }

    private function logAnomalies(Sale $sale, User $cashier, array $priceWarnings, array $totals): void
    {
        if ($priceWarnings) {
            activity('penjualan')->performedOn($sale)->causedBy($cashier)
                ->withProperties(['perubahan' => $priceWarnings])
                ->log("Harga diubah saat jualan (nota {$sale->number})");
        }

        $itemDiscount = collect($totals['lines'])->sum('discount');
        if ($totals['discount_amount'] > 0 || $itemDiscount > 0) {
            activity('penjualan')->performedOn($sale)->causedBy($cashier)
                ->withProperties(['diskon_transaksi' => $totals['discount_amount'], 'diskon_barang' => $itemDiscount])
                ->log("Memberi diskon (nota {$sale->number})");
        }
    }

    /** Nomor nota dari HP kasir; kalau kebetulan sudah dipakai, diberi akhiran. */
    private function uniqueNumber(?string $number): string
    {
        $number = $number ?: 'W-'.now()->format('ymd-His').'-'.Str::upper(Str::random(3));
        $candidate = $number;
        $suffix = 2;

        while (Sale::query()->where('number', $candidate)->exists()) {
            $candidate = "{$number}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }
}
