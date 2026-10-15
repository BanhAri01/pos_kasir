<?php

namespace App\Modules\EggCollector\Services;

use App\Core\Support\NumberSequence;
use App\Core\Support\Qty;
use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\EggCollector\Models\EggProduct;
use App\Modules\EggCollector\Models\EggSorting;
use App\Modules\Inventory\Services\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Sortir telur campur menjadi jenis-jenis telur.
 *
 *  1. Telur campur keluar dari stok sebanyak yang disortir.
 *  2. Pecah/susut = berat campur - total berat hasil. Nilainya (modal per kg x susut) dicatat sebagai rugi susut.
 *  3. Sisa modal dibagi ke hasil menurut nilai jual hari itu (berat x harga jual), jadi telur besar menanggung
 *     modal lebih besar daripada telur retak. Pembagian memakai sisa terbesar sehingga jumlahnya tepat.
 *  4. Hasil masuk ke stok dengan modal per kg masing-masing.
 */
class EggSortingService
{
    public function __construct(
        private StockService $stock,
        private NumberSequence $numbers,
        private EggPriceService $prices,
    ) {}

    /**
     * @param  array{mixed_product_id?:int|null, input_qty:string|int|float, outputs:list<array{product_id:int, qty:string|int|float}>, note?:string|null}  $data
     */
    public function sort(array $data, int $outletId, User $user): EggSorting
    {
        $mixedId = (int) ($data['mixed_product_id'] ?? 0)
            ?: (int) EggProduct::query()->where('role', 'mixed')->orderBy('sort_order')->value('product_id');
        $mixedEgg = EggProduct::query()->where('role', 'mixed')->where('product_id', $mixedId)->with('product')->first();
        if (! $mixedEgg?->product) {
            throw ValidationException::withMessages(['mixed_product_id' => 'Pilih telur campur yang akan disortir.']);
        }

        $input = Qty::normalize($data['input_qty']);
        if (Qty::cmp($input, '0') <= 0) {
            throw ValidationException::withMessages(['input_qty' => 'Isi berapa kg telur campur yang disortir.']);
        }

        $grades = EggProduct::query()->where('role', 'grade')->with('product')->get()->keyBy('product_id');
        $outputs = collect($data['outputs'] ?? [])
            ->map(fn ($row) => ['product_id' => (int) $row['product_id'], 'qty' => Qty::normalize($row['qty'] ?? '0')]);

        if ($outputs->contains(fn ($row) => Qty::isNegative($row['qty']))) {
            throw ValidationException::withMessages(['outputs' => 'Berat hasil sortir tidak boleh minus.']);
        }

        $outputs = $outputs->filter(fn ($row) => Qty::cmp($row['qty'], '0') > 0)->values();
        if ($outputs->isEmpty()) {
            throw ValidationException::withMessages(['outputs' => 'Isi berat hasil sortir, minimal satu jenis.']);
        }
        if ($outputs->pluck('product_id')->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['outputs' => 'Satu jenis telur cukup ditulis sekali.']);
        }
        if ($outputs->contains(fn ($row) => ! isset($grades[$row['product_id']]) || ! $grades[$row['product_id']]->product)) {
            throw ValidationException::withMessages(['outputs' => 'Hasil sortir harus jenis telur yang terdaftar.']);
        }

        $outputTotal = $outputs->reduce(fn ($sum, $row) => Qty::add($sum, $row['qty']), '0');
        if (Qty::cmp($outputTotal, $input) > 0) {
            throw ValidationException::withMessages(['outputs' => 'Berat hasil sortir ('.Qty::display($outputTotal).' kg) lebih banyak dari telur campur ('.Qty::display($input).' kg).']);
        }

        return DB::transaction(function () use ($mixedEgg, $input, $outputs, $outputTotal, $grades, $outletId, $user, $data) {
            $mixed = $mixedEgg->product;
            $inputCost = Qty::money($this->stock->avgCost($outletId, $mixed), $input);
            $shrinkage = Qty::sub($input, $outputTotal);
            $shrinkageCost = (int) round((float) bcdiv(bcmul((string) $inputCost, $shrinkage, 6), $input, 6));

            $products = $outputs->map(fn ($row) => $grades[$row['product_id']]->product);
            $sellPrices = $this->prices->sellPrices($outletId, $products);
            $weights = $outputs->map(fn ($row) => max(0, Qty::money($sellPrices[$row['product_id']], $row['qty'])))->all();
            if (array_sum($weights) === 0) {
                $weights = $outputs->map(fn ($row) => (float) $row['qty'])->all();
            }
            $allocated = self::allocate($inputCost - $shrinkageCost, $weights);

            $sorting = EggSorting::create([
                'outlet_id' => $outletId,
                'uuid' => (string) Str::uuid(),
                'number' => $this->numbers->next('SR'),
                'mixed_product_id' => $mixed->id,
                'input_qty' => $input,
                'output_qty' => $outputTotal,
                'shrinkage_qty' => $shrinkage,
                'input_cost' => $inputCost,
                'shrinkage_cost' => $shrinkageCost,
                'note' => ($data['note'] ?? null) ?: null,
                'user_id' => $user->id,
            ]);

            $this->stock->change($outletId, $mixed, Qty::negate($input), 'sorting_out', $sorting, note: "Disortir {$sorting->number}", userId: $user->id);

            foreach ($outputs as $i => $row) {
                /** @var Product $product */
                $product = $grades[$row['product_id']]->product;
                $total = $allocated[$i];
                $unitCost = (int) round($total / (float) $row['qty']);

                $sorting->items()->create([
                    'product_id' => $product->id,
                    'qty' => $row['qty'],
                    'sell_price' => $sellPrices[$product->id],
                    'unit_cost' => $unitCost,
                    'total_cost' => $total,
                ]);
                $this->stock->change($outletId, $product, $row['qty'], 'sorting_in', $sorting, $unitCost, "Hasil sortir {$sorting->number}", $user->id);
            }

            return $sorting->load('items.product');
        });
    }

    /**
     * Bagi rupiah utuh sesuai bobot; selisih pembulatan diberikan ke sisa pecahan terbesar.
     *
     * @param  list<int|float>  $weights
     * @return list<int>
     */
    public static function allocate(int $amount, array $weights): array
    {
        $sum = array_sum($weights);
        if ($sum <= 0 || $amount <= 0) {
            return array_fill(0, count($weights), 0);
        }

        $exact = array_map(fn ($w) => $amount * $w / $sum, $weights);
        $result = array_map(fn ($x) => (int) floor($x), $exact);
        $left = $amount - array_sum($result);

        $order = array_keys($exact);
        usort($order, fn ($a, $b) => ($exact[$b] - $result[$b]) <=> ($exact[$a] - $result[$a]) ?: $a <=> $b);
        foreach (array_slice($order, 0, $left) as $i) {
            $result[$i]++;
        }

        return $result;
    }
}
