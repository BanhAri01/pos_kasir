<?php

namespace App\Modules\Pos\Services;

use App\Core\Support\Qty;
use App\Models\Tenant;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Pos\Models\Sale;

/**
 * Memotong stok untuk satu barang terjual dan menghitung modalnya (HPP).
 *
 *  - Menu dengan resep (modul recipe): stok BAHAN yang dipotong, modal = jumlah modal bahan.
 *  - Barang biasa: stok barang itu sendiri (x konversi satuan), modal = modal rata-rata.
 */
class SaleStockService
{
    public function __construct(private StockService $stock) {}

    /**
     * @return int modal total baris (rupiah)
     */
    public function deduct(Tenant $tenant, Sale $sale, Product $product, string $qty, string $conversion, int $userId): int
    {
        $baseQty = Qty::mul($qty, $conversion);

        if ($tenant->hasModule('recipe')) {
            $recipe = $product->recipeItems()->with('ingredient')->get();

            if ($recipe->isNotEmpty()) {
                $cost = 0;
                foreach ($recipe as $item) {
                    if (! $item->ingredient) {
                        continue;
                    }
                    $used = Qty::mul($baseQty, $item->qty);
                    $cost += Qty::money($this->avgCost($sale->outlet_id, $item->ingredient), $used);
                    $this->stock->change($sale->outlet_id, $item->ingredient, Qty::negate($used), 'recipe_usage', $sale, note: "Untuk {$product->name}", userId: $userId);
                }

                return $cost;
            }
        }

        $cost = Qty::money($this->avgCost($sale->outlet_id, $product), $baseQty);
        $this->stock->change($sale->outlet_id, $product, Qty::negate($baseQty), 'sale', $sale, userId: $userId);

        return $cost;
    }

    /** Kembalikan stok (void / refund). */
    public function restore(Tenant $tenant, Sale $sale, Product $product, string $qty, string $conversion, string $type, $reference, int $userId): void
    {
        $baseQty = Qty::mul($qty, $conversion);

        if ($tenant->hasModule('recipe') && ($recipe = $product->recipeItems()->with('ingredient')->get())->isNotEmpty()) {
            foreach ($recipe as $item) {
                if ($item->ingredient) {
                    $this->stock->change($sale->outlet_id, $item->ingredient, Qty::mul($baseQty, $item->qty), $type, $reference, userId: $userId);
                }
            }

            return;
        }

        $this->stock->change($sale->outlet_id, $product, $baseQty, $type, $reference, userId: $userId);
    }

    private function avgCost(int $outletId, Product $product): int
    {
        return (int) (Stock::query()->where('outlet_id', $outletId)->where('product_id', $product->id)->value('avg_cost') ?? $product->cost_price);
    }
}
