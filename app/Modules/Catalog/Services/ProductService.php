<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Services\StockService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductService
{
    public function __construct(
        private StockService $stock,
        private ProductImageService $images,
    ) {}

    /**
     * @param  array<string, mixed>  $data  data tervalidasi dari ProductRequest
     */
    public function create(array $data, int $outletId, ?UploadedFile $image = null): Product
    {
        return DB::transaction(function () use ($data, $outletId, $image) {
            $product = Product::create([
                ...$this->attributes($data),
                'uuid' => (string) Str::uuid(),
                'is_active' => true,
            ]);

            if ($image) {
                $product->update(['image_path' => $this->images->store($product, $image)]);
            }

            if ($product->tracksStock() && ! empty($data['initial_stock'])) {
                $this->stock->change($outletId, $product, (string) $data['initial_stock'], 'initial', unitCost: $product->cost_price);
            }

            return $product;
        });
    }

    public function update(Product $product, array $data, ?UploadedFile $image = null): Product
    {
        return DB::transaction(function () use ($product, $data, $image) {
            $product->fill($this->attributes($data));

            if ($image) {
                $product->image_path = $this->images->store($product, $image);
            } elseif (! empty($data['remove_image'])) {
                $this->images->delete($product);
                $product->image_path = null;
            }

            // Barang yang diubah oleh pemilik bukan lagi "data contoh".
            $product->is_sample = false;
            $product->save();

            return $product;
        });
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }

    public function restore(Product $product): void
    {
        $product->restore();
    }

    /** "Habis hari ini": berlaku untuk outlet ini saja, otomatis normal lagi besok. */
    public function setSoldOutToday(Product $product, int $outletId, bool $soldOut, string $timezone): void
    {
        $product->outlets()->syncWithoutDetaching([
            $outletId => ['sold_out_on' => $soldOut ? now($timezone)->toDateString() : null],
        ]);
        $product->touch(); // supaya kasir offline ikut menerima perubahan saat sinkron
    }

    private function attributes(array $data): array
    {
        $categoryId = $data['category_id'] ?? null;

        // Kategori baru bisa diketik langsung dari form barang.
        if (! empty($data['new_category'])) {
            $categoryId = Category::firstOrCreate(['name' => trim($data['new_category'])])->id;
        }

        $type = $data['type'] ?? 'goods';

        return [
            'name' => $data['name'],
            'category_id' => $categoryId,
            'type' => $type,
            'code' => $data['code'] ?? null,
            'barcode' => $data['barcode'] ?? null,
            'base_unit_id' => $data['base_unit_id'] ?? null,
            'price' => (int) $data['price'],
            'cost_price' => (int) ($data['cost_price'] ?? 0),
            'pricing_mode' => $data['pricing_mode'] ?? 'fixed',
            'track_stock' => $type === 'service' ? false : (bool) ($data['track_stock'] ?? true),
            'min_stock' => $data['min_stock'] ?? null,
            'pack_size' => $data['pack_size'] ?? null,
            'pack_weight_fixed' => ! empty($data['pack_size']) && (bool) ($data['pack_weight_fixed'] ?? false),
            'is_packaging' => $type === 'ingredient' && (bool) ($data['is_packaging'] ?? false),
            'consignor_id' => $data['consignor_id'] ?? null,
            'consignment_share_bp' => ! empty($data['consignor_id']) ? (int) round(((float) ($data['consignment_share'] ?? 0)) * 100) : null,
            'track_batch' => $type !== 'service' && (bool) ($data['track_batch'] ?? false),
            'pack_name' => ! empty($data['pack_size']) ? (trim((string) ($data['pack_name'] ?? '')) ?: 'karung') : null,
            'duration_minutes' => $data['duration_minutes'] ?? null,
            'description' => $data['description'] ?? null,
        ];
    }
}
