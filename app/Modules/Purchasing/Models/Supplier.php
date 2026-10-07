<?php

namespace App\Modules\Purchasing\Models;

use App\Core\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Pemasok barang. */
class Supplier extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = ['name', 'phone', 'address', 'notes', 'default_pack_weight'];

    protected function casts(): array
    {
        return ['default_pack_weight' => 'decimal:3'];
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }
}
