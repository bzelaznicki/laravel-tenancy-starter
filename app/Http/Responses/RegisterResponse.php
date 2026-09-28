<?php

namespace App\Http\Responses;

use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Symfony\Component\HttpFoundation\Response;

class RegisterResponse implements RegisterResponseContract
{
    public function __construct(private StatefulGuard $guard) {}

    /**
     * @param  Request  $request
     */
    public function toResponse(mixed $request): Response
    {
        $tenant = $request->user()->tenants()->sole();

        $tenantLoginUrl = (string) $request->uri()
            ->withHost($tenant->host())
            ->withPath('/login');

        $this->guard->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->header('X-Inertia')) {
            return Inertia::location($tenantLoginUrl);
        }

        return redirect()->away($tenantLoginUrl);
    }
}
