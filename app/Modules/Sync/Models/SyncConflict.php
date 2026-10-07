<?php

namespace App\Modules\Sync\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Models\Outlet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Hal yang perlu diperiksa pemilik setelah kasir offline tersinkron. */
class SyncConflict extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'device_id', 'outlet_id', 'type', 'entity_type', 'entity_uuid', 'message', 'payload', 'resolved_at', 'resolved_by',
    ];

    protected function casts(): array
    {
        return ['payload' => 'array', 'resolved_at' => 'datetime'];
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }
}
