<?php

use App\Http\Controllers\InvitationController;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use App\TenantRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

it('allows a guest with a valid token to view the invitation on its tenant host', function (): void {
    $inviter = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($inviter, TenantRole::Owner)->create();
    $token = Str::random(64);
    $invitation = Invitation::factory()->forTenant($tenant, $inviter)->create([
        'token_hash' => hash('sha256', $token),
    ]);
    $response = $this->get(tenantRoute($tenant, 'invitations.accept', [
        'invitation' => $invitation->id,
        'token' => $token,
    ]));

    $response->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('invitations/accept')
                ->where('invitation.id', $invitation->id)
                ->where('invitation.tenant.name', $tenant->name)
                ->where('invitation.role', $invitation->role->value)
                ->missing('invitation.token_hash')
        );

    $this->assertGuest();
});

it('rejects viewing an invitation without a token', function (): void {
    $inviter = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($inviter, TenantRole::Owner)->create();
    $token = Str::random(64);
    $invitation = Invitation::factory()->forTenant($tenant, $inviter)->create([
        'token_hash' => hash('sha256', $token),
    ]);

    $this->get(tenantRoute($tenant, 'invitations.accept', [
        'invitation' => $invitation->id,
    ]))->assertNotFound();
});

it('rejects viewing an invitation with an altered token', function (): void {
    $inviter = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($inviter, TenantRole::Owner)->create();
    $token = Str::random(64);
    $invitation = Invitation::factory()->forTenant($tenant, $inviter)->create([
        'token_hash' => hash('sha256', $token),
    ]);

    $this->get(tenantRoute($tenant, 'invitations.accept', [
        'invitation' => $invitation->id,
        'token' => Str::random(64),
    ]))->assertNotFound();
});

it('rejects viewing an invitation on another tenant host', function (): void {
    $inviter = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($inviter, TenantRole::Owner)->create();
    $token = Str::random(64);
    $invitation = Invitation::factory()->forTenant($tenant, $inviter)->create([
        'token_hash' => hash('sha256', $token),
    ]);

    $otherTenant = Tenant::factory()->withDomain()->create();
    $this->get(tenantRoute($otherTenant, 'invitations.accept', [
        'invitation' => $invitation->id,
        'token' => $token,
    ]))->assertNotFound();
});

it('rejects viewing an invitation whose expiry has passed even when expired_at is null', function (): void {
    $inviter = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($inviter, TenantRole::Owner)->create();
    $token = Str::random(64);
    $invitation = Invitation::factory()->forTenant($tenant, $inviter)->create([
        'token_hash' => hash('sha256', $token),
    ]);

    $invitation->update(['expires_at' => now()->subMinute(), 'expired_at' => null]);
    $this->get(tenantRoute($tenant, 'invitations.accept', [
        'invitation' => $invitation->id,
        'token' => $token,
    ]))->assertNotFound();
});

it('rejects viewing a revoked invitation', function (): void {
    $inviter = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($inviter, TenantRole::Owner)->create();
    $token = Str::random(64);
    $invitation = Invitation::factory()->forTenant($tenant, $inviter)->create([
        'token_hash' => hash('sha256', $token),
    ]);

    $invitation->update(['revoked_at' => now()]);
    $this->get(tenantRoute($tenant, 'invitations.accept', [
        'invitation' => $invitation->id,
        'token' => $token,
    ]))->assertNotFound();
});

it('rejects viewing an already accepted invitation', function (): void {
    $inviter = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($inviter, TenantRole::Owner)->create();
    $token = Str::random(64);
    $invitation = Invitation::factory()->forTenant($tenant, $inviter)->create([
        'token_hash' => hash('sha256', $token),
    ]);

    $invitation->update(['accepted_at' => now()]);
    $this->get(tenantRoute($tenant, 'invitations.accept', [
        'invitation' => $invitation->id,
        'token' => $token,
    ]))->assertNotFound();
});

it('does not mark the invitation accepted or create membership when viewing it', function (): void {
    $inviter = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($inviter, TenantRole::Owner)->create();
    $token = Str::random(64);
    $invitation = Invitation::factory()->forTenant($tenant, $inviter)->create([
        'token_hash' => hash('sha256', $token),
    ]);

    $originalAttributes = $invitation->refresh()->getRawOriginal();
    $this->get(tenantRoute($tenant, 'invitations.accept', [
        'invitation' => $invitation->id,
        'token' => $token,
    ]))->assertOk();

    expect($invitation->refresh()->getRawOriginal())->toBe($originalAttributes);
    $this->assertDatabaseCount('tenant_user', 1);
    $this->assertDatabaseCount('users', 1);
    $this->assertGuest();
});

