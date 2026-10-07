<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockOpnameItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['product_id', 'system_qty', 'counted_qty', 'difference', 'unit_cost'];

    protected function casts(): array
    {
        return ['system_qty' => 'decimal:3', 'counted_qty' => 'decimal:3', 'difference' => 'decimal:3', 'unit_cost' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
