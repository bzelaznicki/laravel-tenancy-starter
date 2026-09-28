<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantMembership
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenant();
        $user = $request->user();

        abort_if($tenant === null, 404);
        abort_if($user === null, 403);

        $role = $user->roleFor($tenant);
        abort_if($role === null, 403);

        $request->attributes->set('tenant_role', $role->value);

        return $next($request);
    }
}
