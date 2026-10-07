<?php

namespace App\Modules\Pos\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'sale_id', 'product_id', 'product_unit_id', 'name', 'unit_name', 'qty', 'conversion_qty', 'unit_price',
        'original_price', 'discount_amount', 'subtotal', 'cost_amount', 'note', 'staff_id', 'kitchen_status',
        'refunded_qty', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'conversion_qty' => 'decimal:3',
            'unit_price' => 'integer',
            'original_price' => 'integer',
            'discount_amount' => 'integer',
            'subtotal' => 'integer',
            'cost_amount' => 'integer',
            'refunded_qty' => 'decimal:3',
            'meta' => 'array',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function modifiers(): HasMany
    {
        return $this->hasMany(SaleItemModifier::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
