<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransferItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['product_id', 'qty', 'unit_cost'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:3', 'unit_cost' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
