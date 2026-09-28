<?php

use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use App\TenantRole;
use Stancl\Tenancy\Database\Models\Domain;

test('profile page is displayed', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($user)->create();

    $response = $this
        ->actingAs($user)
        ->get(tenantRoute($tenant, 'profile.edit'));

    $response->assertOk();
});

test('profile information can be updated', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($user)->create();

    $response = $this
        ->actingAs($user)
        ->patch(tenantRoute($tenant, 'profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(tenantRoute($tenant, 'profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
    expect($user->first_verified_at)->not->toBeNull();
});

test('email verification status is unchanged when the email address is unchanged', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($user)->create();

    $response = $this
        ->actingAs($user)
        ->patch(tenantRoute($tenant, 'profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(tenantRoute($tenant, 'profile.edit'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($user)->create();

    $response = $this
        ->actingAs($user)
        ->delete(tenantRoute($tenant, 'profile.destroy'), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});

test('correct password must be provided to delete account', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($user)->create();

    $response = $this
        ->actingAs($user)
        ->from(tenantRoute($tenant, 'profile.edit'))
        ->delete(tenantRoute($tenant, 'profile.destroy'), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect(tenantRoute($tenant, 'profile.edit'));

    expect($user->fresh())->not->toBeNull();
});

test('a mixed-case email saved through profile settings is stored lowercased', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($user)->create();

    $this->actingAs($user)
        ->patch(tenantRoute($tenant, 'profile.update'), [
            'name' => $user->name,
            'email' => 'Dinesh@PiedPiper.com',
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->email)->toBe('dinesh@piedpiper.com');
});

test('a mixed-case email saved through profile settings can still be used to log in', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($user)->create();

    $this->actingAs($user)
        ->patch(tenantRoute($tenant, 'profile.update'), [
            'name' => $user->name,
            'email' => 'Dinesh@PiedPiper.com',
        ])
        ->assertSessionHasNoErrors();

    auth()->logout();
    $this->assertGuest();

    $this->post(tenantRoute($tenant, 'login.store'), [
        'email' => 'dinesh@piedpiper.com',
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
});

test('profile email update rejects a case variant of another user email', function (): void {
    User::factory()->create(['email' => 'gilfoyle@piedpiper.com']);
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($user)->create();
    $originalEmail = $user->email;

    $this->actingAs($user)
        ->patch(tenantRoute($tenant, 'profile.update'), [
            'name' => $user->name,
            'email' => 'Gilfoyle@PiedPiper.com',
        ])
        ->assertSessionHasErrors('email');

    expect($user->refresh()->email)->toBe($originalEmail);
});

test('inviter can delete their account and their invitations are kept', function (): void {
    $inviter = User::factory()->create();
    $otherInviter = User::factory()->create();
    $tenant = Tenant::factory()
        ->withDomain()
        ->withMember($inviter, TenantRole::Owner)
        ->withMember($otherInviter, TenantRole::Owner)
        ->create();

    $accepted = Invitation::factory()->forTenant($tenant, $inviter)->accepted()->create();
    $pending = Invitation::factory()->forTenant($tenant, $inviter)->create();
    $unrelated = Invitation::factory()->forTenant($tenant, $otherInviter)->create();

    $response = $this
        ->actingAs($inviter)
        ->delete(tenantRoute($tenant, 'profile.destroy'), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();

    expect($inviter->fresh())->toBeNull()
        ->and($accepted->refresh()->invited_by)->toBeNull()
        ->and($pending->refresh()->invited_by)->toBeNull()
        ->and($unrelated->refresh()->invited_by)->toBe($otherInviter->id);
});

test('the only member of a workspace deletes the workspace with their account', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($user)->create();
    $invitation = Invitation::factory()->forTenant($tenant, $user)->create();

    $this->actingAs($user)
        ->delete(tenantRoute($tenant, 'profile.destroy'), [
            'password' => 'password',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();

    expect($user->fresh())->toBeNull()
        ->and($tenant->fresh())->toBeNull()
        ->and(Domain::query()->where('tenant_id', $tenant->id)->exists())->toBeFalse()
        ->and($invitation->fresh())->toBeNull();
});

test('the last owner of a workspace with other members cannot delete their account', function (): void {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $tenant = Tenant::factory()
        ->withDomain()
        ->withMember($owner, TenantRole::Owner)
        ->withMember($admin, TenantRole::Admin)
        ->create(['name' => 'Hooli']);

    $this->actingAs($owner)
        ->from(tenantRoute($tenant, 'profile.edit'))
        ->delete(tenantRoute($tenant, 'profile.destroy'), [
            'password' => 'password',
        ])
        ->assertSessionHasErrors(['account' => 'You are the only owner of Hooli. Make another member an owner, or remove the other members, before you delete your account.'])
        ->assertRedirect(tenantRoute($tenant, 'profile.edit'));

    $this->assertAuthenticatedAs($owner);

    expect($owner->fresh())->not->toBeNull()
        ->and($tenant->fresh())->not->toBeNull()
        ->and($owner->roleFor($tenant))->toBe(TenantRole::Owner);
});

test('a blocked account deletion names every workspace and deletes none of them', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $soloTenant = Tenant::factory()->withDomain()->withMember($owner)->create();
    $firstTenant = Tenant::factory()
        ->withDomain()
        ->withMember($owner)
        ->withMember($member, TenantRole::Viewer)
        ->create(['name' => 'Raviga']);
    $secondTenant = Tenant::factory()
        ->withDomain()
        ->withMember($owner)
        ->withMember($member, TenantRole::Member)
        ->create(['name' => 'Aviato']);

    $this->actingAs($owner)
        ->delete(tenantRoute($soloTenant, 'profile.destroy'), [
            'password' => 'password',
        ])
        ->assertSessionHasErrors(['account' => 'You are the only owner of Aviato, Raviga. Make another member an owner, or remove the other members, before you delete your account.']);

    expect($owner->fresh())->not->toBeNull()
        ->and($soloTenant->fresh())->not->toBeNull()
        ->and($firstTenant->fresh())->not->toBeNull()
        ->and($secondTenant->fresh())->not->toBeNull();
});

test('an owner of several workspaces deletes only the ones where they are the only member', function (): void {
    $owner = User::factory()->create();
    $otherOwner = User::factory()->create();
    $soloTenant = Tenant::factory()->withDomain()->withMember($owner)->create();
    $sharedTenant = Tenant::factory()
        ->withDomain()
        ->withMember($owner, TenantRole::Owner)
        ->withMember($otherOwner, TenantRole::Owner)
        ->create();

    $this->actingAs($owner)
        ->delete(tenantRoute($sharedTenant, 'profile.destroy'), [
            'password' => 'password',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();

    expect($owner->fresh())->toBeNull()
        ->and($soloTenant->fresh())->toBeNull()
        ->and($sharedTenant->fresh())->not->toBeNull()
        ->and($sharedTenant->users()->count())->toBe(1)
        ->and($otherOwner->roleFor($sharedTenant))->toBe(TenantRole::Owner);
});

test('an owner who shares ownership can delete their account and the workspace is kept', function (): void {
    $owner = User::factory()->create();
    $otherOwner = User::factory()->create();
    $tenant = Tenant::factory()
        ->withDomain()
        ->withMember($owner, TenantRole::Owner)
        ->withMember($otherOwner, TenantRole::Owner)
        ->create();

    $this->actingAs($owner)
        ->delete(tenantRoute($tenant, 'profile.destroy'), [
            'password' => 'password',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    expect($owner->fresh())->toBeNull()
        ->and($tenant->fresh())->not->toBeNull()
        ->and($otherOwner->roleFor($tenant))->toBe(TenantRole::Owner);
});

test('a member who is not an owner can delete their account and the workspace is kept', function (): void {
    $owner = User::factory()->create();
    $regularMember = User::factory()->create();
    $tenant = Tenant::factory()
        ->withDomain()
        ->withMember($owner, TenantRole::Owner)
        ->withMember($regularMember, TenantRole::Member)
        ->create();

    $this->actingAs($regularMember)
        ->delete(tenantRoute($tenant, 'profile.destroy'), [
            'password' => 'password',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    expect($regularMember->fresh())->toBeNull()
        ->and($tenant->fresh())->not->toBeNull()
        ->and($tenant->users()->count())->toBe(1);
});
