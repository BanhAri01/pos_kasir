<?php

namespace App\Modules\Warehouse\Services;

use App\Core\Support\NumberSequence;
use App\Core\Support\Qty;
use App\Models\User;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Warehouse\Models\ProductionFormula;
use App\Modules\Warehouse\Models\ProductionOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Olah / produksi dan kemas ulang.
 *
 *  1. Bahan (termasuk bahan kemas) keluar dari stok sesuai jumlah yang benar-benar terpakai.
 *  2. Hasil masuk ke stok dengan modal = (nilai bahan + bahan kemas + biaya olah) / jumlah hasil.
 *  3. Susut produksi = berat bahan - berat hasil (dalam satuan bahan utama, biasanya kg).
 *
 * Bongkar karung (unpack) adalah kebalikan kemas ulang: barang karungan keluar, barang curah masuk.
 */
class ProductionService
{
    public function __construct(
        private StockService $stock,
        private NumberSequence $numbers,
    ) {}

    /**
     * @param  array{formula_id:int, batches:string|float, output_qty?:string|float|null,
     *               inputs?: list<array{product_id:int, actual_qty:string|float}>,
     *               labor_cost?:int|null, utility_cost?:int|null, machine_cost?:int|null, other_cost?:int|null,
     *               batch_no?:string|null, expires_at?:string|null, note?:string|null}  $data
     */
    public function produce(array $data, int $outletId, User $user): ProductionOrder
    {
        return DB::transaction(function () use ($data, $outletId, $user) {
            $formula = ProductionFormula::query()->with(['items.product.unit', 'output.unit'])->findOrFail($data['formula_id']);
            $batches = Qty::normalize($data['batches']);

            if (Qty::cmp($batches, '0') <= 0) {
                throw ValidationException::withMessages(['batches' => 'Jumlah harus lebih dari 0.']);
            }

            $actuals = collect($data['inputs'] ?? [])->mapWithKeys(fn ($row) => [(int) $row['product_id'] => Qty::normalize($row['actual_qty'])]);
            $plannedOutput = Qty::mul($formula->output_qty, $batches);
            $output = isset($data['output_qty']) && $data['output_qty'] !== '' ? Qty::normalize($data['output_qty']) : $plannedOutput;

            if (Qty::cmp($output, '0') <= 0) {
                throw ValidationException::withMessages(['output_qty' => 'Isi jumlah hasilnya. Harus lebih dari 0.']);
            }

            // Bahan utama = bahan pertama yang bukan bahan kemas; susut dihitung dalam satuannya.
            $mainUnitId = $formula->items->first(fn ($i) => ! $i->product->is_packaging)?->product->base_unit_id;

            $lines = [];
            $materialsCost = 0;
            $packagingCost = 0;
            $plannedWeight = '0';
            $actualWeight = '0';
            foreach ($formula->items as $item) {
                $planned = Qty::mul($item->qty, $batches);
                $actual = $actuals[$item->product_id] ?? $planned;
                $unitCost = $this->stock->avgCost($outletId, $item->product);
                $total = Qty::money($unitCost, $actual);
                $role = $item->product->is_packaging ? 'packaging' : 'material';

                $role === 'packaging' ? $packagingCost += $total : $materialsCost += $total;
                if ($role === 'material' && $item->product->base_unit_id === $mainUnitId) {
                    $plannedWeight = Qty::add($plannedWeight, $planned);
                    $actualWeight = Qty::add($actualWeight, $actual);
                }

                $lines[] = compact('item', 'planned', 'actual', 'unitCost', 'total', 'role');
            }

            // Berat hasil dalam satuan bahan utama. Hasil per kg -> sama; hasil per karung -> dikonversi lewat resep.
            $outputWeight = $formula->output->base_unit_id === $mainUnitId
                ? $output
                : (Qty::cmp($plannedOutput, '0') > 0 ? bcdiv(bcmul($plannedWeight, $output, 6), $plannedOutput, Qty::SCALE) : '0');
            $shrinkage = Qty::cmp($actualWeight, '0') > 0 ? Qty::sub($actualWeight, $outputWeight) : '0';

            $costs = [
                'labor_cost' => max(0, (int) ($data['labor_cost'] ?? 0)),
                'utility_cost' => max(0, (int) ($data['utility_cost'] ?? 0)),
                'machine_cost' => max(0, (int) ($data['machine_cost'] ?? 0)),
                'other_cost' => max(0, (int) ($data['other_cost'] ?? 0)),
            ];
            $unitCost = (int) round(($materialsCost + $packagingCost + array_sum($costs)) / (float) $output);

            $order = ProductionOrder::create([
                'outlet_id' => $outletId,
                'uuid' => (string) Str::uuid(),
                'number' => $this->numbers->next($formula->kind === 'repack' ? 'KM' : 'OL'),
                'kind' => $formula->kind,
                'production_formula_id' => $formula->id,
                'output_product_id' => $formula->output_product_id,
                'batches' => $batches,
                'planned_output_qty' => $plannedOutput,
                'output_qty' => $output,
                'input_weight' => $actualWeight,
                'shrinkage_qty' => $shrinkage,
                'materials_cost' => $materialsCost,
                'packaging_cost' => $packagingCost,
                ...$costs,
                'unit_cost' => $unitCost,
                'batch_no' => ($data['batch_no'] ?? null) ?: null,
                'expires_at' => ($data['expires_at'] ?? null) ?: null,
                'note' => $data['note'] ?? null,
                'user_id' => $user->id,
            ]);

            foreach ($lines as $line) {
                $order->items()->create([
                    'product_id' => $line['item']->product_id,
                    'role' => $line['role'],
                    'planned_qty' => $line['planned'],
                    'actual_qty' => $line['actual'],
                    'unit_cost' => $line['unitCost'],
                    'total_cost' => $line['total'],
                ]);
                $this->stock->change($outletId, $line['item']->product, Qty::negate($line['actual']), 'production_out', $order, note: "Dipakai {$order->number}", userId: $user->id);
            }

            $order->items()->create([
                'product_id' => $formula->output_product_id, 'role' => 'output',
                'planned_qty' => $plannedOutput, 'actual_qty' => $output, 'unit_cost' => $unitCost, 'total_cost' => Qty::money($unitCost, $output),
            ]);
            $this->stock->change($outletId, $formula->output, $output, 'production_in', $order, $unitCost, "Hasil {$order->number}", $user->id, array_filter([
                'batch_no' => $order->batch_no,
                'expires_at' => $order->expires_at?->toDateString(),
            ]));

            return $order;
        });
    }

