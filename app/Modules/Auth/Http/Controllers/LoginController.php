<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Modules\Auth\Http\Requests\LoginRequest;
use App\Modules\Auth\Services\DeviceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function create(Request $request, DeviceService $devices): Response
    {
        return Inertia::render('Auth/Login', [
            // Tampilkan tombol "Masuk dengan PIN" kalau HP ini sudah dikenal.
            'canUsePin' => $devices->fromRequest($request) !== null,
        ]);
    }

    public function store(LoginRequest $request, DeviceService $devices): RedirectResponse
    {
        $user = $request->authenticateUser();

        Auth::login($user, remember: true);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        // Hanya pemilik & manajer yang bisa "mendaftarkan" HP toko untuk masuk cepat dengan PIN.
        if ($user->tenant_id && in_array($user->role(), [Role::Owner, Role::Manager], true)) {
            $devices->remember($request, $user);
        }

        if ($user->is_super_admin) {
            return redirect()->route('admin.tenants.index');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /** "Ganti Pengguna": keluar lalu langsung ke layar pilih nama + PIN. */
    public function switchUser(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('pin.create');
    }
}
