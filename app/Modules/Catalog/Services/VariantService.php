<?php

namespace App\Modules\Catalog\Services;

use App\Core\Support\Qty;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Services\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Varian ukuran x warna (modul variant_matrix).
 *
 * Satu model (induk) punya pilihan, misalnya Ukuran [S, M, L] dan Warna [Hitam, Putih].
 * Setiap kombinasi menjadi barang sendiri (stok, SKU, barcode, harga sendiri) yang menginduk ke model.
 * Kombinasi yang dihapus dari tabel tidak dihapus permanen, hanya disembunyikan (riwayat jual tetap utuh).
 */
class VariantService
{
    public const MAX_DIMENSIONS = 2;

    public function __construct(private StockService $stock) {}

    /**
     * @param  list<array{name:string, values:list<string>}>  $options
     * @return list<array<string,string>> semua kombinasi, contoh [["Ukuran"=>"S","Warna"=>"Hitam"], ...]
     */
    public static function combinations(array $options): array
    {
        $result = [[]];
        foreach ($options as $option) {
            $next = [];
            foreach ($result as $partial) {
                foreach ($option['values'] as $value) {
                    $next[] = [...$partial, $option['name'] => $value];
                }
            }
            $result = $next;
        }

        return $options ? $result : [];
    }

    /**
     * @param  list<array{name:string, values:list<string>}>  $options
     * @param  list<array{values:array<string,string>, code?:string|null, barcode?:string|null, price:int, min_stock?:string|null, initial_stock?:string|null}>  $rows
     */
    public function sync(Product $model, array $options, array $rows, int $outletId, bool $autoBarcode = false): Product
    {
        $options = $this->cleanOptions($options);
        if ($options === []) {
            throw ValidationException::withMessages(['options' => 'Isi minimal satu pilihan, misalnya Ukuran: S, M, L.']);
        }

        $valid = collect(self::combinations($options))->map(fn ($c) => $this->key($c));

        return DB::transaction(function () use ($model, $options, $rows, $outletId, $autoBarcode, $valid) {
            $model->update(['has_variants' => true, 'variant_options' => $options]);

            $existing = Product::withTrashed()->where('parent_id', $model->id)->get()->keyBy(fn (Product $p) => $this->key($p->variant_values ?? []));
            $kept = [];

            foreach ($rows as $row) {
                $values = array_map('strval', $row['values']);
                $key = $this->key($values);
                if (! $valid->contains($key) || isset($kept[$key])) {
                    continue; // baris yang tidak cocok dengan pilihan diabaikan
                }
                $kept[$key] = true;

                $attributes = [
                    'name' => $model->name.' - '.implode(' / ', array_values($values)),
                    'category_id' => $model->category_id,
                    'base_unit_id' => $model->base_unit_id,
                    'cost_price' => $model->cost_price,
                    'price' => (int) $row['price'],
                    'code' => ($row['code'] ?? null) ?: $this->sku($model, $values),
                    'barcode' => ($row['barcode'] ?? null) ?: null,
                    'min_stock' => isset($row['min_stock']) && $row['min_stock'] !== '' ? Qty::normalize($row['min_stock']) : null,
                    'variant_values' => $values,
                    'is_active' => true,
                ];

                if ($variant = $existing->get($key)) {
                    if ($variant->trashed()) {
                        $variant->restore();
                    }
                    $variant->update($attributes);
                } else {
                    $variant = Product::create($attributes + [
                        'uuid' => (string) Str::uuid(),
                        'parent_id' => $model->id,
                        'type' => 'goods',
                        'pricing_mode' => 'fixed',
                        'track_stock' => true,
                    ]);

                    if (! empty($row['initial_stock']) && Qty::cmp(Qty::normalize($row['initial_stock']), '0') > 0) {
                        $this->stock->change($outletId, $variant, Qty::normalize($row['initial_stock']), 'initial', unitCost: $model->cost_price, note: 'Stok awal varian');
                    }
                }

                if ($autoBarcode && ! $variant->barcode) {
                    $variant->update(['barcode' => self::internalBarcode($variant->id)]);
                }
            }

            // Kombinasi yang tidak ada lagi: disembunyikan dari kasir.
            $existing->reject(fn ($p, $key) => isset($kept[$key]))->each(fn (Product $p) => $p->update(['is_active' => false]));

            $this->ensureUniqueBarcodes($model);

            return $model->load('variants');
        });
    }

    /** Nama, kategori, satuan, dan modal model ikut ke semua variannya (dipanggil setelah model diubah). */
    public function refreshFromModel(Product $model): void
    {
        if (! $model->has_variants) {
            return;
        }

        foreach ($model->variants()->get() as $variant) {
            $variant->update([
                'name' => $model->name.' - '.implode(' / ', array_values($variant->variant_values ?? [])),
                'category_id' => $model->category_id,
                'base_unit_id' => $model->base_unit_id,
                'cost_price' => $model->cost_price,
            ]);
        }
    }

    /**
     * Barcode toko (EAN-13 berawalan 20, khusus pemakaian internal), unik karena memakai id barang.
     */
    public static function internalBarcode(int $productId): string
    {
        $digits = '20'.str_pad((string) $productId, 10, '0', STR_PAD_LEFT);
        $sum = 0;
        foreach (str_split($digits) as $i => $digit) {
            $sum += (int) $digit * ($i % 2 === 0 ? 1 : 3);
        }

        return $digits.((10 - $sum % 10) % 10);
    }

    private function ensureUniqueBarcodes(Product $model): void
    {
        $codes = Product::query()->where('parent_id', $model->id)->whereNotNull('barcode')->pluck('barcode', 'id');
        $taken = Product::query()->whereIn('barcode', $codes->values())->whereNotIn('id', $codes->keys())->pluck('barcode');
        $duplicates = $codes->duplicates()->merge($taken)->unique();

        if ($duplicates->isNotEmpty()) {
            throw ValidationException::withMessages(['rows' => 'Barcode '.$duplicates->join(', ').' sudah dipakai barang lain. Kosongkan supaya dibuat otomatis.']);
        }
    }

    /** @return list<array{name:string, values:list<string>}> */
    private function cleanOptions(array $options): array
    {
        return collect($options)
            ->map(fn ($o) => ['name' => trim((string) ($o['name'] ?? '')), 'values' => collect($o['values'] ?? [])->map(fn ($v) => trim((string) $v))->filter()->unique()->values()->all()])
            ->filter(fn ($o) => $o['name'] !== '' && $o['values'] !== [])
            ->take(self::MAX_DIMENSIONS)
            ->values()
            ->all();
    }

    /** Kunci kombinasi yang tidak tergantung urutan: "ukuran=m|warna=hitam". */
    private function key(array $values): string
    {
        $pairs = collect($values)->map(fn ($v, $k) => Str::lower(trim((string) $k)).'='.Str::lower(trim((string) $v)))->sort()->values();

        return $pairs->join('|');
    }

    /** SKU otomatis: KODE-MODEL-M-HTM */
    private function sku(Product $model, array $values): string
    {
        $base = $model->code ?: Str::upper(Str::substr(Str::slug($model->name, ''), 0, 6)).$model->id;
        $parts = array_map(fn ($v) => Str::upper(Str::substr(Str::slug((string) $v, ''), 0, 3)), array_values($values));

        return Str::limit($base.'-'.implode('-', $parts), 50, '');
    }
}
