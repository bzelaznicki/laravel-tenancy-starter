<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConfigurePasskeyOrigin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->getSchemeAndHttpHost();

        config([
            'fortify.passkeys.allowed_origins' => [$origin],
            'passkeys.allowed_origins' => [$origin],
        ]);

        return $next($request);
    }
}
