<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class RedirectToTenantController extends Controller
{
    public function __invoke(Request $request): Response
    {

        $validated = $request->validate(
            [
                'subdomain' => ['required', 'string'],
            ],
            [
                'subdomain.required' => 'Enter your tenant subdomain.',
            ],
        );

        $subdomain = $validated['subdomain'] |> trim(...) |> strtolower(...);

        if (in_array($subdomain, config('tenancy.reserved_subdomains'))) {
            throw ValidationException::withMessages(['subdomain' => 'We were unable to find a tenant on this subdomain.']);
        }

        $tenant = Tenant::query()->whereRelation(
            'domains',
            'domain',
            $subdomain
        )->first();

        if ($tenant === null) {
            throw ValidationException::withMessages(['subdomain' => 'We were unable to find a tenant on this subdomain.']);
        }

        return Inertia::location(
            "https://{$subdomain}.".config('app.domain').'/login'
        );
    }
}
