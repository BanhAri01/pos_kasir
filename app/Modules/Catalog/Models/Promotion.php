<?php

namespace App\Modules\Catalog\Models;

use App\Core\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Promo / diskon musiman (modul promotions). Berlaku otomatis di kasir selama tanggalnya.
 * percent: value dalam basis point (1000 = 10%). amount: potongan rupiah per barang.
 */
class Promotion extends Model
{
    use BelongsToTenant;

    protected $fillable = ['name', 'type', 'value', 'scope', 'category_ids', 'product_ids', 'starts_on', 'ends_on', 'is_active'];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'category_ids' => 'array',
            'product_ids' => 'array',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /** Berlaku pada tanggal tertentu (Y-m-d, waktu usaha). */
    public function scopeActiveOn(Builder $query, string $date): Builder
    {
        return $query->where('is_active', true)->whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date);
    }

    /** Barang ini kena promo? Varian ikut promo modelnya. */
    public function appliesTo(Product $product): bool
    {
        return match ($this->scope) {
            'categories' => in_array($product->category_id, $this->category_ids ?? [], false),
            'products' => (bool) array_intersect([$product->id, $product->parent_id], $this->product_ids ?? []),
            default => true,
        };
    }

    /** Potongan per barang dari harga $price. Rumus yang sama ada di resources/js/pos/lib/pricing.js. */
    public function discountFor(int $price): int
    {
        return $this->type === 'percent'
            ? min($price, intdiv($price * $this->value, 10000))
            : min($price, $this->value);
    }
}
