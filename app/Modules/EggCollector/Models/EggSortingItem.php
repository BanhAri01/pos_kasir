<?php

namespace App\Modules\EggCollector\Models;

use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EggSortingItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['product_id', 'qty', 'sell_price', 'unit_cost', 'total_cost'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:3', 'sell_price' => 'integer', 'unit_cost' => 'integer', 'total_cost' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
