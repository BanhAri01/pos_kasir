<?php

namespace App\Modules\Catalog\Services;

use App\Core\Support\Qty;
use App\Models\Tenant;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductUnit;

/**
 * Bagian tambahan formulir barang yang tergantung modul:
 * resep (recipe), satuan lain (multi_unit), harga grosir / tipe harga (price_levels),
 * dan pilihan & tambahan (variants_modifiers).
 *
 * Setiap bagian hanya disentuh kalau datanya dikirim DAN modulnya aktif, supaya
 * mematikan modul tidak menghapus data yang sudah diisi.
 */
class ProductExtrasService
{
    public function sync(Product $product, array $data, Tenant $tenant): void
    {
        if (array_key_exists('recipe', $data) && $tenant->hasModule('recipe')) {
            $this->syncRecipe($product, $data['recipe'] ?? []);
        }

        if (array_key_exists('units', $data) && $tenant->hasModule('multi_unit')) {
            $this->syncUnits($product, $data['units'] ?? []);
        }

        if (array_key_exists('prices', $data) && $tenant->hasModule('price_levels')) {
            $this->syncPrices($product, $data['prices'] ?? []);
        }

        if (array_key_exists('modifier_group_ids', $data) && $tenant->hasModule('variants_modifiers')) {
            $product->modifierGroups()->sync(
                collect($data['modifier_group_ids'] ?? [])->values()->mapWithKeys(fn ($id, $i) => [$id => ['sort_order' => $i]])->all()
            );
        }

        $product->touch(); // kasir offline ikut menerima perubahan saat sinkron
    }

    /** Data untuk mengisi formulir. */
    public function formData(?Product $product): array
    {
        if (! $product) {
            return ['recipe' => [], 'units' => [], 'prices' => [], 'modifier_group_ids' => []];
        }

        $product->loadMissing(['recipeItems.ingredient:id,name', 'units', 'prices', 'modifierGroups:id']);
        $unitIdByProductUnit = $product->units->pluck('unit_id', 'id');

        return [
            'recipe' => $product->recipeItems->map(fn ($r) => ['ingredient_id' => $r->ingredient_id, 'name' => $r->ingredient?->name, 'qty' => Qty::display($r->qty)])->values(),
            'units' => $product->units->map(fn ($u) => ['id' => $u->id, 'unit_id' => $u->unit_id, 'conversion_qty' => Qty::display($u->conversion_qty), 'price' => $u->price, 'barcode' => $u->barcode])->values(),
            'prices' => $product->prices->map(fn ($p) => [
                'price_level_id' => $p->price_level_id,
                'unit_id' => $p->product_unit_id ? $unitIdByProductUnit[$p->product_unit_id] ?? null : null,
                'min_qty' => Qty::display($p->min_qty),
                'price' => $p->price,
            ])->values(),
            'modifier_group_ids' => $product->modifierGroups->pluck('id')->values(),
        ];
    }

    private function syncRecipe(Product $product, array $rows): void
    {
        $keep = [];
        foreach ($rows as $row) {
            if ((int) $row['ingredient_id'] === $product->id) {
                continue; // menu tidak boleh menjadi bahan dirinya sendiri
            }
            $product->recipeItems()->updateOrCreate(['ingredient_id' => $row['ingredient_id']], ['qty' => Qty::normalize($row['qty'])]);
            $keep[] = (int) $row['ingredient_id'];
        }
        $product->recipeItems()->whereNotIn('ingredient_id', $keep)->delete();
    }

    private function syncUnits(Product $product, array $rows): void
    {
        $keep = [];
        foreach ($rows as $row) {
            if ((int) $row['unit_id'] === (int) $product->base_unit_id) {
                continue; // satuan dasar sudah ada di harga utama
            }
            $unit = $product->units()->updateOrCreate(['unit_id' => $row['unit_id']], [
                'conversion_qty' => Qty::normalize($row['conversion_qty']),
                'price' => (int) $row['price'],
                'barcode' => $row['barcode'] ?? null,
            ]);
            $keep[] = $unit->id;
        }
        $product->units()->whereNotIn('id', $keep)->delete();
    }

    private function syncPrices(Product $product, array $rows): void
    {
        $productUnits = ProductUnit::query()->where('product_id', $product->id)->pluck('id', 'unit_id');

        $product->prices()->delete();
        foreach ($rows as $row) {
            $unitId = $row['unit_id'] ?? null;
            $product->prices()->create([
                'price_level_id' => $row['price_level_id'] ?? null,
                'product_unit_id' => $unitId && (int) $unitId !== (int) $product->base_unit_id ? ($productUnits[$unitId] ?? null) : null,
                'min_qty' => Qty::normalize($row['min_qty'] ?? 1),
                'price' => (int) $row['price'],
            ]);
        }
    }
}
