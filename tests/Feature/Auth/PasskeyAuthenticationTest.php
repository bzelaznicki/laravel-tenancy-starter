<?php

use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Passkeys;

it('configures the tenant host as the allowed passkey origin', function (): void {
    $tenant = Tenant::factory()->withDomain()->create();

    $tenantHost = tenantHost($tenant);
    $tenantOrigin = "https://{$tenantHost}";

    $this->get("{$tenantOrigin}/passkeys/login/options")
        ->assertOk();

    expect(config('fortify.passkeys.allowed_origins'))
        ->toBe([$tenantOrigin])
        ->and(config('passkeys.allowed_origins'))->toBe([$tenantOrigin])
        ->and(config('fortify.passkeys.relying_party_id'))->toBe(config('app.domain'));
});

it('rejects an unknown tenant host before configuring its passkey origin', function (): void {
    $originalAllowedOrigins = config('passkeys.allowed_origins');
    $unknownTenantOrigin = 'https://unknown.'.config('app.domain');

    $this->get("{$unknownTenantOrigin}/passkeys/login/options")
        ->assertNotFound();

    expect(config('passkeys.allowed_origins'))->toBe($originalAllowedOrigins);
});

it('allows a tenant member to authenticate with a passkey', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($user)->create();

    $passkey = new Passkey;
    $passkey->setRelation('user', $user);

    Route::middleware(config('fortify.middleware'))
        ->get('/test/passkey-authorization', function (Request $request) use ($passkey) {
            return response()->json([
                'allowed' => Passkeys::allowsLogin($request, $passkey),
                'tenant_id' => tenant()->getKey(),
            ]);
        });

    $tenantHost = tenantHost($tenant);

    $this->get("https://{$tenantHost}/test/passkey-authorization")
        ->assertOk()
        ->assertJson([
            'allowed' => true,
            'tenant_id' => $tenant->getKey(),
        ]);
});

it('allows an existing invitee to authenticate with a passkey before membership exists', function (): void {
    $inviter = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->create();
    $invitee = User::factory()->create();

    Invitation::factory()
        ->forTenant($tenant, $inviter)
        ->create([
            'email' => $invitee->email,
        ]);

    $passkey = new Passkey;
    $passkey->setRelation('user', $invitee);

    Route::middleware(config('fortify.middleware'))
        ->get('/test/passkey-invitation-authorization', function (Request $request) use ($passkey) {
            return response()->json([
                'allowed' => Passkeys::allowsLogin($request, $passkey),
                'tenant_id' => tenant()->getKey(),
            ]);
        });

    $tenantHost = tenantHost($tenant);

    $this->get("https://{$tenantHost}/test/passkey-invitation-authorization")
        ->assertOk()
        ->assertJson([
            'allowed' => true,
            'tenant_id' => $tenant->getKey(),
        ]);

    $this->assertDatabaseMissing('tenant_user', [
        'tenant_id' => $tenant->id,
        'user_id' => $invitee->id,
    ]);
});

it('rejects passkey authentication when the user is not a tenant member', function (): void {
    $user = User::factory()->create();
    Tenant::factory()->withMember($user)->create();

    $tenant = Tenant::factory()->withDomain()->create();

    $passkey = new Passkey;
    $passkey->setRelation('user', $user);

    Route::middleware(config('fortify.middleware'))
        ->get('/test/passkey-authorization', function (Request $request) use ($passkey) {
            return response()->json([
                'allowed' => Passkeys::allowsLogin($request, $passkey),
                'tenant_id' => tenant()->getKey(),
            ]);
        });

    $tenantHost = tenantHost($tenant);

    $this->get("https://{$tenantHost}/test/passkey-authorization")
        ->assertOk()
        ->assertJson([
            'allowed' => false,
            'tenant_id' => $tenant->getKey(),
        ]);
});
