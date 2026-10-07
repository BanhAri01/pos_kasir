<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Module extends Model
{
    protected $fillable = [
        'code', 'name', 'description', 'group', 'is_core', 'depends_on', 'icon', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_core' => 'boolean',
            'is_active' => 'boolean',
            'depends_on' => 'array',
        ];
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_modules')
            ->withPivot(['is_enabled', 'settings', 'enabled_at'])
            ->withTimestamps();
    }

    public function businessTypes(): BelongsToMany
    {
        return $this->belongsToMany(BusinessType::class, 'business_type_modules');
    }
}
