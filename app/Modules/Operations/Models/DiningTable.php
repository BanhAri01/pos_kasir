<?php

namespace App\Modules\Operations\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Models\Outlet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Meja di rumah makan / kedai. */
class DiningTable extends Model
{
    use BelongsToTenant;

    protected $fillable = ['outlet_id', 'name', 'area', 'capacity', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'capacity' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }
}
