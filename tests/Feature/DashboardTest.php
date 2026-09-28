<?php

use App\Models\Tenant;
use App\Models\User;
use App\TenantRole;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function (): void {
    $tenant = Tenant::factory()->withDomain()->create();

    $this->get(tenantRoute($tenant, 'dashboard'))
        ->assertRedirect('/login');
});

test('authenticated members can visit the dashboard', function (): void {
    $user = User::factory()->create();

    $tenant = Tenant::factory()->withDomain()->withMember($user, TenantRole::Admin)->create();

    $this->actingAs($user)
        ->get(tenantRoute($tenant, 'dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p->where('auth.role', 'admin'));
});
