<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Core\Tenancy\TenantContext;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Auth\Services\DeviceService;
use App\Modules\Report\Services\OwnerReportService;
use App\Modules\WhatsApp\Services\WhatsAppService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Masuk cepat untuk kasir & karyawan: pilih nama, ketik PIN.
 * Hanya bisa di HP yang sebelumnya sudah dipakai login pemilik/manajer.
 */
class PinLoginController extends Controller
{
    public function create(Request $request, DeviceService $devices): Response|RedirectResponse
    {
        $device = $devices->fromRequest($request);

        if (! $device) {
            return redirect()->route('login')->with(
                'info',
                'HP ini belum dikenal. Pemilik usaha perlu masuk dulu sekali dengan no HP dan kata sandi.'
            );
        }

        $staff = User::query()
            ->where('tenant_id', $device->tenant_id)
            ->where('is_active', true)
            ->whereNotNull('pin_hash')
            ->with('roles')
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'role_label' => $u->job_title ?: $u->roleLabel(),
                'photo_url' => $u->photo_path ? asset('storage/'.$u->photo_path) : null,
            ]);

        return Inertia::render('Auth/PinLogin', [
            'businessName' => $device->tenant()->value('name'),
            'staff' => $staff,
        ]);
    }

    public function store(Request $request, DeviceService $devices): SymfonyResponse
    {
        $request->validate([
            'user_id' => ['required', 'integer'],
            'pin' => ['required', 'string'],
        ], [
            'user_id.required' => 'Pilih dulu nama Anda.',
            'pin.required' => 'PIN belum diisi.',
        ]);

        $device = $devices->fromRequest($request);

        if (! $device) {
            return redirect()->route('login');
        }

        $key = 'pin:'.$device->id.'|'.$request->integer('user_id');
        $dailyKey = User::pinDailyKey($request->integer('user_id'));

        if (RateLimiter::tooManyAttempts($dailyKey, config('hermes.pin_daily_attempts'))) {
            throw ValidationException::withMessages([
                'pin' => 'PIN akun ini dikunci karena terlalu sering salah. Minta pemilik usaha mengganti PIN Anda di menu Karyawan.',
            ]);
        }

        if (RateLimiter::tooManyAttempts($key, config('hermes.max_login_attempts'))) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'pin' => "PIN salah terlalu sering. Tunggu {$seconds} detik lalu coba lagi.",
            ]);
        }

        // Hanya karyawan dari usaha pemilik HP ini.
        $user = User::query()
            ->where('tenant_id', $device->tenant_id)
            ->where('is_active', true)
            ->find($request->integer('user_id'));

        if (! $user || ! $user->checkPin((string) $request->input('pin'))) {
            RateLimiter::hit($key, config('hermes.login_lockout_seconds'));

            if ($user && RateLimiter::hit($dailyKey, 86400) === (int) config('hermes.pin_daily_attempts')) {
                $this->warnOwner($user);
            }

            throw ValidationException::withMessages([
                'pin' => 'PIN salah. Coba lagi, atau tanyakan PIN ke pemilik usaha.',
            ]);
        }

        RateLimiter::clear($key);
        RateLimiter::clear($dailyKey);

        Auth::login($user);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        $device->forceFill(['last_seen_at' => now()])->save();

        // Kasir langsung ke layar kasir (bukan halaman Inertia, jadi pakai Inertia::location).
        if ($user->can(Permission::UsePos->value) && ! $user->can(Permission::ManageProducts->value)) {
            return Inertia::location(route('pos.show'));
        }

        return redirect()->route('dashboard')->with('success', "Halo, {$user->name}! Selamat bekerja.");
    }

    private function warnOwner(User $user): void
    {
        $tenant = Tenant::query()->find($user->tenant_id);
        if (! $tenant) {
            return;
        }

        app(TenantContext::class)->runAs($tenant, function (Tenant $tenant) use ($user) {
            $to = app(OwnerReportService::class)->recipient($tenant);
            if ($to) {
                app(WhatsAppService::class)->send($to, "*Peringatan keamanan {$tenant->name}*\n\nPIN akun *{$user->name}* salah ".config('hermes.pin_daily_attempts')." kali hari ini, jadi akun itu dikunci 24 jam.\n\nKalau itu bukan karyawan Anda yang lupa PIN, ganti PIN-nya di menu Karyawan. Mengganti PIN juga membuka kuncinya.");
            }
        });
    }
}
