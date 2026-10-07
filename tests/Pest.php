<?php

use App\Core\Tenancy\TenantContext;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Auth\Services\RegisterTenantService;
use App\Modules\Staff\Services\StaffService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

/**
 * Buat usaha baru lewat alur pendaftaran asli. Mengembalikan akun pemilik.
 */
function registerTenant(string $type = 'warung_makan', ?string $phone = null, string $name = 'Warung Bu Sri'): User
{
    static $counter = 0;
    $counter++;

    return app(RegisterTenantService::class)->register([
        'business_type' => $type,
        'business_name' => $name,
        'owner_name' => 'Pemilik '.$counter,
        'phone' => $phone ?? '62812000000'.str_pad((string) $counter, 2, '0', STR_PAD_LEFT),
        'password' => 'rahasia123',
    ]);
}

/** Jalankan callback sebagai tenant tertentu (seperti saat user tenant itu login). */
function asTenant(Tenant $tenant, callable $callback): mixed
{
    return app(TenantContext::class)->runAs($tenant, $callback);
}

/** Outlet tempat user bertugas (dibaca sebagai tenant user itu). */
function outletsOf(User $user): Collection
{
    return asTenant($user->tenant, fn () => $user->outlets()->get());
}

/** Tambah karyawan ke usaha milik $owner. */
function addStaff(User $owner, string $role = 'kasir', string $pin = '1234', array $extra = []): User
{
    return asTenant($owner->tenant, fn () => app(StaffService::class)->create([
        'name' => ucfirst($role).' '.fake()->firstName(),
        'role' => $role,
        'pin' => $pin,
        'outlet_ids' => outletsOf($owner)->pluck('id')->all(),
        ...$extra,
    ]));
}