it('rejects an empty token', function (): void {
    $inviter = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($inviter, TenantRole::Owner)->create();
    $token = Str::random(64);
    $invitation = Invitation::factory()->forTenant($tenant, $inviter)->create([
        'token_hash' => hash('sha256', $token),
    ]);

    $this->get(tenantRoute($tenant, 'invitations.accept', [
        'invitation' => $invitation->id,
        'token' => '',
    ]))->assertNotFound();
});

it('rejects an array token', function (): void {
    $inviter = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($inviter, TenantRole::Owner)->create();
    $token = Str::random(64);
    $invitation = Invitation::factory()->forTenant($tenant, $inviter)->create([
        'token_hash' => hash('sha256', $token),
    ]);

    $this->get(tenantRoute($tenant, 'invitations.accept', [
        'invitation' => $invitation->id,
        'token' => [$token],
    ]))->assertNotFound();
});

it('rejects an invitation at its exact expiry', function (): void {
    $inviter = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($inviter, TenantRole::Owner)->create();
    $token = Str::random(64);
    $invitation = Invitation::factory()->forTenant($tenant, $inviter)->create([
        'token_hash' => hash('sha256', $token),
    ]);

    $this->freezeSecond();
    $invitation->update(['expires_at' => now()]);
    $this->get(tenantRoute($tenant, 'invitations.accept', [
        'invitation' => $invitation->id,
        'token' => $token,
    ]))->assertNotFound();
});

it('rejects an explicitly expired invitation even with a future deadline', function (): void {
    $inviter = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($inviter, TenantRole::Owner)->create();
    $token = Str::random(64);
    $invitation = Invitation::factory()->forTenant($tenant, $inviter)->create([
        'token_hash' => hash('sha256', $token),
    ]);

    $invitation->update(['expired_at' => now(), 'expires_at' => now()->addDay()]);
    $this->get(tenantRoute($tenant, 'invitations.accept', [
        'invitation' => $invitation->id,
        'token' => $token,
    ]))->assertNotFound();
});

it('accepts an invitation for a new user with verified email and membership then redirects to tenant login as a guest', function (): void {

    $inviter = User::factory()->create();
    $tenant = Tenant::factory()
        ->withDomain()
        ->withMember($inviter, TenantRole::Owner)
        ->create();

    $token = Str::random(64);

    $invitation = Invitation::factory()
        ->forTenant($tenant, $inviter)
        ->create([
            'email' => 'bighead@piedpiper.com',
            'role' => TenantRole::Member,
            'token_hash' => hash('sha256', $token),
        ]);

    $response = $this->post(
        tenantRoute($tenant, 'invitations.accept.store', [
            'invitation' => $invitation->id,
        ]),
        [
            'token' => $token,
            'name' => 'Nelson Bighetti',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ],
    );

    $response->assertSessionHasNoErrors()
        ->assertRedirect(tenantRoute($tenant, 'login'));

    $this->assertGuest();

    $user = User::query()
        ->where('email', $invitation->email)
        ->sole();

    expect($user->name)->toBe('Nelson Bighetti')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('StrongPassword123!', $user->password))->toBeTrue();

    $this->assertDatabaseHas('tenant_user', [
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => TenantRole::Member->value,
    ]);

    expect($invitation->refresh()->accepted_at)->not->toBeNull();

    $this->assertDatabaseCount('users', 2);
    $this->assertDatabaseCount('tenant_user', 2);
});

it('validates the name password and password confirmation when accepting as a new user', function (
    array $overrides,
    string $errorField,
    ?string $unsetField = null,
): void {
    $inviter = User::factory()->create();
    $tenant = Tenant::factory()
        ->withDomain()
        ->withMember($inviter, TenantRole::Owner)
        ->create();
    $token = Str::random(64);
    $invitation = Invitation::factory()
        ->forTenant($tenant, $inviter)
        ->create([
            'email' => 'bighead@piedpiper.com',
            'role' => TenantRole::Member,
            'token_hash' => hash('sha256', $token),
        ]);

    $payload = array_replace([
        'token' => $token,
        'name' => 'Nelson Bighetti',
        'password' => 'StrongPassword123!',
        'password_confirmation' => 'StrongPassword123!',
    ], $overrides);

    if ($unsetField !== null) {
        unset($payload[$unsetField]);
    }

    $this->post(
        tenantRoute($tenant, 'invitations.accept.store', [
            'invitation' => $invitation->id,
        ]),
        $payload,
    )->assertSessionHasErrors([$errorField]);

    $this->assertGuest();
    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseCount('tenant_user', 1);

    expect($invitation->refresh()->accepted_at)->toBeNull();
})->with([
    'missing name' => [[], 'name', 'name'],
    'empty name' => [['name' => ''], 'name'],
    'name too long' => [['name' => str_repeat('a', 256)], 'name'],
    'missing password' => [[], 'password', 'password'],
    'weak password' => [[
        'password' => 'short',
        'password_confirmation' => 'short',
    ], 'password'],
    'missing password confirmation' => [[], 'password', 'password_confirmation'],
    'mismatched password confirmation' => [[
        'password_confirmation' => 'DifferentPassword123!',
    ], 'password'],
]);

