<?php

namespace App\Models;

use App\Modules\Billing\Services\Plans;
use App\Modules\WhatsApp\Models\WhatsappMessage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
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
        'onboarded_at', 'settings', 'plan', 'paid_until', 'midtrans_server_key',
        'midtrans_client_key', 'midtrans_production', 'qr_payment',
    ];

    protected $hidden = ['midtrans_server_key', 'midtrans_client_key'];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'onboarded_at' => 'datetime',
            'settings' => 'array',
            'paid_until' => 'date',
            'midtrans_server_key' => 'encrypted',
            'midtrans_client_key' => 'encrypted',
            'midtrans_production' => 'boolean',
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
                ->filter(fn (string $code) => $this->planAllowsModule($code))
                ->values()
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
        return "tenant:{$this->id}:modules:".$this->planKey();
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

    public function planKey(): string
    {
        return Plans::normalize($this->plan);
    }

    public function planLabel(): string
    {
        return Plans::label($this->plan);
    }

    public function planAllowsModule(string $code): bool
    {
        $required = Plans::requiredForModule($code);

        return $required === null || Plans::covers($this->plan, $required);
    }

    public function allows(string $feature): bool
    {
        $required = Plans::requiredForFeature($feature);

        return $required === null || Plans::covers($this->plan, $required);
    }

    public function limit(string $key): ?int
    {
        $value = Plans::get($this->plan)[$key] ?? null;

        return $value === null ? null : (int) $value;
    }

    public function hasUnlimitedAccess(): bool
    {
        return $this->status === 'active' && $this->paid_until === null;
    }

    public function accessEndsAt(): ?Carbon
    {
        return match (true) {
            $this->hasUnlimitedAccess() => null,
            $this->status === 'active' => $this->paid_until->copy()->endOfDay(),
            default => $this->trial_ends_at?->copy(),
        };
    }

    public function hasAccess(): bool
    {
        if ($this->isSuspended()) {
            return false;
        }
        if ($this->hasUnlimitedAccess()) {
            return true;
        }

        $ends = $this->accessEndsAt();

        return $ends !== null && $ends->copy()->addDays((int) config('hermes.grace_days'))->isFuture();
    }

    public function isInGrace(): bool
    {
        $ends = $this->accessEndsAt();

        return $ends !== null && $ends->isPast() && $this->hasAccess();
    }

    public function daysLeft(): ?int
    {
        $ends = $this->accessEndsAt();
        if ($ends === null) {
            return null;
        }

        return $ends->isPast() ? 0 : (int) ceil(now()->diffInHours($ends) / 24);
    }

    public function graceDaysLeft(): int
    {
        $ends = $this->accessEndsAt();
        if ($ends === null || ! $this->isInGrace()) {
            return 0;
        }

        return max(1, (int) ceil(now()->diffInHours($ends->copy()->addDays((int) config('hermes.grace_days'))) / 24));
    }

    public function convertedDays(string $newPlan): int
    {
        if ($this->status !== 'active' || ! $this->paid_until || $this->paid_until->isPast()) {
            return 0;
        }

        $remaining = (int) Carbon::today()->diffInDays($this->paid_until);
        $oldPrice = Plans::get($this->plan)['price'];
        $newPrice = max(1, Plans::get($newPlan)['price']);

        return (int) floor($remaining * $oldPrice / $newPrice);
    }

    public function extendSubscription(int $months, ?string $plan = null): array
    {
        $plan = Plans::normalize($plan ?? $this->plan);
        $today = Carbon::today();
        $activeLeft = $this->status === 'active' && $this->paid_until && $this->paid_until->gte($today);

        $from = match (true) {
            $activeLeft && $plan !== $this->planKey() => $today->copy()->addDays($this->convertedDays($plan)),
            $activeLeft => $this->paid_until->copy()->addDay(),
            $this->status === 'trial' && $this->trial_ends_at?->isFuture() => $this->trial_ends_at->copy()->startOfDay(),
            default => $today->copy(),
        };

        $until = $from->copy()->addMonthsNoOverflow($months)->subDay();

        $this->forceFill(['status' => 'active', 'plan' => $plan, 'paid_until' => $until->toDateString()])->save();
        $this->flushModuleCache();

        return [$from, $until];
    }

    public function whatsappUsedThisMonth(): int
    {
        return WhatsappMessage::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $this->id)
            ->where('status', '!=', 'failed')
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
    }
}