<?php

use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('confirm password screen can be rendered', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($user)->create();

    $response = $this->actingAs($user)->get(tenantRoute($tenant, 'password.confirm'));

    $response->assertOk();

    $response->assertInertia(
        fn (Assert $page) => $page
            ->component('auth/confirm-password'),
    );
});

test('password confirmation requires authentication', function (): void {
    $tenant = Tenant::factory()->withDomain()->create();

    $response = $this->get(tenantRoute($tenant, 'password.confirm'));
    $response->assertRedirect(tenantRoute($tenant, 'login'));
});
