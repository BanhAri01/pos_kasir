<?php

namespace App\Core\Tenancy;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pasang di setiap model data bisnis.
 *
 * - Query otomatis dibatasi ke tenant aktif (TenantScope).
 * - tenant_id otomatis diisi saat membuat data baru.
 * - Membuat data tanpa tenant aktif akan gagal, supaya tidak ada data "yatim".
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            if ($model->tenant_id) {
                return;
            }

            $tenantId = app(TenantContext::class)->id();

            if ($tenantId === null) {
                throw new MissingTenantException(
                    'Tidak bisa menyimpan '.class_basename($model).' tanpa tenant aktif.'
                );
            }

            $model->tenant_id = $tenantId;
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** Query lintas tenant (khusus Super Admin / command). */
    public static function allTenants(): Builder
    {
        return static::withoutGlobalScope(TenantScope::class);
    }
}
