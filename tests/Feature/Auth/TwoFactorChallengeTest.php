<?php

use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

beforeEach(function (): void {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
});

test('two factor challenge redirects to login when not authenticated', function (): void {
    $tenant = Tenant::factory()->withDomain()->create();

    $response = $this->get(tenantRoute($tenant, 'two-factor.login'));

    $response->assertRedirect(route('login'));
});

test('two factor challenge can be rendered', function (): void {
    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $tenant = Tenant::factory()->withDomain()->withMember($user)->create();

    $this->post(tenantRoute($tenant, 'login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->get(tenantRoute($tenant, 'two-factor.login'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('auth/two-factor-challenge'),
        );
});
