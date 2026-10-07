<?php

namespace App\Modules\Purchasing\Services;

use App\Core\Support\NumberSequence;
use App\Core\Support\Qty;
use App\Core\Support\Rupiah;
use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductUnit;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Purchasing\Models\Purchase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Belanja barang dari pemasok: stok bertambah (dengan harga beli → modal rata-rata),
 * dan kalau belum lunas tercatat sebagai utang ke pemasok.
 */
class PurchaseService
{
    public function __construct(
        private StockService $stock,
        private NumberSequence $numbers,
    ) {}

    /**
     * Terima barang dari pemasok.
     *
     * Timbangan (modul weighed_receiving): pemasok menagih berdasarkan nota (jumlah karung x berat per karung),
     * tapi stok bertambah sesuai berat timbangan asli. Selisihnya tercatat sebagai selisih timbang pemasok.
     *
     * Modal lengkap (modul landed_cost): ongkos angkut, bongkar muat, dan biaya lain dibagi ke setiap barang
     * sebanding nilai belanjanya, lalu modal per satuan = (harga beli + bagian biaya) / jumlah yang benar-benar diterima.
     * Biaya tambahan tidak menambah utang ke pemasok.
     *
     * @param  array{outlet_id:int, supplier_id?:int|null, supplier_invoice_no?:string|null, purchased_on:string,
     *               due_date?:string|null, note?:string|null, paid_amount:int, payment_method_id?:int|null,
     *               freight_cost?:int|null, unloading_cost?:int|null, other_cost?:int|null,
     *               items: list<array{product_id:int, unit_id?:int|null, qty:string|float, unit_cost:int,
     *                                 pack_count?:string|float|null, pack_weight?:string|float|null, received_qty?:string|float|null}>}  $data
     */
    public function create(array $data, User $user): Purchase
    {
        return DB::transaction(function () use ($data, $user) {
            $products = Product::query()->whereIn('id', array_column($data['items'], 'product_id'))->get()->keyBy('id');
            $units = ProductUnit::query()->whereIn('id', array_filter(array_column($data['items'], 'unit_id')))->get()->keyBy('id');

            $lines = [];
            $total = 0;
            foreach ($data['items'] as $item) {
                $unit = ! empty($item['unit_id']) ? $units->get($item['unit_id']) : null;
                $conversion = $unit ? Qty::normalize($unit->conversion_qty) : '1.000';

                // Berat nota dihitung ulang di server: jumlah karung x berat per karung.
                $weighed = ! $unit && ! empty($item['pack_count']) && ! empty($item['pack_weight']);
                $qty = $weighed ? Qty::mul($item['pack_count'], $item['pack_weight']) : Qty::normalize($item['qty']);

                $billedBase = Qty::mul($qty, $conversion);
                $hasReceived = ! $unit && isset($item['received_qty']) && $item['received_qty'] !== '';
                $receivedBase = $hasReceived ? Qty::normalize($item['received_qty']) : $billedBase;

                $subtotal = Qty::money((int) $item['unit_cost'], $qty);
                $lines[] = [
                    'product' => $products[$item['product_id']],
                    'unit' => $unit,
                    'conversion' => $conversion,
                    'qty' => $qty,
                    'unit_cost' => (int) $item['unit_cost'],
                    'subtotal' => $subtotal,
                    'pack_count' => $weighed ? Qty::normalize($item['pack_count']) : null,
                    'pack_weight' => $weighed ? Qty::normalize($item['pack_weight']) : null,
                    'received' => $receivedBase,
                    'weighed' => $hasReceived || $weighed,
                    'diff' => Qty::sub($receivedBase, $billedBase),
                    // Batch / lot (modul batch_lot)
                    'batch' => array_filter([
                        'batch_no' => $item['batch_no'] ?? null,
                        'expires_at' => $item['expires_at'] ?? null,
                        'moisture' => $item['moisture'] ?? null,
                        'quality_note' => $item['quality_note'] ?? null,
                    ], fn ($v) => $v !== null && $v !== ''),
                ];
                $total += $subtotal;
            }

            $extraCosts = [
                'freight_cost' => max(0, (int) ($data['freight_cost'] ?? 0)),
                'unloading_cost' => max(0, (int) ($data['unloading_cost'] ?? 0)),
                'other_cost' => max(0, (int) ($data['other_cost'] ?? 0)),
            ];
            $shares = $this->allocate(array_sum($extraCosts), array_column($lines, 'subtotal'));

            $paid = min($total, max(0, (int) ($data['paid_amount'] ?? 0)));

            $purchase = Purchase::create([
                'outlet_id' => $data['outlet_id'],
                'uuid' => (string) Str::uuid(),
                'number' => $this->numbers->next('BL'),
                'supplier_id' => $data['supplier_id'] ?? null,
                'supplier_invoice_no' => $data['supplier_invoice_no'] ?? null,
                'purchased_on' => $data['purchased_on'],
                'total' => $total,
                ...$extraCosts,
                'paid_amount' => $paid,
                'due_date' => $paid < $total ? ($data['due_date'] ?? null) : null,
                'payment_status' => $paid >= $total ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid'),
                'note' => $data['note'] ?? null,
                'user_id' => $user->id,
            ]);

            foreach ($lines as $i => $line) {
                $extra = $shares[$i];
                $received = $line['received'];

                // Modal per satuan dasar = (harga beli + bagian biaya tambahan) / jumlah yang benar-benar diterima.
                $landedCost = Qty::cmp($received, '0') > 0
                    ? (int) round(($line['subtotal'] + $extra) / (float) $received)
                    : (int) round($line['unit_cost'] / (float) $line['conversion']);

                $purchase->items()->create([
                    'product_id' => $line['product']->id,
                    'product_unit_id' => $line['unit']?->id,
                    'qty' => $line['qty'],
                    'conversion_qty' => $line['conversion'],
                    'unit_cost' => $line['unit_cost'],
                    'subtotal' => $line['subtotal'],
                    'pack_count' => $line['pack_count'],
                    'pack_weight' => $line['pack_weight'],
                    'received_qty' => $line['weighed'] ? $received : null,
                    'weight_diff' => $line['diff'],
                    'extra_cost' => $extra,
                    'landed_unit_cost' => $landedCost,
                ]);

                $note = "Belanja {$purchase->number}";
                if (! Qty::isZero($line['diff'])) {
                    $note .= ' (selisih timbang '.(Qty::isNegative($line['diff']) ? '' : '+').Qty::display($line['diff']).')';
                }

                $this->stock->change($data['outlet_id'], $line['product'], $received, 'purchase', $purchase, $landedCost, $note, $user->id, $line['batch']);
            }

            if ($paid > 0) {
                $purchase->payments()->create(['amount' => $paid, 'payment_method_id' => $data['payment_method_id'] ?? null, 'paid_at' => now(), 'user_id' => $user->id]);
            }

            return $purchase;
        });
    }

