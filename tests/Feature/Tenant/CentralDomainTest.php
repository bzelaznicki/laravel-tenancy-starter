<?php

use App\Models\Invitation;
use App\Models\Membership;
use App\Models\Tenant;
use App\Models\User;
use App\TenantRole;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;

/**
 * @return list<Route>
 */
function routesIdentifyingTenant(): array
{
    return collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn (Route $route): bool => in_array(
            InitializeTenancyBySubdomain::class,
            $route->gatherMiddleware(),
            true,
        ))
        ->values()
        ->all();
}

it('never serves a tenant route on the apex domain', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($user, TenantRole::Owner)->create();

    // A real membership, pending invitation and token, so these routes can't
    // 404 on a bad ID or token and hide a route that is reachable on the apex.
    $membership = Membership::query()
        ->where('tenant_id', $tenant->getKey())
        ->where('user_id', $user->getKey())
        ->sole();
    $token = Str::random(64);
    $invitation = Invitation::factory()
        ->forTenant($tenant, $user)
        ->create(['token_hash' => hash('sha256', $token)]);

    $this->actingAs($user);

    $checkedRouteNames = [];
    $servedOnApex = [];

    foreach (routesIdentifyingTenant() as $route) {
        $method = $route->methods()[0];
        $path = str_replace(
            ['{invitation}', '{membership}'],
            [$invitation->id, (string) $membership->getKey()],
            $route->uri(),
        );
        $path = preg_replace('/\{[^}]+\}/', 'placeholder', $path);
        $url = 'https://'.config('app.domain').'/'.ltrim($path, '/');
        $parameters = [];

        if (in_array('invitation', $route->parameterNames(), true)) {
            if ($method === 'GET') {
                $url .= '?'.http_build_query(['token' => $token]);
            } else {
                $parameters = ['token' => $token];
            }
        }

        // A central route registered for the same URL takes the request
        // instead (e.g. the apex homepage); it isn't a tenant page leaking.
        if (app('router')->getRoutes()->match(Request::create($url, $method)) !== $route) {
            continue;
        }

        $checkedRouteNames[] = $route->getName();

        $status = $this->call($method, $url, $parameters)->getStatusCode();

        if ($status !== 404) {
            $servedOnApex[] = "{$method} /{$route->uri()} returned {$status}";
        }
    }

    expect($checkedRouteNames)->toContain('dashboard', 'memberships.index', 'password.request')
        ->and($servedOnApex)->toBeEmpty();
});

it('identifies the tenant on every route that requires membership', function (): void {
    $membershipRoutesWithoutTenant = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn (Route $route): bool => in_array('tenant.member', $route->gatherMiddleware(), true))
        ->reject(fn (Route $route): bool => in_array(
            InitializeTenancyBySubdomain::class,
            $route->gatherMiddleware(),
            true,
        ))
        ->map(fn (Route $route): string => $route->uri())
        ->values();

    expect($membershipRoutesWithoutTenant)->toBeEmpty();
});