it('uses the invited email tenant and role instead of client supplied values when accepting', function (): void {
    $inviter = User::factory()->create();
    $tenant = Tenant::factory()
        ->withDomain()
        ->withMember($inviter, TenantRole::Owner)
        ->create();
    $otherTenant = Tenant::factory()->create();
    $token = Str::random(64);
    $invitation = Invitation::factory()
        ->forTenant($tenant, $inviter)
        ->create([
            'email' => 'bighead@piedpiper.com',
            'role' => TenantRole::Member,
            'token_hash' => hash('sha256', $token),
        ]);

    $response = $this->post(
        tenantRoute($tenant, 'invitations.accept.store', [
            'invitation' => $invitation->id,
        ]),
        [
            'token' => $token,
            'email' => 'gavin.belson@hooli.com',
            'tenant_id' => $otherTenant->id,
            'role' => TenantRole::Owner->value,
            'name' => 'Gavin Belson',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ],
    );

    $response->assertSessionHasNoErrors()
        ->assertRedirect(tenantRoute($tenant, 'login'));

    $this->assertGuest();
    $this->assertDatabaseMissing('users', [
        'email' => 'gavin.belson@hooli.com',
    ]);

    $user = User::query()
        ->where('email', $invitation->email)
        ->sole();

    expect($user->name)->toBe('Gavin Belson');

    $this->assertDatabaseHas('tenant_user', [
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => TenantRole::Member->value,
    ]);
    $this->assertDatabaseMissing('tenant_user', [
        'tenant_id' => $otherTenant->id,
        'user_id' => $user->id,
    ]);

    expect($invitation->refresh()->accepted_at)->not->toBeNull();

    $this->assertDatabaseCount('users', 2);
    $this->assertDatabaseCount('tenant_user', 2);
});

it('rolls back user creation membership and acceptance when accepting fails', function (): void {
    $inviter = User::factory()->create();
    $tenant = Tenant::factory()
        ->withDomain()
        ->withMember($inviter, TenantRole::Owner)
        ->create();
    $token = Str::random(64);
    $invitation = Invitation::factory()
        ->forTenant($tenant, $inviter)
        ->create([
            'email' => 'bighead@piedpiper.com',
            'role' => TenantRole::Member,
            'token_hash' => hash('sha256', $token),
        ]);

    Exceptions::fake();

    Invitation::updating(function (Invitation $candidate) use ($invitation): void {
        if ($candidate->getKey() === $invitation->getKey() && $candidate->isDirty('accepted_at')) {
            throw new RuntimeException('Forced acceptance failure.');
        }
    });

    $this->post(
        tenantRoute($tenant, 'invitations.accept.store', [
            'invitation' => $invitation->id,
        ]),
        [
            'token' => $token,
            'name' => 'Nelson Bighetti',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ],
    )->assertServerError();

    Exceptions::assertReported(
        fn (RuntimeException $exception): bool => $exception->getMessage() === 'Forced acceptance failure.',
    );

    $this->assertGuest();
    $this->assertDatabaseMissing('users', [
        'email' => $invitation->email,
    ]);
    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseCount('tenant_user', 1);

    expect($invitation->refresh()->accepted_at)->toBeNull();
});

it('requires an existing user to authenticate before accepting without changing their account', function (): void {
    $inviter = User::factory()->create();
    $tenant = Tenant::factory()
        ->withDomain()
        ->withMember($inviter, TenantRole::Owner)
        ->create();

    $token = Str::random(64);

    $invitation = Invitation::factory()
        ->forTenant($tenant, $inviter)
        ->create([
            'email' => 'gilfoyle@piedpiper.com',
            'role' => TenantRole::Member,
            'token_hash' => hash('sha256', $token),
        ]);

    $invitee = User::factory()->create([
        'email' => 'gilfoyle@piedpiper.com',
    ]);

    $originalName = $invitee->name;
    $originalPassword = $invitee->password;
    $originalEmailVerifiedAt = $invitee->email_verified_at;

    $response = $this->post(
        tenantRoute($tenant, 'invitations.accept.store', [
            'invitation' => $invitation->id,
        ]),
        ['token' => $token],
    );

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(tenantRoute($tenant, 'login'));

    $this->assertGuest();
    $this->assertDatabaseCount('users', 2);
    $this->assertDatabaseCount('tenant_user', 1);

    $invitee->refresh();

    expect($invitee)
        ->name->toBe($originalName)
        ->password->toBe($originalPassword)
        ->email_verified_at->toEqual($originalEmailVerifiedAt);

    expect($invitation->refresh()->accepted_at)->toBeNull();

    $this->assertDatabaseMissing('tenant_user', [
        'tenant_id' => $tenant->id,
        'user_id' => $invitee->id,
    ]);
});

