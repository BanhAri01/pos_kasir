<?php

namespace App\Models;

use App\Core\Tenancy\TenantContext;
use Spatie\Activitylog\Models\Activity as BaseActivity;

/**
 * Catatan aktivitas penting (ubah karyawan, nyalakan fitur, void, refund, dll.)
 * dengan tenant_id supaya tiap usaha hanya melihat catatannya sendiri.
 */
class Activity extends BaseActivity
{
    protected static function booted(): void
    {
        static::creating(function (Activity $activity) {
            $activity->tenant_id ??= app(TenantContext::class)->id();
        });
    }
}
