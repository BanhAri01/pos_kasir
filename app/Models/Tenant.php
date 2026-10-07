<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

/**
 * Satu tenant = satu usaha yang berlangganan (bisa punya banyak outlet).
 */
class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    public const TIMEZONES = [
        'Asia/Jakarta' => 'WIB',
        'Asia/Makassar' => 'WITA',
        'Asia/Jayapura' => 'WIT',
    ];

    protected $fillable = [
        'uuid', 'name', 'slug', 'business_type_id', 'owner_id', 'phone', 'address',
        'city', 'province', 'timezone', 'logo_path', 'status', 'trial_ends_at',
        'onboarded_at', 'settings',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'onboarded_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessType::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function outlets(): HasMany
    {
        return $this->hasMany(Outlet::class);
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'tenant_modules')
            ->withPivot(['is_enabled', 'settings', 'enabled_at'])
            ->withTimestamps();
    }

    // ------------------------------------------------------------------
    // Modul
    // ------------------------------------------------------------------

    /**
     * Kode semua modul yang aktif (core + yang dinyalakan), di-cache per tenant.
     *
     * @return list<string>
     */
    public function enabledModuleCodes(): array
    {
        return Cache::rememberForever($this->moduleCacheKey(), function () {
            return Module::query()
                ->where('is_active', true)
                ->where(function ($query) {
                    $query->where('is_core', true)
                        ->orWhereHas('tenants', fn ($q) => $q
                            ->where('tenants.id', $this->id)
                            ->where('tenant_modules.is_enabled', true));
                })
                ->orderBy('sort_order')
                ->pluck('code')
                ->all();
        });
    }

    public function hasModule(string $code): bool
    {
        return in_array($code, $this->enabledModuleCodes(), true);
    }

    public function flushModuleCache(): void
    {
        Cache::forget($this->moduleCacheKey());
    }

    private function moduleCacheKey(): string
    {
        return "tenant:{$this->id}:modules";
    }

    // ------------------------------------------------------------------
    // Info tampilan
    // ------------------------------------------------------------------

    public function timezoneLabel(): string
    {
        return self::TIMEZONES[$this->timezone] ?? 'WIB';
    }

    public function isOnTrial(): bool
    {
        return $this->status === 'trial' && $this->trial_ends_at?->isFuture();
    }

    public function trialDaysLeft(): int
    {
        if (! $this->isOnTrial()) {
            return 0;
        }

        return (int) ceil(now()->diffInHours($this->trial_ends_at) / 24);
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }
}
