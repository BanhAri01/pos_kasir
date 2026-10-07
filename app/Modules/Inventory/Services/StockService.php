<?php

namespace App\Modules\Inventory\Services;

use App\Core\Support\Qty;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * SATU-SATUNYA jalan untuk mengubah stok.
 *
 * Setiap perubahan:
 *  - berjalan di dalam database transaction dengan row lock (aman dipakai beberapa kasir sekaligus),
 *  - menulis catatan di stock_movements (audit trail),
 *  - memperbarui modal rata-rata saat ada stok masuk dengan harga modal.
 *
 * Stok BOLEH minus (keputusan yang disetujui): penjualan yang sudah terjadi, terutama
 * dari kasir offline, tidak boleh ditolak. Pemilik diberi tahu lewat penanda stok minus.
 */
class StockService
{
    /**
     * @param  string  $qtyChange  positif = masuk, negatif = keluar (dalam satuan dasar)
     * @param  array  $batch  info batch untuk stok masuk (modul batch_lot), lihat BatchService::apply()
     */
    public function change(
        int $outletId,
        Product $product,
        string|int|float $qtyChange,
        string $type,
        ?Model $reference = null,
        ?int $unitCost = null,
        ?string $note = null,
        ?int $userId = null,
        array $batch = [],
    ): ?StockMovement {
        if (! $product->tracksStock()) {
            return null;
        }

        $change = Qty::normalize($qtyChange);

        if (Qty::isZero($change)) {
            return null;
        }

        return DB::transaction(function () use ($outletId, $product, $change, $type, $reference, $unitCost, $note, $userId, $batch) {
            $stock = $this->lockedStock($outletId, $product);

            $before = Qty::normalize($stock->qty);
            $after = Qty::add($before, $change);

            // Modal rata-rata bergerak: hanya dihitung ulang saat stok MASUK dengan harga modal.
            if ($unitCost !== null && ! Qty::isNegative($change)) {
                $stock->avg_cost = $this->movingAverage($before, (int) $stock->avg_cost, $change, $unitCost);
            }

            $stock->qty = $after;
            $stock->save();

            $movement = StockMovement::create([
                'outlet_id' => $outletId,
                'product_id' => $product->id,
                'type' => $type,
                'qty_change' => $change,
                'qty_before' => $before,
                'qty_after' => $after,
                'unit_cost' => $unitCost ?? (int) $stock->avg_cost,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'user_id' => $userId ?? auth()->id(),
                'note' => $note,
            ]);

            $batches = app(BatchService::class);
            if ($batches->enabledFor($product)) {
                $batches->apply($movement, $product, $batch);
            }

            return $movement;
        });
    }

    /** Samakan stok dengan hasil hitung (stock opname). */
    public function setTo(int $outletId, Product $product, string|int|float $countedQty, ?Model $reference = null, ?string $note = null): ?StockMovement
    {
        return DB::transaction(function () use ($outletId, $product, $countedQty, $reference, $note) {
            $current = Qty::normalize($this->lockedStock($outletId, $product)->qty);

            return $this->change($outletId, $product, Qty::sub(Qty::normalize($countedQty), $current), 'opname', $reference, null, $note);
        });
    }

    public function qty(int $outletId, Product $product): string
    {
        return Qty::normalize(
            Stock::query()->where('outlet_id', $outletId)->where('product_id', $product->id)->value('qty') ?? '0'
        );
    }

    /** Simpan blok/rak penyimpanan barang di satu gudang (tidak mengubah jumlah stok). */
    public function setLocation(int $outletId, Product $product, ?int $locationId): void
    {
        DB::transaction(function () use ($outletId, $product, $locationId) {
            $this->lockedStock($outletId, $product)->update(['location_id' => $locationId]);
        });
    }

    /** Modal rata-rata per satuan dasar di satu outlet/gudang (modal barang kalau belum pernah ada stok). */
    public function avgCost(int $outletId, Product $product): int
    {
        $cost = Stock::query()->where('outlet_id', $outletId)->where('product_id', $product->id)->value('avg_cost');

        return (int) ($cost ?? $product->cost_price);
    }

    private function lockedStock(int $outletId, Product $product): Stock
    {
        $stock = Stock::query()
            ->where('outlet_id', $outletId)
            ->where('product_id', $product->id)
            ->lockForUpdate()
            ->first();

        if ($stock) {
            return $stock;
        }

        // Baris stok pertama untuk barang ini di outlet ini.
        Stock::query()->insertOrIgnore([
            'tenant_id' => $product->tenant_id,
            'outlet_id' => $outletId,
            'product_id' => $product->id,
            'qty' => 0,
            'avg_cost' => $product->cost_price,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Stock::query()
            ->where('outlet_id', $outletId)
            ->where('product_id', $product->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function movingAverage(string $beforeQty, int $beforeCost, string $inQty, int $inCost): int
    {
        if (Qty::cmp($beforeQty, '0') <= 0) {
            return $inCost;
        }

        $totalValue = bcadd(bcmul($beforeQty, (string) $beforeCost, 3), bcmul($inQty, (string) $inCost, 3), 3);
        $totalQty = Qty::add($beforeQty, $inQty);

        return (int) round((float) bcdiv($totalValue, $totalQty, 4));
    }
}
