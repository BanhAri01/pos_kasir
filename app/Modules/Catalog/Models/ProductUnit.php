<?php

namespace App\Modules\Catalog\Models;

use App\Core\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satuan lain untuk satu barang, mis. 1 dus = 40 pcs. */
class ProductUnit extends Model
{
    use BelongsToTenant;

    protected $fillable = ['product_id', 'unit_id', 'conversion_qty', 'price', 'barcode'];

    protected function casts(): array
    {
        return [
            'conversion_qty' => 'decimal:3',
            'price' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