it('allows an existing invitee to log in on the target tenant before membership exists', function (): void {
    $inviter = User::factory()->create();
    $tenant = Tenant::factory()
        ->withDomain()
        ->withMember($inviter, TenantRole::Owner)
        ->create();

    $invitation = Invitation::factory()
        ->forTenant($tenant, $inviter)
        ->create([
            'email' => 'gilfoyle@piedpiper.com',
            'role' => TenantRole::Member,
        ]);

    $invitee = User::factory()->create([
        'email' => $invitation->email,
    ]);

    $response = $this->post(
        tenantRoute($tenant, 'login.store'),
        [
            'email' => $invitee->email,
            'password' => 'password',
        ],
    );

    $this->assertAuthenticatedAs($invitee);

    $response->assertRedirect(route('dashboard', absolute: false));

    $this->assertDatabaseMissing('tenant_user', [
        'tenant_id' => $tenant->id,
        'user_id' => $invitee->id,
    ]);

    expect($invitation->refresh()->accepted_at)->toBeNull();
});

it('keeps ordinary tenant pages forbidden until the authenticated invitee accepts', function (): void {
    $inviter = User::factory()->create();
    $tenant = Tenant::factory()
        ->withDomain()
        ->withMember($inviter, TenantRole::Owner)
        ->create();

    $invitation = Invitation::factory()
        ->forTenant($tenant, $inviter)
        ->create([
            'email' => 'gilfoyle@piedpiper.com',
            'role' => TenantRole::Member,
        ]);

    $invitee = User::factory()->create([
        'email' => $invitation->email,
    ]);

    $this->post(
        tenantRoute($tenant, 'login.store'),
        [
            'email' => $invitee->email,
            'password' => 'password',
        ],
    );

    $this->assertAuthenticatedAs($invitee);

    $this->get(tenantRoute($tenant, 'dashboard'))
        ->assertForbidden();

    $this->assertDatabaseMissing('tenant_user', [
        'tenant_id' => $tenant->id,
        'user_id' => $invitee->id,
    ]);

    expect($invitation->refresh()->accepted_at)->toBeNull();
});

it('accepts an invitation for an authenticated user with matching email and redirects to the tenant dashboard', function (): void {
    $inviter = User::factory()->create();
    $tenant = Tenant::factory()
        ->withDomain()
        ->withMember($inviter, TenantRole::Owner)
        ->create();
    $token = Str::random(64);
    $invitation = Invitation::factory()
        ->forTenant($tenant, $inviter)
        ->create([
            'email' => 'gilfoyle@piedpiper.com',
            'role' => TenantRole::Member,
            'token_hash' => hash('sha256', $token),
        ]);
    $invitee = User::factory()->create([
        'email' => $invitation->email,
    ]);
    $originalName = $invitee->name;
    $originalPassword = $invitee->password;
    $originalEmailVerifiedAt = $invitee->email_verified_at;

    $this->post(
        tenantRoute($tenant, 'login.store'),
        [
            'email' => $invitee->email,
            'password' => 'password',
        ],
    );

    $this->assertAuthenticatedAs($invitee);

    $response = $this->post(
        tenantRoute($tenant, 'invitations.accept.store', [
            'invitation' => $invitation->id,
        ]),
        ['token' => $token],
    );

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(tenantRoute($tenant, 'dashboard'));

    $this->assertAuthenticatedAs($invitee);
    $this->assertDatabaseHas('tenant_user', [
        'tenant_id' => $tenant->id,
        'user_id' => $invitee->id,
        'role' => TenantRole::Member->value,
    ]);
    $this->assertDatabaseCount('users', 2);
    $this->assertDatabaseCount('tenant_user', 2);

    $invitee->refresh();

    expect($invitee)
        ->name->toBe($originalName)
        ->password->toBe($originalPassword)
        ->email_verified_at->toEqual($originalEmailVerifiedAt);

    expect($invitation->refresh()->accepted_at)->not->toBeNull();
});

it('rejects acceptance by an authenticated user with a different email', function (): void {
    $inviter = User::factory()->create();
    $tenant = Tenant::factory()
        ->withDomain()
        ->withMember($inviter, TenantRole::Owner)
        ->create();
    $token = Str::random(64);
    $invitation = Invitation::factory()
        ->forTenant($tenant, $inviter)
        ->create([
            'email' => 'gilfoyle@piedpiper.com',
            'role' => TenantRole::Member,
            'token_hash' => hash('sha256', $token),
        ]);
    $invitee = User::factory()->create([
        'email' => $invitation->email,
    ]);

    $this->actingAs($inviter)
        ->post(
            tenantRoute($tenant, 'invitations.accept.store', [
                'invitation' => $invitation->id,
            ]),
            ['token' => $token],
        )
        ->assertForbidden();

    $this->assertAuthenticatedAs($inviter);

    $this->assertDatabaseMissing('tenant_user', [
        'tenant_id' => $tenant->id,
        'user_id' => $invitee->id,
    ]);

    $this->assertDatabaseHas('tenant_user', [
        'tenant_id' => $tenant->id,
        'user_id' => $inviter->id,
        'role' => TenantRole::Owner->value,
    ]);

    $this->assertDatabaseCount('tenant_user', 1);

    expect($invitation->refresh()->accepted_at)->toBeNull();
});

