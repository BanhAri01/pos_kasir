<?php

namespace App\Modules\EggCollector\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Penanda barang telur: Telur Campur (bahan sortir) atau jenis hasil sortir. */
class EggProduct extends Model
{
    use BelongsToTenant;

    public const ROLES = ['mixed' => 'Telur campur', 'grade' => 'Hasil sortir'];

    protected $fillable = ['product_id', 'role', 'sort_order'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
