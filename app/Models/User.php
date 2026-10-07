<?php

namespace App\Models;

use App\Core\Tenancy\TenantContext;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

/**
 * Pengguna aplikasi: pemilik, manajer, kasir, karyawan, atau Super Admin (tenant_id null).
 *
 * Catatan: User sengaja TIDAK memakai BelongsToTenant karena login (no HP / PIN) perlu
 * mencari user sebelum tenant diketahui. Untuk data karyawan di dalam aplikasi,
 * selalu pakai User::sameTenant().
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, LogsActivity, Notifiable, SoftDeletes;

    /** Role dipakai bersama oleh login web dan API (Sanctum). */
    protected string $guard_name = 'web';

    protected $fillable = [
        'tenant_id', 'name', 'phone', 'email', 'password', 'job_title', 'photo_path',
        'is_active', 'preferences', 'last_login_at',
    ];

    protected $attributes = [
        'is_active' => true,
        'is_super_admin' => false,
        'pin_hash' => null,
        'remember_token' => null,
    ];

    protected $hidden = [
        'password',
        'pin_hash',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'is_active' => 'boolean',
            'preferences' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function outlets(): BelongsToMany
    {
        return $this->belongsToMany(Outlet::class);
    }

    /** Hanya user milik tenant yang sedang aktif. */
    public function scopeSameTenant(Builder $query): Builder
    {
        $tenantId = app(TenantContext::class)->id();

        return $tenantId === null
            ? $query->whereRaw('1 = 0')
            : $query->where('tenant_id', $tenantId);
    }

    // ------------------------------------------------------------------
    // Role
    // ------------------------------------------------------------------

    public function role(): ?Role
    {
        $name = $this->getRoleNames()->first();

        return $name ? Role::tryFrom($name) : null;
    }

    public function roleLabel(): string
    {
        return $this->role()?->label() ?? '-';
    }

    public function isOwner(): bool
    {
        return $this->role() === Role::Owner;
    }

    // ------------------------------------------------------------------
    // PIN masuk cepat
    // ------------------------------------------------------------------

    public function setPin(?string $pin): void
    {
        $this->pin_hash = $pin ? Hash::make($pin) : null;
    }

    public function hasPin(): bool
    {
        return $this->pin_hash !== null;
    }

    public function checkPin(string $pin): bool
    {
        return $this->pin_hash !== null && Hash::check($pin, $this->pin_hash);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'phone', 'job_title', 'is_active'])
            ->logOnlyDirty()
            ->useLogName('karyawan');
    }
}