it('rejects POST acceptance with a missing empty malformed or altered token', function (array $tokenPayload): void {
    $inviter = User::factory()->create();
    $tenant = Tenant::factory()
        ->withDomain()
        ->withMember($inviter, TenantRole::Owner)
        ->create();
    $token = str_repeat('b', 64);
    $invitation = Invitation::factory()
        ->forTenant($tenant, $inviter)
        ->create([
            'token_hash' => hash('sha256', $token),
        ]);

    $this->post(
        tenantRoute($tenant, 'invitations.accept.store', [
            'invitation' => $invitation->id,
        ]),
        $tokenPayload,
    )->assertNotFound();

    $this->assertGuest();
    $this->assertDatabaseMissing('users', [
        'email' => $invitation->email,
    ]);
    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseCount('tenant_user', 1);

    expect($invitation->refresh()->accepted_at)->toBeNull();
})->with([
    'missing token' => [[]],
    'empty token' => [['token' => '']],
    'malformed token' => [['token' => []]],
    'altered token' => [['token' => str_repeat('a', 64)]],
]);

it('rejects POST acceptance on another tenant host', function (): void {
    $inviter = User::factory()->create();
    $tenant = Tenant::factory()
        ->withDomain()
        ->withMember($inviter, TenantRole::Owner)
        ->create();
    $otherTenant = Tenant::factory()->withDomain()->create();
    $token = Str::random(64);
    $invitation = Invitation::factory()
        ->forTenant($tenant, $inviter)
        ->create([
            'token_hash' => hash('sha256', $token),
        ]);

    $this->post(
        tenantRoute($otherTenant, 'invitations.accept.store', [
            'invitation' => $invitation->id,
        ]),
        ['token' => $token],
    )->assertNotFound();

    $this->assertGuest();
    $this->assertDatabaseMissing('users', [
        'email' => $invitation->email,
    ]);
    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseCount('tenant_user', 1);

    expect($invitation->refresh()->accepted_at)->toBeNull();
});

it('rejects POST acceptance of expired revoked or already accepted invitations', function (string $state): void {
    $inviter = User::factory()->create();
    $tenant = Tenant::factory()
        ->withDomain()
        ->withMember($inviter, TenantRole::Owner)
        ->create();
    $token = Str::random(64);
    $invitation = Invitation::factory()
        ->forTenant($tenant, $inviter)
        ->create([
            'token_hash' => hash('sha256', $token),
        ]);

    match ($state) {
        'past deadline' => $invitation->update([
            'expires_at' => now()->subMinute(),
            'expired_at' => null,
        ]),
        'explicitly expired' => $invitation->update([
            'expires_at' => now()->addDay(),
            'expired_at' => now(),
        ]),
        'revoked' => $invitation->update(['revoked_at' => now()]),
        'accepted' => $invitation->update(['accepted_at' => now()]),
    };

    $originalLifecycle = $invitation->only([
        'expires_at',
        'expired_at',
        'revoked_at',
        'accepted_at',
    ]);

    $this->post(
        tenantRoute($tenant, 'invitations.accept.store', [
            'invitation' => $invitation->id,
        ]),
        ['token' => $token],
    )->assertNotFound();

    $this->assertGuest();
    $this->assertDatabaseMissing('users', [
        'email' => $invitation->email,
    ]);
    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseCount('tenant_user', 1);

    expect($invitation->refresh()->only([
        'expires_at',
        'expired_at',
        'revoked_at',
        'accepted_at',
    ]))->toEqual($originalLifecycle);
})->with([
    'past deadline' => ['past deadline'],
    'explicitly expired' => ['explicitly expired'],
    'revoked' => ['revoked'],
    'already accepted' => ['accepted'],
]);

it('revalidates the invitation on POST when it becomes invalid after viewing the page', function (): void {
    $inviter = User::factory()->create();
    $tenant = Tenant::factory()
        ->withDomain()
        ->withMember($inviter, TenantRole::Owner)
        ->create();
    $token = Str::random(64);
    $invitation = Invitation::factory()
        ->forTenant($tenant, $inviter)
        ->create([
            'token_hash' => hash('sha256', $token),
        ]);

    $this->get(tenantRoute($tenant, 'invitations.accept', [
        'invitation' => $invitation->id,
        'token' => $token,
    ]))->assertOk();

    $invitation->update(['revoked_at' => now()]);

    $this->post(
        tenantRoute($tenant, 'invitations.accept.store', [
            'invitation' => $invitation->id,
        ]),
        [
            'token' => $token,
            'name' => 'Gilfoyle',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ],
    )->assertNotFound();

    $this->assertGuest();
    $this->assertDatabaseMissing('users', [
        'email' => $invitation->email,
    ]);
    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseCount('tenant_user', 1);

    $invitation->refresh();

    expect($invitation->revoked_at)->not->toBeNull()
        ->and($invitation->accepted_at)->toBeNull();
});

