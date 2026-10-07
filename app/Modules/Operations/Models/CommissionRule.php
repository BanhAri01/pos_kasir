<?php

namespace App\Modules\Operations\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Aturan komisi: per karyawan / per layanan / per kategori / umum. */
class CommissionRule extends Model
{
    use BelongsToTenant;

    protected $fillable = ['user_id', 'product_id', 'category_id', 'type', 'value'];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
