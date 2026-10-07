<?php

namespace App\Modules\Inventory\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Models\Outlet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Blok / rak penyimpanan di dalam gudang (modul multi_warehouse). */
class WarehouseLocation extends Model
{
    use BelongsToTenant;

    protected $fillable = ['outlet_id', 'name', 'note', 'sort_order'];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }
}
