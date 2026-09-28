<?php

use App\Models\Tenant;
use App\Models\User;

dataset('settings routes', [
    'settings redirect' => ['get', '/settings'],
    'profile edit' => ['get', '/settings/profile'],
    'profile update' => ['patch', '/settings/profile'],
    'profile destroy' => ['delete', '/settings/profile'],
    'security edit' => ['get', '/settings/security'],
    'password update' => ['put', '/settings/password'],
    'appearance edit' => ['get', '/settings/appearance'],
]);

it('forbids settings routes to users who are not members of the tenant', function (string $method, string $path): void {
    $user = User::factory()->create();
    Tenant::factory()->withDomain()->withMember($user)->create();
    $otherTenant = Tenant::factory()->withDomain()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->{$method}(tenantUrl($otherTenant, $path))
        ->assertForbidden();

    expect($user->fresh())->not->toBeNull();
})->with('settings routes');

it('does not serve settings routes on the central domain', function (string $method, string $path): void {
    $user = User::factory()->create();
    Tenant::factory()->withDomain()->withMember($user)->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->{$method}('https://'.config('app.domain').$path)
        ->assertNotFound();

    expect($user->fresh())->not->toBeNull();
})->with('settings routes');

it('serves passkey endpoints for the tenant subdomain when passkeys are enabled', function (): void {
    $tenant = Tenant::factory()->withDomain()->create();

    $this->get(tenantUrl($tenant, '/.well-known/passkey-endpoints'))
        ->assertOk()
        ->assertExactJson([
            'enroll' => tenantRoute($tenant, 'security.edit'),
            'manage' => tenantRoute($tenant, 'security.edit'),
        ]);
});

it('does not serve passkey endpoints when passkeys are disabled', function (): void {
    config(['fortify.features' => []]);

    $tenant = Tenant::factory()->withDomain()->create();

    $this->get(tenantUrl($tenant, '/.well-known/passkey-endpoints'))
        ->assertNotFound();
});

it('does not serve passkey endpoints on the central domain', function (): void {
    $this->get('https://'.config('app.domain').'/.well-known/passkey-endpoints')
        ->assertNotFound();
});
