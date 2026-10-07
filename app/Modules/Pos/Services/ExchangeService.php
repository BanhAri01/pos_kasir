<?php

namespace App\Modules\Pos\Services;

use App\Core\Support\NumberSequence;
use App\Core\Tenancy\TenantContext;
use App\Models\Outlet;
use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Pos\Models\PaymentMethod;
use App\Modules\Pos\Models\Refund;
use App\Modules\Pos\Models\Sale;
use App\Modules\Pos\Models\Shift;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Tukar barang (modul returns_exchange), misalnya tukar ukuran baju.
 *
 * Dicatat sebagai dua langkah yang sudah ada, supaya stok, uang laci, dan laporan tetap benar:
 *   1. Pengembalian barang lama (stok kembali) senilai yang dulu dibayar  -> uang "keluar" tunai.
 *   2. Penjualan barang pengganti, dibayar dengan nilai barang lama (tunai) + kekurangannya.
 * Hasil bersih di laci = selisihnya saja: pembeli menambah kalau barang baru lebih mahal,
 * atau menerima kembalian kalau lebih murah.
 */
class ExchangeService
{
    public function __construct(
        private SaleAdjustmentService $adjustments,
        private SaleService $sales,
        private PriceResolver $prices,
        private SaleCalculator $calculator,
        private NumberSequence $numbers,
        private TenantContext $context,
    ) {}

    /**
     * @param  list<array{sale_item_id:int, qty:string|float}>  $returns
     * @param  list<array{product_id:int, qty:string|float}>  $items
     * @return array{refund: Refund, sale: Sale, difference: int} difference > 0: pembeli menambah, < 0: uang dikembalikan
     */
    public function exchange(Sale $sale, array $returns, array $items, ?int $paymentMethodId, User $actor, ?string $reason = null): array
    {
        $shift = $this->openShift($actor, $sale->outlet_id);
        $cash = PaymentMethod::query()->where('type', 'cash')->where('is_active', true)->orderBy('sort_order')->first()
            ?? throw ValidationException::withMessages(['payment_method_id' => 'Cara bayar Tunai belum aktif. Nyalakan dulu di menu Cara Bayar.']);

        return DB::transaction(function () use ($sale, $returns, $items, $paymentMethodId, $actor, $reason, $shift, $cash) {
            $total = $this->previewTotal($sale, $items);

            $refund = $this->adjustments->refund(
                $sale, $returns, $reason ?: 'Tukar barang', true, $cash->id, $actor, $shift->id,
            );
            $credit = $refund->amount;

            $payments = [['payment_method_id' => $cash->id, 'amount' => min($credit, $total)]];
            if ($total > $credit) {
                $payments[] = ['payment_method_id' => $paymentMethodId ?? $cash->id, 'amount' => $total - $credit];
            }

            $newSale = $this->sales->record([
                'uuid' => (string) Str::uuid(),
                'number' => $this->numbers->next('TK'),
                'shift_uuid' => $shift->uuid,
                'customer_uuid' => $sale->customer?->uuid,
                'items' => array_map(fn ($i) => ['product_id' => (int) $i['product_id'], 'qty' => (string) $i['qty']], $items),
                'payments' => array_values(array_filter($payments, fn ($p) => $p['amount'] > 0)),
                'note' => "Tukar barang dari nota {$sale->number}",
            ], $actor);

            $refund->update(['reason' => ($reason ?: 'Tukar barang')." → nota {$newSale->number}"]);

            return ['refund' => $refund, 'sale' => $newSale, 'difference' => $newSale->total - $credit];
        });
    }

    /** Total barang pengganti dengan harga & pajak yang sama seperti di kasir. */
    public function previewTotal(Sale $sale, array $items): int
    {
        $tenant = $this->context->get();
        $outlet = Outlet::query()->findOrFail($sale->outlet_id);
        $products = Product::query()->with(['unit:id,name', 'modifierGroups'])->whereIn('id', array_column($items, 'product_id'))->get()->keyBy('id');

        if ($products->count() !== count(array_unique(array_column($items, 'product_id')))) {
            throw ValidationException::withMessages(['items' => 'Ada barang pengganti yang tidak ditemukan.']);
        }

        $this->prices->prepare($tenant, $outlet->id, $products, $sale->customer);
        $lines = array_map(fn ($i) => [
            'unit_price' => $this->prices->resolve($products[$i['product_id']], (string) $i['qty'], strict: false)['unit_price'],
            'qty' => (string) $i['qty'],
        ], $items);

        return $this->calculator->calculate($lines, null, 0, $outlet->service_charge_bp, $outlet->tax_rate_bp, $outlet->tax_inclusive)['total'];
    }

    /** Tukar barang dicatat di kasir yang sedang buka di outlet itu (diutamakan kasir milik pengguna ini). */
    private function openShift(User $actor, int $outletId): Shift
    {
        $shift = Shift::query()->where('outlet_id', $outletId)->where('status', 'open')
            ->orderByRaw('CASE WHEN user_id = ? THEN 0 ELSE 1 END', [$actor->id])
            ->latest('id')->first();

        return $shift ?? throw ValidationException::withMessages([
            'shift' => 'Belum ada kasir yang buka di outlet ini. Buka kasir dulu di layar Kasir, lalu ulangi tukar barang.',
        ]);
    }
}
