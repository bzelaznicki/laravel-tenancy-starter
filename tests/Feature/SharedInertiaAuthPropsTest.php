<?php

use App\Models\Tenant;
use App\Models\User;
use App\TenantRole;
use Inertia\Testing\AssertableInertia as Assert;

it('shares only the required user and tenant fields on tenant pages', function (): void {
    $user = User::factory()->create();

    $tenant = Tenant::factory()
        ->withDomain()
        ->withMember($user, TenantRole::Admin)
        ->create();

    $this->actingAs($user)
        ->get(tenantRoute($tenant, 'dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('auth.user', [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'emailVerified' => true,
                ])
                ->where('auth.tenant', [
                    'name' => $tenant->name,
                ])
        );
});

it('shares the tenant name on tenant settings pages', function (string $route): void {
    $user = User::factory()->create();

    $tenant = Tenant::factory()
        ->withDomain()
        ->withMember($user, TenantRole::Admin)
        ->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(tenantRoute($tenant, $route))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page->where('auth.tenant.name', $tenant->name)
        );
})->with([
    'profile.edit',
    'security.edit',
    'appearance.edit',
    'memberships.index',
]);

it('shares no user or tenant on central guest pages', function (): void {
    $this->get('https://'.config('app.domain').'/')
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('auth.user', null)
                ->where('auth.tenant', null)
                ->where('auth.role', null)
                ->where('auth.membershipCapabilities.assignableRoles', [])
        );
});

it('shares the tenant name but no user on tenant guest pages', function (): void {
    $tenant = Tenant::factory()->withDomain()->create();

    $this->get(tenantRoute($tenant, 'login'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('auth.user', null)
                ->where('auth.tenant', [
                    'name' => $tenant->name,
                ])
                ->where('auth.role', null)
                ->where('auth.membershipCapabilities.assignableRoles', [])
        );
});

it('shares a minimal user without tenant context on authenticated central pages', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('https://'.config('app.domain').'/')
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('auth.user', [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'emailVerified' => true,
                ])
                ->where('auth.tenant', null)
        );
});
