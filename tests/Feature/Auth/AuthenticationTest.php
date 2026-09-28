<?php

use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use App\TenantRole;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

it('renders the tenant selection screen', function (): void {
    $response = $this->get(route('tenant.find'));

    $response->assertOk();
});

test('renders the tenant login screen', function (): void {
    $tenant = Tenant::factory()->withDomain()->create();

    $response = $this->get(tenantRoute($tenant, 'login'));

    $response->assertOk();
});

test('names the workspace host on the tenant login screen', function (): void {
    $tenant = Tenant::factory()->withDomain()->create();

    $this->get(tenantRoute($tenant, 'login'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/login')
            ->where('workspaceHost', $tenant->slug.'.'.config('app.domain')));
});

test('users can authenticate using the login screen', function (): void {
    $user = User::factory()->create();

    $tenant = Tenant::factory()->withDomain()->withMember($user)->create();

    $response = $this->post(tenantRoute($tenant, 'login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('authentication uses a host-only session cookie', function (): void {
    config(['session.driver' => 'database']);
    app('session')->forgetDrivers();

    $user = User::factory()->create();

    $tenant = Tenant::factory()->withDomain()->withMember($user)->create();

    $response = $this->post(tenantRoute($tenant, 'login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $sessionCookie = $response->getCookie(config('session.cookie'), decrypt: false);

    expect($sessionCookie)
        ->not->toBeNull()
        ->and($sessionCookie->getDomain())->toBeNull();
});

test('users with two factor enabled are redirected to two factor challenge', function (): void {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($user)->create();

    $response = $this->post(tenantRoute($tenant, 'login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $response->assertSessionHas('login.id', $user->id);
    $this->assertGuest();
});

test('users can not authenticate with invalid password', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($user)->create();

    $this->post(tenantRoute($tenant, 'login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($user)->create();

    $this->post(tenantRoute($tenant, 'login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);

    $response = $this->post(tenantRoute($tenant, 'logout'));

    $response->assertRedirect('/');

    $this->assertGuest();
});

test('users are rate limited', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($user)->create();

    RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

    $response = $this->post(tenantRoute($tenant, 'login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertTooManyRequests();
});

it('does not authenticate a user who is not a member of the current tenant', function (): void {
    $user = User::factory()->create();
    Tenant::factory()->withMember($user)->create();

    $tenant = Tenant::factory()->withDomain()->create();

    $response = $this->post(tenantRoute($tenant, 'login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors([
        'email' => __('auth.failed'),
    ]);

    $this->assertGuest();
});

it('regenerates session upon login', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($user)->create();

    $this->get(tenantRoute($tenant, 'login'));
    $initialSessionId = session()->getId();

    $response = $this->post(tenantRoute($tenant, 'login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $newSessionId = session()->getId();

    $this->assertAuthenticated();
    expect($initialSessionId)->not->toBe($newSessionId);

    $response->assertRedirect(route('dashboard', absolute: false));
});

test('a user with a pending invitation can log in when the emails differ only by case', function (): void {
    $inviter = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($inviter, TenantRole::Owner)->create();
    Invitation::factory()->forTenant($tenant, $inviter)->create([
        'email' => 'Gilfoyle@PiedPiper.com',
    ]);
    $invitee = User::factory()->create(['email' => 'GILFOYLE@piedpiper.com']);

    $this->post(tenantRoute($tenant, 'login.store'), [
        'email' => 'gilFOYLE@PiedPiper.com',
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($invitee);
});
