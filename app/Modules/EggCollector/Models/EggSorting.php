<?php

namespace App\Modules\EggCollector\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Models\User;
use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Satu kali sortir: telur campur keluar, jenis-jenis telur masuk, sisanya pecah/susut. */
class EggSorting extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'outlet_id', 'uuid', 'number', 'mixed_product_id', 'input_qty', 'output_qty', 'shrinkage_qty',
        'input_cost', 'shrinkage_cost', 'note', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'input_qty' => 'decimal:3',
            'output_qty' => 'decimal:3',
            'shrinkage_qty' => 'decimal:3',
            'input_cost' => 'integer',
            'shrinkage_cost' => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(EggSortingItem::class);
    }

    public function mixed(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'mixed_product_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
