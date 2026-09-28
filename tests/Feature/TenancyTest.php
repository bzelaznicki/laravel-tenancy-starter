<?php

use App\Models\Tenant;
use App\Models\User;
use App\TenantRole;
use Illuminate\Contracts\Http\Kernel as KernelContract;
use Illuminate\Foundation\Http\Kernel;
use Inertia\Testing\AssertableInertia as Assert;
use Stancl\Tenancy\Middleware;

it('registers all tenancy middleware on the application HTTP kernel', function (): void {
    $kernel = app(KernelContract::class);

    expect($kernel)->toBeInstanceOf(Kernel::class);

    assert($kernel instanceof Kernel);

    expect(array_slice($kernel->getMiddlewarePriority(), 0, 6))->toBe([
        Middleware\PreventAccessFromCentralDomains::class,
        Middleware\InitializeTenancyByDomain::class,
        Middleware\InitializeTenancyBySubdomain::class,
        Middleware\InitializeTenancyByDomainOrSubdomain::class,
        Middleware\InitializeTenancyByPath::class,
        Middleware\InitializeTenancyByRequestData::class,
    ]);
});

it('returns a valid page for a tenant member', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = Tenant::factory()->withDomain()->withMember($user)->create();

    $response = $this->get(tenantRoute($tenant, 'dashboard'));

    $response
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page): Assert => $page->component('dashboard'));
});

it('redirects visitor to the login page when unauthenticated on a tenant page', function (): void {

    $tenant = Tenant::factory()->withDomain()->create();

    $response = $this->get(tenantRoute($tenant, 'dashboard'));

    $response
        ->assertRedirect('/login');
});

it('redirects a guest to the login page of the tenant they requested', function (): void {
    Tenant::factory()->withDomain('tenant-a')->create();
    $tenantB = Tenant::factory()->withDomain('tenant-b')->create();

    $this->get(tenantRoute($tenantB, 'dashboard'))
        ->assertRedirect(tenantRoute($tenantB, 'login'));
});

it('returns a 404 page if accessed from the root domain', function (): void {
    $response = $this->get(
        'https://'.config('app.domain').'/dashboard',
    );

    $response->assertNotFound();
});

it('returns a 404 page if the tenant does not exist', function (): void {
    $response = $this->get(
        'https://nothere.'.config('app.domain').'/dashboard',
    );

    $response->assertNotFound();
});

it('returns 403 if the authenticated user is not a member of the tenant', function (): void {
    $this->actingAs($user = User::factory()->create());

    Tenant::factory()->withMember($user)->create();

    $tenant = Tenant::factory()->withDomain()->create();

    $response = $this->get(tenantRoute($tenant, 'dashboard'));

    $response->assertStatus(403);
});

it('rejects unverified user from tenant routes', function (): void {

    $this->actingAs($user = User::factory()->unverified()->create());

    $tenant = Tenant::factory()->withDomain()->withMember($user)->create();

    $response = $this->get(tenantRoute($tenant, 'dashboard'));

    $response->assertRedirectToRoute('verification.notice');
});

it('resolves the tenant role correctly', function (): void {
    $user = User::factory()->create();

    $tenant = Tenant::factory()->withDomain()->withMember($user, TenantRole::Admin)->create();

    $this->actingAs($user);
    $response = $this->get(tenantRoute($tenant, 'dashboard'));

    $response->assertInertia(
        fn (Assert $page) => $page->where('auth.role', TenantRole::Admin->value)
    );
});