it('does not duplicate membership or change its role when the invitee is already a member', function (): void {
    $inviter = User::factory()->create();
    $invitee = User::factory()->create([
        'email' => 'gilfoyle@piedpiper.com',
    ]);
    $tenant = Tenant::factory()
        ->withDomain()
        ->withMember($inviter, TenantRole::Owner)
        ->withMember($invitee, TenantRole::Viewer)
        ->create();
    $token = Str::random(64);
    $invitation = Invitation::factory()
        ->forTenant($tenant, $inviter)
        ->create([
            'email' => $invitee->email,
            'role' => TenantRole::Member,
            'token_hash' => hash('sha256', $token),
        ]);

    $this->actingAs($invitee)
        ->post(
            tenantRoute($tenant, 'invitations.accept.store', [
                'invitation' => $invitation->id,
            ]),
            ['token' => $token],
        )
        ->assertSessionHasNoErrors()
        ->assertRedirect(tenantRoute($tenant, 'dashboard'));

    $this->assertDatabaseHas('tenant_user', [
        'tenant_id' => $tenant->id,
        'user_id' => $invitee->id,
        'role' => TenantRole::Viewer->value,
    ]);
    $this->assertDatabaseCount('users', 2);
    $this->assertDatabaseCount('tenant_user', 2);

    expect($invitation->refresh()->accepted_at)->not->toBeNull();
});

it('does not create duplicate users or memberships when acceptance is replayed', function (): void {
    $inviter = User::factory()->create();
    $tenant = Tenant::factory()
        ->withDomain()
        ->withMember($inviter, TenantRole::Owner)
        ->create();
    $token = Str::random(64);
    $invitation = Invitation::factory()
        ->forTenant($tenant, $inviter)
        ->create([
            'email' => 'gilfoyle@piedpiper.com',
            'role' => TenantRole::Member,
            'token_hash' => hash('sha256', $token),
        ]);
    $payload = [
        'token' => $token,
        'name' => 'Gilfoyle',
        'password' => 'StrongPassword123!',
        'password_confirmation' => 'StrongPassword123!',
    ];
    $acceptUrl = tenantRoute($tenant, 'invitations.accept.store', [
        'invitation' => $invitation->id,
    ]);

    $this->post($acceptUrl, $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect(tenantRoute($tenant, 'login'));

    $acceptedAt = $invitation->refresh()->accepted_at;

    $this->post($acceptUrl, $payload)
        ->assertNotFound();

    $invitee = User::query()
        ->where('email', $invitation->email)
        ->sole();

    $this->assertGuest();
    $this->assertDatabaseHas('tenant_user', [
        'tenant_id' => $tenant->id,
        'user_id' => $invitee->id,
        'role' => TenantRole::Member->value,
    ]);
    $this->assertDatabaseCount('users', 2);
    $this->assertDatabaseCount('tenant_user', 2);

    expect($invitation->refresh()->accepted_at)->toEqual($acceptedAt);
});

it('creates exactly one membership when an invitation is accepted concurrently', function (): void {
    if (! function_exists('pcntl_fork') || ! function_exists('stream_socket_pair')) {
        $this->markTestSkipped('This test requires the PCNTL and sockets extensions.');
    }

    $inviter = User::factory()->create();
    $invitee = User::factory()->create([
        'email' => 'gilfoyle.concurrent@piedpiper.com',
    ]);
    $tenant = Tenant::factory()
        ->withDomain()
        ->withMember($inviter, TenantRole::Owner)
        ->create();
    $token = Str::random(64);
    $invitation = Invitation::factory()
        ->forTenant($tenant, $inviter)
        ->create([
            'email' => $invitee->email,
            'role' => TenantRole::Member,
            'token_hash' => hash('sha256', $token),
        ]);
    $connection = DB::connection();
    $connection->commit();
    $children = [];

    try {
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

            if ($sockets === false) {
                throw new RuntimeException('Unable to create a socket pair.');
            }

            $processId = pcntl_fork();

            if ($processId === -1) {
                throw new RuntimeException('Unable to fork an invitation acceptance process.');
            }

            if ($processId === 0) {
                fclose($sockets[0]);

                foreach ($children as $child) {
                    fclose($child['socket']);
                }

                runConcurrentInvitationAcceptanceAttempt(
                    $sockets[1],
                    $tenant->id,
                    $invitee->id,
                    $invitation->id,
                    $token,
                );
            }

            fclose($sockets[1]);
            stream_set_timeout($sockets[0], 10);

            $children[] = [
                'processId' => $processId,
                'socket' => $sockets[0],
            ];
        }

        foreach ($children as $child) {
            expect(trim((string) fgets($child['socket'])))->toBe('ready');
        }

        foreach ($children as $child) {
            fwrite($child['socket'], "go\n");
        }

        $results = [];

        foreach ($children as $child) {
            $results[] = json_decode(trim((string) fgets($child['socket'])), true, flags: JSON_THROW_ON_ERROR);
            fclose($child['socket']);
            pcntl_waitpid($child['processId'], $status);

            expect(pcntl_wexitstatus($status))->toBe(0);
        }

        expect(array_column($results, 'status'))
            ->toHaveCount(2)
            ->toContain('success')
            ->toContain('failure')
            ->and(collect($results)->firstWhere('status', 'failure')['exception'])
            ->toBe(NotFoundHttpException::class);

        $this->assertDatabaseHas('tenant_user', [
            'tenant_id' => $tenant->id,
            'user_id' => $invitee->id,
            'role' => TenantRole::Member->value,
        ]);
        $this->assertDatabaseCount('tenant_user', 2);

        expect($invitation->refresh()->accepted_at)->not->toBeNull();
    } finally {
        foreach ($children as $child) {
            if (is_resource($child['socket'])) {
                fclose($child['socket']);
            }

            pcntl_waitpid($child['processId'], $status, WNOHANG);
        }

        DB::table('invitations')->where('id', $invitation->id)->delete();
        DB::table('tenant_user')->where('tenant_id', $tenant->id)->delete();
        DB::table('domains')->where('tenant_id', $tenant->id)->delete();
        DB::table('tenants')->where('id', $tenant->id)->delete();
        DB::table('users')->whereIn('id', [$inviter->id, $invitee->id])->delete();

        $connection->beginTransaction();
    }
});

