<?php

namespace App\Core\Tenancy;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dipasang global di grup "web" (dan nanti "api"): mengisi TenantContext
 * dari user yang sedang login.
 */
class SetTenantContext
{
    public function __construct(private TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $this->context->set($user?->tenant_id ? $user->tenant : null);

        return $next($request);
    }
}
