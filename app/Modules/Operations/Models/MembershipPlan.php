<?php

namespace App\Modules\Operations\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Paket member (harian/bulanan/tahunan) atau paket sesi personal trainer. */
class MembershipPlan extends Model
{
    use BelongsToTenant;

    protected $fillable = ['product_id', 'kind', 'duration_value', 'duration_unit', 'session_quota', 'is_active'];

    protected function casts(): array
    {
        return [
            'duration_value' => 'integer',
            'session_quota' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
