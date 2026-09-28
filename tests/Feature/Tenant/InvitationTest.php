<?php

use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use App\TenantRole;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;

use function Pest\Laravel\assertModelExists;

it('creates a pending invitation using the default factory state', function (): void {
    $inviter = User::factory()->create();
    $tenant = Tenant::factory()->withMember($inviter)->create();

    $invitation = Invitation::factory()->forTenant($tenant, $inviter)->create();

    assertModelExists($invitation);

    expect($invitation->email)->not->toBeNull()
        ->and($invitation->tenant_id)->toBe($tenant->id)
        ->and($invitation->invited_by)->toBe($inviter->id)
        ->and($invitation->token_hash)->toHaveLength(64)
        ->and($invitation->expires_at->isFuture())->toBeTrue()
        ->and($invitation->accepted_at)->toBeNull()
        ->and($invitation->revoked_at)->toBeNull()
        ->and($invitation->expired_at)->toBeNull()
        ->and($invitation->message)->toBeNull();
});

it('casts the role to a tenant role', function (): void {
    $invitation = Invitation::factory()->create(['role' => TenantRole::Admin]);

    $dbInvitation = Invitation::query()->where(['id' => $invitation->id])->sole();

    expect($dbInvitation->role)->toBe(TenantRole::Admin);
});

it('casts invitation lifecycle timestamps to carbon instances', function (): void {
    $pending = Invitation::factory()->create()->refresh();
    $accepted = Invitation::factory()->accepted()->create()->refresh();
    $revoked = Invitation::factory()->revoked()->create()->refresh();
    $expired = Invitation::factory()->expired()->create()->refresh();

    expect($pending->expires_at)->toBeInstanceOf(CarbonInterface::class)
        ->and($accepted->accepted_at)->toBeInstanceOf(CarbonInterface::class)
        ->and($revoked->revoked_at)->toBeInstanceOf(CarbonInterface::class)
        ->and($expired->expired_at)->toBeInstanceOf(CarbonInterface::class);
});

it('resolves the tenant and inviter relationships', function (): void {
    $inviter = User::factory()->create();
    $tenant = Tenant::factory()->withMember($inviter, TenantRole::Owner)->create();

    $invitation = Invitation::factory()->forTenant($tenant, $inviter)->create()->refresh();

    expect($invitation->inviter->is($inviter))->toBeTrue()
        ->and($invitation->tenant->is($tenant))->toBeTrue();
});

it('hides the token hash when serialized', function (): void {
    $invitation = Invitation::factory()->create();

    expect($invitation->token_hash)->not->toBeNull()
        ->and($invitation->toArray())->not->toHaveKey('token_hash');
});

it('creates an invitation with a personal message', function (): void {
    $message = 'Hello, welcome to the team!';
    $invitation = Invitation::factory()->withMessage($message)->create()->refresh();

    expect($invitation->message)->toBe($message);
});

it('creates accepted revoked and expired factory states', function (): void {
    $accepted = Invitation::factory()->accepted()->create()->refresh();
    $revoked = Invitation::factory()->revoked()->create()->refresh();
    $expired = Invitation::factory()->expired()->create()->refresh();

    expect($accepted->accepted_at)->not->toBeNull()
        ->and($accepted->revoked_at)->toBeNull()
        ->and($accepted->expired_at)->toBeNull()
        ->and($revoked->accepted_at)->toBeNull()
        ->and($revoked->revoked_at)->not->toBeNull()
        ->and($revoked->expired_at)->toBeNull()
        ->and($expired->accepted_at)->toBeNull()
        ->and($expired->revoked_at)->toBeNull()
        ->and($expired->expired_at)->not->toBeNull()
        ->and($expired->expires_at->isPast())->toBeTrue();
});

it('prevents multiple open invitations for the same tenant and email', function (): void {
    $tenant = Tenant::factory()->create();
    $email = 'gilfoyle@piedpiper.com';

    Invitation::factory()->for($tenant)->create(['email' => $email]);

    expect(fn () => Invitation::factory()
        ->for($tenant)
        ->create(['email' => $email]))
        ->toThrow(QueryException::class);
});

it('allows a replacement after an invitation is accepted revoked or expired', function (string $state): void {
    $inviter = User::factory()->create();
    $tenant = Tenant::factory()->withMember($inviter)->create();
    $email = 'dinesh@piedpiper.com';

    Invitation::factory()->forTenant($tenant, $inviter)->{$state}()->create(['email' => $email]);

    $replacement = Invitation::factory()->forTenant($tenant, $inviter)->create(['email' => $email]);

    assertModelExists($replacement);

    expect(Invitation::query()
        ->where('tenant_id', $tenant->id)
        ->where('email', $email)
        ->count())
        ->toBe(2);
})->with([
    'accepted' => 'accepted',
    'revoked' => 'revoked',
    'expired' => 'expired',
]);

it('allows the same email to have open invitations in different tenants', function (): void {
    $email = 'jared@piedpiper.com';
    $firstTenant = Tenant::factory()->create();
    $secondTenant = Tenant::factory()->create();

    Invitation::factory()->for($firstTenant)->create(['email' => $email]);
    Invitation::factory()->for($secondTenant)->create(['email' => $email]);

    expect(
        Invitation::query()
            ->where('email', $email)
            ->count()
    )->toBe(2);
});
