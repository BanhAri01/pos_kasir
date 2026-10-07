<?php

namespace App\Modules\Catalog\Models;

use App\Core\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu pilihan, mis. "Besar (+Rp5.000)". */
class ModifierOption extends Model
{
    use BelongsToTenant;

    protected $fillable = ['modifier_group_id', 'name', 'price_delta', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'price_delta' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ModifierGroup::class, 'modifier_group_id');
    }
}
