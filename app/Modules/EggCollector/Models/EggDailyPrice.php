<?php

namespace App\Modules\EggCollector\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Harga beli & jual telur per hari per outlet. */
class EggDailyPrice extends Model
{
    use BelongsToTenant;

    protected $fillable = ['outlet_id', 'product_id', 'price_date', 'buy_price', 'sell_price', 'user_id'];

    protected function casts(): array
    {
        return ['buy_price' => 'integer', 'sell_price' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
