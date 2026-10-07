<?php

namespace App\Modules\Catalog\Models;

use App\Core\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Bahan baku untuk satu menu (jumlah per 1 porsi/gelas). */
class RecipeItem extends Model
{
    use BelongsToTenant;

    protected $fillable = ['product_id', 'ingredient_id', 'qty'];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'ingredient_id');
    }
}
