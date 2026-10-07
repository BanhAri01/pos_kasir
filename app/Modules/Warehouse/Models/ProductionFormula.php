<?php

namespace App\Modules\Warehouse\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Resep olah / kemas: bahan per 1 kali olah -> hasil.
 * kind: repack (kemas ulang curah jadi karungan) | production (giling, racik pakan).
 */
class ProductionFormula extends Model
{
    use BelongsToTenant;

    public const KINDS = ['repack' => 'Kemas Ulang', 'production' => 'Olah / Produksi'];

    protected $fillable = ['kind', 'name', 'output_product_id', 'output_qty', 'cost_per_batch', 'note', 'is_active'];

    protected function casts(): array
    {
        return ['output_qty' => 'decimal:3', 'cost_per_batch' => 'integer', 'is_active' => 'boolean'];
    }

    public function output(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'output_product_id')->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductionFormulaItem::class)->orderBy('sort_order');
    }
}
