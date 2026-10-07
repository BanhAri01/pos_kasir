<?php

namespace App\Modules\Catalog\Http\Resources;

use App\Core\Support\Qty;
use App\Core\Tenancy\TenantContext;
use App\Modules\Catalog\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 *
 * Stok yang ditampilkan adalah stok di outlet aktif (relasi `stocks` sudah difilter per outlet).
 */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $stockRow = $this->relationLoaded('stocks') ? $this->stocks->first() : null;
        $stockQty = $this->relationLoaded('stocks') ? ($stockRow?->qty ?? '0') : null;
        $pivot = $this->relationLoaded('outlets') ? $this->outlets->first()?->pivot : null;
        $tracks = $this->tracksStock();

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'type' => $this->type,
            'category_id' => $this->category_id,
            // Varian ukuran x warna (modul variant_matrix)
            'parent_id' => $this->parent_id,
            'has_variants' => (bool) $this->has_variants,
            'variant_options' => $this->has_variants ? ($this->variant_options ?? []) : null,
            'variant_values' => $this->variant_values,
            'consignor_id' => $this->consignor_id,
            'consignment_share' => $this->consignment_share_bp !== null ? rtrim(rtrim(number_format($this->consignment_share_bp / 100, 2, ',', ''), '0'), ',') : null,
            'category' => $this->whenLoaded('category', fn () => $this->category?->name),
            'price' => $this->price,
            'cost_price' => $this->cost_price,
            'pricing_mode' => $this->pricing_mode,
            'unit_id' => $this->base_unit_id,
            'unit' => $this->whenLoaded('unit', fn () => $this->unit?->name),
            'unit_allows_decimal' => $this->whenLoaded('unit', fn () => (bool) $this->unit?->allow_decimal),
            'track_stock' => $tracks,
            'stock' => $tracks && $stockQty !== null ? Qty::display($stockQty) : null,
            'stock_raw' => $tracks && $stockQty !== null ? (float) $stockQty : null,
            'min_stock' => $this->min_stock !== null ? Qty::display($this->min_stock) : null,
            // Barang curah: "1.250 kg = setara 25 karung" (modul base_unit_stock)
            'pack_size' => $this->pack_size !== null ? Qty::display($this->pack_size) : null,
            'pack_name' => $this->pack_name,
            'pack_weight_fixed' => (bool) $this->pack_weight_fixed,
            'is_packaging' => (bool) $this->is_packaging,
            'track_batch' => (bool) $this->track_batch,
            'pack_equivalent' => $tracks && $stockQty !== null && $this->pack_size !== null && Qty::cmp($this->pack_size, '0') > 0
                ? Qty::display(bcdiv(Qty::normalize($stockQty), Qty::normalize($this->pack_size), 1))
                : null,
            'location' => $stockRow?->relationLoaded('location') ? $stockRow->location?->name : null,
            'location_id' => $stockRow?->location_id,
            'is_low_stock' => $tracks && $stockQty !== null && $this->min_stock !== null && Qty::cmp($stockQty, $this->min_stock) <= 0,
            'is_out_of_stock' => $tracks && $stockQty !== null && Qty::cmp($stockQty, '0') <= 0,
            'sold_out_today' => $pivot?->sold_out_on !== null
                && substr((string) $pivot->sold_out_on, 0, 10) === now(app(TenantContext::class)->get()?->timezone ?? 'Asia/Jakarta')->toDateString(),
            'duration_minutes' => $this->duration_minutes,
            'code' => $this->code,
            'barcode' => $this->barcode,
            'description' => $this->description,
            'image_url' => $this->imageUrl(),
            'is_sample' => $this->is_sample,
            'is_active' => $this->is_active,
        ];
    }
}
