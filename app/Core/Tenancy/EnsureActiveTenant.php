<?php

namespace App\Core\Tenancy;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware route 'tenant': halaman hanya untuk pengguna yang punya usaha aktif
 * dan akunnya masih aktif.
 */
class EnsureActiveTenant
{
    public function __construct(private TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $tenant = $this->context->get();

        $message = match (true) {
            $tenant === null => 'Akun ini belum terhubung ke usaha mana pun. Silakan hubungi admin.',
            ! $user->is_active => 'Akun Anda sedang dinonaktifkan. Minta pemilik usaha untuk mengaktifkan lagi.',
            $tenant->isSuspended() => 'Usaha ini sedang dibekukan. Silakan hubungi admin lewat WhatsApp.',
            default => null,
        };

        if ($message === null) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        return redirect()->route('login')->with('error', $message);
    }
}
