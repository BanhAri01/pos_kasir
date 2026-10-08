<?php

namespace App\Http\Middleware;

use App\Core\Tenancy\CurrentOutlet;
use App\Core\Tenancy\TenantContext;
use App\Modules\Auth\Services\RegisterTenantService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Data yang tersedia di semua halaman Vue.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $tenant = app(TenantContext::class)->get();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'role' => $user->role()?->value,
                    'role_label' => $user->roleLabel(),
                    'permissions' => $user->getAllPermissions()->pluck('name'),
                    'preferences' => [...RegisterTenantService::defaultPreferences(), ...($user->preferences ?? [])],
                ] : null,
            ],
            'tenant' => $tenant ? [
                'name' => $tenant->name,
                'business_type' => $tenant->businessType?->code,
                'pos_layout' => $tenant->businessType?->pos_layout,
                'timezone_label' => $tenant->timezoneLabel(),
                'trial_days_left' => $tenant->trialDaysLeft(),
                'subscription' => [
                    'plan_label' => $tenant->planLabel(),
                    'on_trial' => $tenant->status === 'trial',
                    'days_left' => $tenant->daysLeft(),
                    'in_grace' => $tenant->isInGrace(),
                    'grace_days_left' => $tenant->graceDaysLeft(),
                ],
                'modules' => $tenant->enabledModuleCodes(),
            ] : null,
            // Outlet aktif + daftar outlet yang boleh dipilih (untuk tombol ganti outlet).
            'outlet' => $tenant && $user ? function () use ($user) {
                $current = app(CurrentOutlet::class);

                return [
                    'current' => $current->get($user)?->only(['id', 'name', 'type']),
                    'list' => $current->accessible($user)->map->only(['id', 'name', 'type'])->values(),
                ];
            } : null,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'info' => fn () => $request->session()->get('info'),
                'undo' => fn () => $request->session()->get('undo'),
            ],
            'adminWhatsapp' => config('hermes.admin_whatsapp'),
            'isLocal' => app()->environment('local'),
        ];
    }
}
