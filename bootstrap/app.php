<?php

use App\Core\Modules\EnsureModuleEnabled;
use App\Core\Tenancy\EnsureActiveTenant;
use App\Core\Tenancy\SetTenantContext;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetTenantContext::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Tenant harus sudah diketahui SEBELUM route model binding,
        // supaya {outlet} milik usaha lain langsung 404.
        $middleware->prependToPriorityList(SubstituteBindings::class, SetTenantContext::class);

        $middleware->validateCsrfTokens(except: ['webhook/*']);

        $middleware->alias([
            'tenant' => EnsureActiveTenant::class,
            'module' => EnsureModuleEnabled::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Halaman error yang ramah (bahasa sehari-hari + tombol kembali), bukan halaman teknis.
        // 500/503 hanya di production supaya saat development detail error tetap terlihat.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            $status = $response->getStatusCode();
            $friendly = in_array($status, [403, 404, 419], true)
                || (! app()->hasDebugModeEnabled() && in_array($status, [500, 503], true));

            if (! $friendly || $request->expectsJson()) {
                return $response;
            }

            if ($status === 419) {
                return back()->with('error', 'Halaman sudah terlalu lama dibuka. Silakan coba lagi.');
            }

            return Inertia::render('Error', [
                'status' => $status,
                'adminWhatsapp' => config('hermes.admin_whatsapp'),
            ])
                ->toResponse($request)
                ->setStatusCode($status);
        });
    })->create();
