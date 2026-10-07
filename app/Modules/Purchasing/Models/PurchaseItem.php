<?php

namespace App\Modules\Purchasing\Models;

use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Barang dalam satu belanja. */
class PurchaseItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['product_id', 'product_unit_id', 'qty', 'conversion_qty', 'unit_cost', 'subtotal',
        'pack_count', 'pack_weight', 'received_qty', 'weight_diff', 'extra_cost', 'landed_unit_cost'];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'conversion_qty' => 'decimal:3',
            'unit_cost' => 'integer',
            'subtotal' => 'integer',
            'pack_count' => 'decimal:3',
            'pack_weight' => 'decimal:3',
            'received_qty' => 'decimal:3',
            'weight_diff' => 'decimal:3',
            'extra_cost' => 'integer',
            'landed_unit_cost' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }
}
