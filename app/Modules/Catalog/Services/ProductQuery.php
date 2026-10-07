<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Builder;

/**
 * Query barang lengkap dengan stok & status "Habis hari ini" di satu outlet.
 */
class ProductQuery
{
    /** @param  bool  $withLocation  ikut memuat blok/rak penyimpanan (halaman stok gudang) */
    public static function forOutlet(int $outletId, bool $withLocation = false): Builder
    {
        return Product::query()->with([
            'category:id,name',
            'unit:id,name,symbol,allow_decimal',
            'stocks' => fn ($q) => $q->where('outlet_id', $outletId)->when($withLocation, fn ($q) => $q->with('location:id,name')),
            'outlets' => fn ($q) => $q->where('outlets.id', $outletId),
        ]);
    }

    /** Cari berdasarkan nama, kode barang, atau barcode. */
    public static function search(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('code', $term)
                ->orWhere('barcode', $term);
        });
    }
}
