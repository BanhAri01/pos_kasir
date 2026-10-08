<?php

namespace App\Core\Modules;

use App\Core\Tenancy\TenantContext;
use App\Models\Module;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware route: ->middleware('module:booking')
 * Memblokir halaman dari modul yang belum dinyalakan.
 */
class EnsureModuleEnabled
{
    public function __construct(private TenantContext $context) {}

    public function handle(Request $request, Closure $next, string ...$codes): Response
    {
        $tenant = $this->context->get();

        if ($tenant && array_filter($codes, fn (string $code) => $tenant->hasModule($code))) {
            return $next($request);
        }

        $code = $codes[0];

        $name = Module::query()->where('code', $code)->value('name') ?? 'ini';
        $message = "Fitur {$name} belum dinyalakan. Nyalakan dulu di menu Lainnya, lalu Atur Fitur.";

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        return redirect()->route('dashboard')->with('error', $message);
    }
}
