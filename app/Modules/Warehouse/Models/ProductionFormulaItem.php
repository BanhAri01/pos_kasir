<?php

namespace App\Modules\Warehouse\Models;

use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu bahan dalam resep olah/kemas (jumlah per 1 kali olah). */
class ProductionFormulaItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['product_id', 'qty', 'sort_order'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:3'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
