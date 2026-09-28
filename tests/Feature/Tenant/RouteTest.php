<?php

use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('redirects to a tenant subdomain if the tenant exists', function (): void {
    $tenant = Tenant::factory()->withDomain()->create();

    $response = $this->post(route('tenant.redirect'), ['subdomain' => $tenant->slug]);
    $response->assertRedirect(tenantRoute($tenant, 'login'));
});

it('redirects to a tenant subdomain handling case sensitivity', function (): void {
    $tenant = Tenant::factory()->withDomain()->create();

    $response = $this->post(route('tenant.redirect'), ['subdomain' => strtoupper($tenant->slug)]);
    $response->assertRedirect(tenantRoute($tenant, 'login'));
});

it('redirects to a tenant subdomain handling extra spaces', function (): void {
    $tenant = Tenant::factory()->withDomain()->create();

    $response = $this->post(route('tenant.redirect'), ['subdomain' => '            '.$tenant->slug.'                             ']);
    $response->assertRedirect(tenantRoute($tenant, 'login'));
});

it('returns a not found message if the tenant does not exist', function (): void {
    $response = $this
        ->from(route('tenant.find'))
        ->post(route('tenant.redirect'), [
            'subdomain' => 'acme',
        ]);

    $response
        ->assertRedirect(route('tenant.find'))
        ->assertSessionHasErrors([
            'subdomain' => 'We were unable to find a tenant on this subdomain.',
        ]);
});

it('returns a not found message for a reserved tenant without querying the database', function (): void {
    $this->expectsDatabaseQueryCount(0);
    $response = $this
        ->from(route('tenant.find'))
        ->post(route('tenant.redirect'), [
            'subdomain' => 'api',
        ]);
    $response
        ->assertRedirect(route('tenant.find'))
        ->assertSessionHasErrors([
            'subdomain' => 'We were unable to find a tenant on this subdomain.',
        ]);
});

it('does not expose registration on tenant hosts', function (): void {
    $tenant = Tenant::factory()->withDomain()->create();

    $this->get(tenantRoute($tenant, 'register'))
        ->assertNotFound();
});

it('does not expose tenant authentication routes on the apex', function (): void {
    $this->get(route('password.request'))
        ->assertNotFound();
});

it('returns not found for the homepage when the subdomain is unknown', function (): void {
    $this->get('https://nope.'.config('app.domain').'/')
        ->assertNotFound();
});

it('redirects guests to the tenant login page on a tenant subdomain', function (): void {
    $tenant = Tenant::factory()->withDomain()->create();

    $this->get(tenantUrl($tenant))
        ->assertRedirect(tenantRoute($tenant, 'login'));
});

it('redirects logged in members to the tenant dashboard page on the tenant homepage', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($user)->create();

    $this->actingAs($user)
        ->get(tenantUrl($tenant))
        ->assertRedirect(tenantRoute($tenant, 'dashboard'));
});

it('renders the homepage on the apex domain', function (): void {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('welcome'));
});
