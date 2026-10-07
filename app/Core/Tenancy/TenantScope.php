<?php

namespace App\Core\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Membatasi semua query ke tenant yang sedang aktif.
 *
 * Sengaja "fail closed": kalau tidak ada tenant aktif, query tidak mengembalikan
 * data apa pun. Lebih baik layar kosong daripada data usaha lain bocor.
 * Kode yang memang perlu lintas tenant (Super Admin, command) harus memanggil
 * withoutGlobalScope(TenantScope::class) secara eksplisit.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = app(TenantContext::class)->id();

        if ($tenantId === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->qualifyColumn('tenant_id'), $tenantId);
    }
}