/**
 * @param  resource  $socket
 */
function runConcurrentInvitationAcceptanceAttempt(
    $socket,
    string $tenantId,
    string $inviteeId,
    string $invitationId,
    string $token,
): never {
    $exitCode = 0;

    try {
        DB::purge();

        $tenant = Tenant::query()->findOrFail($tenantId);
        $invitee = User::query()->findOrFail($inviteeId);
        tenancy()->initialize($tenant);

        fwrite($socket, "ready\n");

        if (trim((string) fgets($socket)) !== 'go') {
            throw new RuntimeException('The concurrent invitation acceptance was not released.');
        }

        $request = Request::create('/invitations/'.$invitationId.'/accept', 'POST', [
            'token' => $token,
        ]);
        $request->setUserResolver(fn (): User => $invitee);

        try {
            app(InvitationController::class)->accept($request, $invitationId);

            $result = ['status' => 'success'];
        } catch (Throwable $exception) {
            $result = [
                'status' => 'failure',
                'exception' => $exception::class,
            ];
        }

        fwrite($socket, json_encode($result, JSON_THROW_ON_ERROR)."\n");
    } catch (Throwable $exception) {
        $exitCode = 1;

        try {
            fwrite($socket, json_encode([
                'status' => 'failure',
                'exception' => $exception::class,
            ], JSON_THROW_ON_ERROR)."\n");
        } catch (Throwable) {
        }
    } finally {
        if (is_resource($socket)) {
            fclose($socket);
        }

        exit($exitCode);
    }
}

