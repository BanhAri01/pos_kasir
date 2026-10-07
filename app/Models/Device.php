<?php

namespace App\Models;

use App\Core\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * HP/tablet toko yang sudah pernah dipakai login pemilik/manajer.
 */
class Device extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'outlet_id', 'uuid', 'code', 'name', 'user_agent', 'last_seen_at', 'last_synced_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }
}