    /**
     * Bagi biaya tambahan ke setiap baris sebanding nilainya. Sisa pembulatan masuk ke baris terakhir,
     * jadi jumlah bagian selalu sama persis dengan total biaya.
     *
     * @param  list<int>  $weights
     * @return list<int>
     */
    private function allocate(int $amount, array $weights): array
    {
        $count = count($weights);
        if ($amount <= 0 || $count === 0) {
            return array_fill(0, $count, 0);
        }

        $sum = array_sum($weights);
        $shares = [];
        $given = 0;
        foreach ($weights as $i => $weight) {
            $share = $i === $count - 1
                ? $amount - $given
                : (int) floor($sum > 0 ? $amount * $weight / $sum : $amount / $count);
            $shares[] = $share;
            $given += $share;
        }

        return $shares;
    }

    public function pay(Purchase $purchase, int $amount, ?int $paymentMethodId, User $user): Purchase
    {
        return DB::transaction(function () use ($purchase, $amount, $paymentMethodId, $user) {
            $purchase = Purchase::query()->lockForUpdate()->findOrFail($purchase->id);
            $due = $purchase->total - $purchase->paid_amount;

            if ($amount <= 0 || $amount > $due) {
                throw ValidationException::withMessages(['amount' => 'Jumlah bayar harus antara Rp1 dan sisa utang '.Rupiah::format($due).'.']);
            }

            $purchase->payments()->create(['amount' => $amount, 'payment_method_id' => $paymentMethodId, 'paid_at' => now(), 'user_id' => $user->id]);
            $paid = $purchase->paid_amount + $amount;
            $purchase->update(['paid_amount' => $paid, 'payment_status' => $paid >= $purchase->total ? 'paid' : 'partial']);

            return $purchase;
        });
    }
}
