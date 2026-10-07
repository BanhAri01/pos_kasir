<?php

namespace App\Modules\Catalog\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Modules\Customer\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Tipe harga, mis. Grosir / Tukang / Kontraktor. */
class PriceLevel extends Model
{
    use BelongsToTenant;

    protected $fillable = ['name', 'sort_order'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }
}
