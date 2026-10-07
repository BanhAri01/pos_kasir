<?php

namespace App\Modules\Warehouse\Models;

use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Bahan terpakai (material/packaging) atau hasil (output) dalam satu kali olah. */
class ProductionOrderItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['product_id', 'role', 'planned_qty', 'actual_qty', 'unit_cost', 'total_cost'];

    protected function casts(): array
    {
        return ['planned_qty' => 'decimal:3', 'actual_qty' => 'decimal:3', 'unit_cost' => 'integer', 'total_cost' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