it('gives the acceptance page register mode and the details it renders when the invited email has no account', function (): void {
    $inviter = User::factory()->create(['name' => 'Monica Hall']);
    $tenant = Tenant::factory()
        ->withDomain()
        ->withMember($inviter, TenantRole::Owner)
        ->create();
    $token = Str::random(64);
    $invitation = Invitation::factory()
        ->forTenant($tenant, $inviter)
        ->withMessage('Starting Monday on the enterprise book.')
        ->create([
            'email' => 'dinesh@piedpiper.com',
            'role' => TenantRole::Member,
            'token_hash' => hash('sha256', $token),
        ]);

    $this->get(tenantRoute($tenant, 'invitations.accept', [
        'invitation' => $invitation->id,
        'token' => $token,
    ]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('invitations/accept')
                ->where('mode', 'register')
                ->where('token', $token)
                ->where('invitation.email', 'dinesh@piedpiper.com')
                ->where('invitation.message', 'Starting Monday on the enterprise book.')
                ->where('invitation.role', TenantRole::Member->value)
                ->where('invitation.invitedBy.name', 'Monica Hall')
                ->where('invitation.expiresAt', $invitation->expires_at->toIso8601String())
                ->has('passwordRules')
                ->missing('invitation.token_hash')
        );
});

it('gives the acceptance page confirm mode when the invitee views their own invitation while signed in', function (): void {
    $inviter = User::factory()->create();
    $tenant = Tenant::factory()
        ->withDomain()
        ->withMember($inviter, TenantRole::Owner)
        ->create();
    $token = Str::random(64);
    $invitation = Invitation::factory()
        ->forTenant($tenant, $inviter)
        ->create([
            'email' => 'gilfoyle@piedpiper.com',
            'token_hash' => hash('sha256', $token),
        ]);
    $invitee = User::factory()->create([
        'email' => $invitation->email,
    ]);

    $this->post(tenantRoute($tenant, 'login.store'), [
        'email' => $invitee->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($invitee);

    $this->get(tenantRoute($tenant, 'invitations.accept', [
        'invitation' => $invitation->id,
        'token' => $token,
    ]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('invitations/accept')
                ->where('mode', 'confirm')
        );
});

it('gives the acceptance page sign in mode when the invited email already has an account', function (
    bool $signInAsSomeoneElse,
): void {
    $inviter = User::factory()->create();
    $tenant = Tenant::factory()
        ->withDomain()
        ->withMember($inviter, TenantRole::Owner)
        ->create();
    $token = Str::random(64);
    $invitation = Invitation::factory()
        ->forTenant($tenant, $inviter)
        ->create([
            'email' => 'gilfoyle@piedpiper.com',
            'token_hash' => hash('sha256', $token),
        ]);
    User::factory()->create(['email' => $invitation->email]);

    if ($signInAsSomeoneElse) {
        $this->actingAs(User::factory()->create([
            'email' => 'richard@piedpiper.com',
        ]));
    }

    $acceptanceUrl = tenantRoute($tenant, 'invitations.accept', [
        'invitation' => $invitation->id,
        'token' => $token,
    ]);

    $response = $this->get($acceptanceUrl)
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('invitations/accept')
                ->where('mode', 'sign_in')
        );

    if ($signInAsSomeoneElse) {
        $response->assertSessionMissing('url.intended');
    } else {
        $response->assertSessionHas('url.intended', $acceptanceUrl);
    }
})->with([
    'viewed by a guest' => [false],
    'viewed while signed in as a different user' => [true],
]);

it('shows the invitation to an existing user whose email differs only by case', function (): void {
    $inviter = User::factory()->create();
    $tenant = Tenant::factory()
        ->withDomain()
        ->withMember($inviter, TenantRole::Owner)
        ->create();
    $token = Str::random(64);
    $invitation = Invitation::factory()
        ->forTenant($tenant, $inviter)
        ->create([
            'email' => 'Gilfoyle@PiedPiper.com',
            'token_hash' => hash('sha256', $token),
        ]);
    User::factory()->create(['email' => 'GILFOYLE@piedpiper.com']);

    $this->get(tenantRoute($tenant, 'invitations.accept', [
        'invitation' => $invitation->id,
        'token' => $token,
    ]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('invitations/accept')
                ->where('mode', 'sign_in')
        );
});

it('attaches an existing user whose email differs only by case instead of creating a second user', function (): void {
    $inviter = User::factory()->create();
    $tenant = Tenant::factory()
        ->withDomain()
        ->withMember($inviter, TenantRole::Owner)
        ->create();
    $token = Str::random(64);
    $invitation = Invitation::factory()
        ->forTenant($tenant, $inviter)
        ->create([
            'email' => 'Gilfoyle@PiedPiper.com',
            'role' => TenantRole::Member,
            'token_hash' => hash('sha256', $token),
        ]);
    $invitee = User::factory()->create(['email' => 'GILFOYLE@piedpiper.com']);

    $this->post(tenantRoute($tenant, 'login.store'), [
        'email' => 'gilFOYLE@PiedPiper.com',
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($invitee);

    $this->post(
        tenantRoute($tenant, 'invitations.accept.store', [
            'invitation' => $invitation->id,
        ]),
        ['token' => $token],
    )
        ->assertSessionHasNoErrors()
        ->assertRedirect(tenantRoute($tenant, 'dashboard'));

    $this->assertDatabaseCount('users', 2);
    $this->assertDatabaseHas('tenant_user', [
        'tenant_id' => $tenant->id,
        'user_id' => $invitee->id,
        'role' => TenantRole::Member->value,
    ]);
    expect($invitation->refresh()->accepted_at)->not->toBeNull();
});

it('gives the acceptance page a null inviter when the inviter was deleted', function (): void {
    $tenant = Tenant::factory()->withDomain()->create();
    $token = Str::random(64);
    $invitation = Invitation::factory()
        ->for($tenant)
        ->create([
            'invited_by' => null,
            'token_hash' => hash('sha256', $token),
        ]);

    $this->get(tenantRoute($tenant, 'invitations.accept', [
        'invitation' => $invitation->id,
        'token' => $token,
    ]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('invitations/accept')
                ->where('invitation.invitedBy', null)
        );
});