    /**
     * Bongkar barang karungan jadi curah (kebalikan kemas ulang).
     *
     * @param  array{formula_id:int, qty:string|float, received_qty?:string|float|null, reuse_packaging?:bool, note?:string|null}  $data
     *                                                                                                                                    qty = berapa karung dibongkar, received_qty = berat curah hasil timbang
     */
    public function unpack(array $data, int $outletId, User $user): ProductionOrder
    {
        return DB::transaction(function () use ($data, $outletId, $user) {
            $formula = ProductionFormula::query()->where('kind', 'repack')->with(['items.product', 'output'])->findOrFail($data['formula_id']);
            $count = Qty::normalize($data['qty']);

            if (Qty::cmp($count, '0') <= 0) {
                throw ValidationException::withMessages(['qty' => 'Isi berapa karung yang dibongkar.']);
            }

            $bulkItem = $formula->items->first(fn ($i) => ! $i->product->is_packaging);
            if (! $bulkItem) {
                throw ValidationException::withMessages(['formula_id' => 'Resep kemasan ini belum punya bahan curah.']);
            }

            $packs = Qty::mul($count, bcdiv('1', Qty::normalize($formula->output_qty), 6)); // umumnya output_qty = 1
            $plannedBulk = Qty::mul($bulkItem->qty, $packs);
            $bulk = isset($data['received_qty']) && $data['received_qty'] !== '' ? Qty::normalize($data['received_qty']) : $plannedBulk;
            $packCost = $this->stock->avgCost($outletId, $formula->output);
            $totalValue = Qty::money($packCost, $count);

            // Karung yang masih bagus bisa dikembalikan ke stok bahan kemas (nilainya dikurangkan dari modal curah).
            $returned = [];
            $returnedValue = 0;
            if (! empty($data['reuse_packaging'])) {
                // Hanya kemasan utuh (1 karung per kemasan). Benang/label yang terpakai sebagian tidak bisa dipakai lagi.
                foreach ($formula->items->filter(fn ($i) => $i->product->is_packaging && Qty::cmp($i->qty, bcadd($i->qty, '0', 0)) === 0) as $item) {
                    $qty = Qty::mul($item->qty, $packs);
                    $cost = $this->stock->avgCost($outletId, $item->product);
                    $returned[] = [$item->product, $qty, $cost];
                    $returnedValue += Qty::money($cost, $qty);
                }
            }

            $bulkCost = Qty::cmp($bulk, '0') > 0 ? (int) round(max(0, $totalValue - $returnedValue) / (float) $bulk) : 0;

            $order = ProductionOrder::create([
                'outlet_id' => $outletId,
                'uuid' => (string) Str::uuid(),
                'number' => $this->numbers->next('BK'),
                'kind' => 'unpack',
                'production_formula_id' => $formula->id,
                'output_product_id' => $bulkItem->product_id,
                'batches' => $count,
                'planned_output_qty' => $plannedBulk,
                'output_qty' => $bulk,
                'input_weight' => $plannedBulk,
                'shrinkage_qty' => Qty::sub($plannedBulk, $bulk),
                'materials_cost' => $totalValue,
                'packaging_cost' => -$returnedValue,
                'unit_cost' => $bulkCost,
                'note' => $data['note'] ?? null,
                'user_id' => $user->id,
            ]);

            $order->items()->create(['product_id' => $formula->output_product_id, 'role' => 'material', 'planned_qty' => $count, 'actual_qty' => $count, 'unit_cost' => $packCost, 'total_cost' => $totalValue]);
            $this->stock->change($outletId, $formula->output, Qty::negate($count), 'production_out', $order, note: "Dibongkar {$order->number}", userId: $user->id);

            $order->items()->create(['product_id' => $bulkItem->product_id, 'role' => 'output', 'planned_qty' => $plannedBulk, 'actual_qty' => $bulk, 'unit_cost' => $bulkCost, 'total_cost' => Qty::money($bulkCost, $bulk)]);
            $this->stock->change($outletId, $bulkItem->product, $bulk, 'production_in', $order, $bulkCost, "Hasil bongkar {$order->number}", $user->id);

            foreach ($returned as [$product, $qty, $cost]) {
                $order->items()->create(['product_id' => $product->id, 'role' => 'packaging', 'planned_qty' => $qty, 'actual_qty' => $qty, 'unit_cost' => $cost, 'total_cost' => Qty::money($cost, $qty)]);
                $this->stock->change($outletId, $product, $qty, 'production_in', $order, $cost, "Karung kembali {$order->number}", $user->id);
            }

            return $order;
        });
    }

