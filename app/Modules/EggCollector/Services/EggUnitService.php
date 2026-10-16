<?php

namespace App\Modules\EggCollector\Services;

use App\Core\Support\Qty;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductUnit;
use App\Modules\Catalog\Models\Unit;
use App\Modules\EggCollector\Models\EggProduct;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Satuan jual & beli telur selain kg: Peti, Tray, Ikat.
 *
 * Isi (kg) tiap satuan diisi sendiri oleh pemilik per jenis telur, karena berbeda di tiap pengepul.
 * Harga satuan selalu mengikuti harga jual per kg hari ini: harga per kg × isi kg.
 */
class EggUnitService
{
    public const UNITS = ['peti' => ['Peti', 'peti'], 'tray' => ['Tray', 'tray'], 'ikat' => ['Ikat', 'ikat']];

    public function __construct(private EggPriceService $prices) {}

    /** @return list<array{product_id:int, name:string, unit:string, peti:?string, tray:?string, ikat:?string}> */
    public function board(): array
    {
        $unitIds = Unit::query()->whereIn('name', array_column(self::UNITS, 0))->pluck('id', 'name');

        $eggs = $this->prices->eggProducts();
        (new EloquentCollection($eggs->pluck('product')->all()))->load('units');

        return $eggs->map(function (EggProduct $egg) use ($unitIds) {
            $row = ['product_id' => $egg->product->id, 'name' => $egg->product->name, 'unit' => $egg->product->unit?->name ?? 'Kg'];
            foreach (self::UNITS as $key => [$name]) {
                $pu = $egg->product->units->firstWhere('unit_id', $unitIds[$name] ?? 0);
                $row[$key] = $pu ? Qty::display($pu->conversion_qty) : null;
            }

            return $row;
        })->values()->all();
    }

    /**
     * @param  list<array{product_id:int, peti?:string|null, tray?:string|null, ikat?:string|null}>  $rows
     */
    public function save(array $rows, int $outletId): void
    {
        $eggs = EggProduct::query()->pluck('product_id')->map(fn ($id) => (int) $id)->all();

        DB::transaction(function () use ($rows, $outletId, $eggs) {
            $units = collect(self::UNITS)->mapWithKeys(fn ($u, $key) => [
                $key => Unit::firstOrCreate(['name' => $u[0]], ['symbol' => $u[1], 'allow_decimal' => false]),
            ]);

            foreach ($rows as $row) {
                if (! in_array((int) $row['product_id'], $eggs, true)) {
                    throw ValidationException::withMessages(['units' => 'Barang ini bukan jenis telur.']);
                }
                $product = Product::query()->findOrFail($row['product_id']);

                foreach ($units as $key => $unit) {
                    $kg = isset($row[$key]) && $row[$key] !== '' && $row[$key] !== null ? Qty::normalize($row[$key]) : null;
                    if ($kg === null || Qty::cmp($kg, '0') <= 0) {
                        ProductUnit::query()->where('product_id', $product->id)->where('unit_id', $unit->id)->delete();

                        continue;
                    }
                    ProductUnit::updateOrCreate(
                        ['product_id' => $product->id, 'unit_id' => $unit->id],
                        ['conversion_qty' => $kg, 'price' => 0],
                    );
                }
            }

            $this->refreshPrices($outletId);
        });
    }

    /** Samakan harga Peti/Tray/Ikat dengan harga jual per kg hari ini (dipanggil setiap Harga Hari Ini disimpan). */
    public function refreshPrices(int $outletId): void
    {
        $unitIds = Unit::query()->whereIn('name', array_column(self::UNITS, 0))->pluck('id');
        if ($unitIds->isEmpty()) {
            return;
        }

        $products = $this->prices->eggProducts()->pluck('product');
        $sell = $this->prices->sellPrices($outletId, $products);

        ProductUnit::query()->whereIn('product_id', $products->pluck('id'))->whereIn('unit_id', $unitIds)->get()
            ->each(fn (ProductUnit $pu) => $pu->update(['price' => Qty::money($sell[$pu->product_id], Qty::normalize($pu->conversion_qty))]));
    }
}
