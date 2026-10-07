<?php

namespace App\Modules\Catalog\Models;

use App\Core\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Harga khusus: per tipe pelanggan dan/atau mulai jumlah tertentu (grosir). */
class ProductPrice extends Model
{
    use BelongsToTenant;

    protected $fillable = ['product_id', 'product_unit_id', 'price_level_id', 'min_qty', 'price'];

    protected function casts(): array
    {
        return [
            'min_qty' => 'decimal:3',
            'price' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(PriceLevel::class, 'price_level_id');
    }
}