    /**
     * Simpan resep olah / kemas.
     *
     * @param  array{kind:string, name:string, output_product_id:int, output_qty:string|float, cost_per_batch?:int|null, note?:string|null,
     *               items: list<array{product_id:int, qty:string|float}>}  $data
     */
    public function saveFormula(array $data, ?ProductionFormula $formula = null): ProductionFormula
    {
        if (collect($data['items'])->contains(fn ($i) => (int) $i['product_id'] === (int) $data['output_product_id'])) {
            throw ValidationException::withMessages(['items' => 'Barang hasil tidak boleh sekaligus jadi bahannya.']);
        }

        return DB::transaction(function () use ($data, $formula) {
            $attributes = [
                'kind' => $data['kind'],
                'name' => $data['name'],
                'output_product_id' => $data['output_product_id'],
                'output_qty' => Qty::normalize($data['output_qty']),
                'cost_per_batch' => (int) ($data['cost_per_batch'] ?? 0),
                'note' => $data['note'] ?? null,
            ];

            $formula ? $formula->update($attributes) : $formula = ProductionFormula::create($attributes + ['is_active' => true]);

            $formula->items()->delete();
            foreach (array_values($data['items']) as $i => $item) {
                $formula->items()->create(['product_id' => $item['product_id'], 'qty' => Qty::normalize($item['qty']), 'sort_order' => $i]);
            }

            return $formula->load('items');
        });
    }
}
