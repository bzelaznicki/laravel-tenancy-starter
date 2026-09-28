<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Authorization and data shape are covered by
 * tests/Feature/Tenant/MembershipAuthorizationTest.php. This browser spec only
 * exercises the migrated React page against fixture props — matching the
 * AccountsFrontendTest/DashboardFrontendTest convention of a throwaway web
 * route, decoupled from tenancy.
 *
 * @param  array{
 *     id?: int,
 *     role?: string,
 *     joinedAt?: string|null,
 *     user?: array{id: string, name: string, email: string},
 *     capabilities?: array{assignableRoles: list<string>, canDelete: bool}
 * }  $overrides
 * @return array{
 *     id: int,
 *     role: string,
 *     joinedAt: string|null,
 *     user: array{id: string, name: string, email: string},
 *     capabilities: array{assignableRoles: list<string>, canDelete: bool}
 * }
 */
function membershipFixture(array $overrides = []): array
{
    return array_merge([
        'id' => 1,
        'role' => 'owner',
        'joinedAt' => '2026-02-04T00:00:00Z',
        'user' => [
            'id' => 'usr-1',
            'name' => 'Monica Hall',
            'email' => 'monica@bream-hall.com',
        ],
        'capabilities' => [
            'assignableRoles' => ['owner', 'admin', 'member', 'viewer'],
            'canDelete' => true,
        ],
    ], $overrides);
}

it('lets an owner see and manage every membership', function (): void {
    Route::middleware('web')->get('/test/members-owner', fn (): Response => Inertia::render('settings/members', [
        'memberships' => [
            membershipFixture(),
            membershipFixture([
                'id' => 2,
                'role' => 'member',
                'user' => [
                    'id' => 'usr-2',
                    'name' => 'Gilfoyle B.',
                    'email' => 'gilfoyle@bream-hall.com',
                ],
                'capabilities' => [
                    'assignableRoles' => ['owner', 'admin', 'member', 'viewer'],
                    'canDelete' => true,
                ],
            ]),
        ],
        'pendingInvitations' => [],
    ]));

    $this->actingAs(User::factory()->create());

    $page = visit('/test/members-owner')
        ->assertSee('Monica Hall')
        ->assertSee('Gilfoyle B.')
        ->assertSee('No pending invitations yet')
        ->assertPresent('[data-test="change-role-1"]')
        ->assertPresent('[data-test="remove-member-1"]')
        ->assertPresent('[data-test="change-role-2"]')
        ->assertPresent('[data-test="remove-member-2"]')
        ->assertNoJavaScriptErrors();
});

it('locks owner and admin rows out of an admin\'s view', function (): void {
    Route::middleware('web')->get('/test/members-admin', fn (): Response => Inertia::render('settings/members', [
        'memberships' => [
            membershipFixture([
                'capabilities' => [
                    'assignableRoles' => [],
                    'canDelete' => false,
                ],
            ]),
            membershipFixture([
                'id' => 2,
                'role' => 'member',
                'user' => [
                    'id' => 'usr-2',
                    'name' => 'Gilfoyle B.',
                    'email' => 'gilfoyle@bream-hall.com',
                ],
                'capabilities' => [
                    'assignableRoles' => ['member', 'viewer'],
                    'canDelete' => true,
                ],
            ]),
        ],
        'pendingInvitations' => [],
    ]));

    $this->actingAs(User::factory()->create());

    visit('/test/members-admin')
        ->assertSee('Monica Hall')
        ->assertSee('not yours to manage')
        ->assertSee('Gilfoyle B.')
        ->assertPresent('[data-test="membership-locked-1"]')
        ->assertMissing('[data-test="change-role-1"]')
        ->assertMissing('[data-test="remove-member-1"]')
        ->assertPresent('[data-test="change-role-2"]')
        ->assertPresent('[data-test="remove-member-2"]')
        ->assertNoJavaScriptErrors();
});

it('hides management controls for the sole owner\'s own row', function (): void {
    Route::middleware('web')->get('/test/members-sole-owner', fn (): Response => Inertia::render('settings/members', [
        'memberships' => [
            membershipFixture([
                'capabilities' => [
                    'assignableRoles' => ['owner'],
                    'canDelete' => false,
                ],
            ]),
        ],
        'pendingInvitations' => [],
    ]));

    $this->actingAs(User::factory()->create());

    visit('/test/members-sole-owner')
        ->assertSee('Monica Hall')
        ->assertPresent('[data-test="membership-locked-1"]')
        ->assertMissing('[data-test="change-role-1"]')
        ->assertMissing('[data-test="remove-member-1"]')
        ->assertNoJavaScriptErrors();
});

/**
 * @param  array{
 *     id?: string,
 *     email?: string,
 *     role?: string,
 *     invitedAt?: string|null,
 *     expiresAt?: string,
 *     resendAvailableAt?: string|null,
 *     invitedBy?: array{name: string}|null,
 *     capabilities?: array{canRevoke: bool, canResend: bool}
 * }  $overrides
 * @return array{
 *     id: string,
 *     email: string,
 *     role: string,
 *     invitedAt: string|null,
 *     expiresAt: string,
 *     resendAvailableAt: string|null,
 *     invitedBy: array{name: string}|null,
 *     capabilities: array{canRevoke: bool, canResend: bool}
 * }
 */
function pendingInvitationFixture(array $overrides = []): array
{
    return array_merge([
        'id' => 'inv-1',
        'email' => 'dinesh@bream-hall.com',
        'role' => 'member',
        'invitedAt' => '2026-02-04T00:00:00Z',
        'expiresAt' => now()->addDays(7)->toIso8601String(),
        'resendAvailableAt' => null,
        'invitedBy' => ['name' => 'Monica Hall'],
        'capabilities' => ['canRevoke' => true, 'canResend' => true],
    ], $overrides);
}

/**
 * The invite dialog reads the actor's grantable roles and workspace name from
 * shared auth props, which tenancy normally supplies. Share them directly so
 * this spec stays decoupled from tenancy like the rest of the file.
 *
 * @param  list<string>  $assignableRoles
 * @param  list<array<string, mixed>>  $pendingInvitations
 */
function registerMembersPage(
    string $path,
    array $assignableRoles,
    array $pendingInvitations = [],
): void {
    Route::middleware('web')->get($path, function () use ($assignableRoles, $pendingInvitations): Response {
        Inertia::share('auth', [
            'user' => ['id' => 'usr-1', 'name' => 'Monica Hall', 'email' => 'monica@bream-hall.com'],
            'tenant' => ['id' => 'tnt-1', 'name' => 'bream-hall', 'slug' => 'bream-hall'],
            'role' => 'owner',
            'membershipCapabilities' => ['assignableRoles' => $assignableRoles],
        ]);

        return Inertia::render('settings/members', [
            'memberships' => [membershipFixture([
                'role' => 'member',
                'capabilities' => ['assignableRoles' => [], 'canDelete' => false],
            ])],
            'pendingInvitations' => $pendingInvitations,
        ]);
    });
}

it('opens an invite dialog offering only the roles the actor may grant', function (): void {
    registerMembersPage('/test/members-invite', ['member', 'viewer']);

    $this->actingAs(User::factory()->create());

    visit('/test/members-invite')
        ->assertPresent('[data-test="invite-member"]')
        ->click('[data-test="invite-member"]')
        ->assertSee('Invite someone to bream-hall')
        ->assertSee('Nobody joins until they accept.')
        ->assertPresent('[data-test="invite-email"]')
        ->assertPresent('[data-test="invite-role"]')
        ->assertPresent('[data-test="invite-message"]')
        ->click('[data-test="invite-role"]')
        ->assertSee('Viewer')
        ->assertDontSee('Owner')
        ->assertDontSee('Admin')
        ->assertNoJavaScriptErrors();
});

it('keeps send disabled until the address looks like an email', function (): void {
    registerMembersPage('/test/members-invite-validation', ['member', 'viewer']);

    $this->actingAs(User::factory()->create());

    visit('/test/members-invite-validation')
        ->click('[data-test="invite-member"]')
        ->assertDisabled('[data-test="confirm-invite-button"]')
        ->type('[data-test="invite-email"]', 'not-an-address')
        ->assertDisabled('[data-test="confirm-invite-button"]')
        ->type('[data-test="invite-email"]', 'dinesh@bream-hall.com')
        ->assertEnabled('[data-test="confirm-invite-button"]')
        ->assertNoJavaScriptErrors();
});

it('gives an actor without grantable roles no invite button', function (): void {
    registerMembersPage('/test/members-no-invite', []);

    $this->actingAs(User::factory()->create());

    visit('/test/members-no-invite')
        ->assertMissing('[data-test="invite-member"]')
        ->assertNoJavaScriptErrors();
});

it('lists a pending invitation with resend and revoke', function (): void {
    registerMembersPage('/test/members-pending', ['member', 'viewer'], [
        pendingInvitationFixture(),
    ]);

    $this->actingAs(User::factory()->create());

    visit('/test/members-pending')
        ->assertSee('dinesh@bream-hall.com')
        ->assertSee('Invited by Monica Hall')
        ->assertSee('Sent · expires in 7 days')
        ->assertPresent('[data-test="resend-invitation-inv-1"]')
        ->assertPresent('[data-test="revoke-invitation-inv-1"]')
        ->assertDontSee('No pending invitations yet')
        ->assertNoJavaScriptErrors();
});

it('lists a pending invitation from a former member', function (): void {
    registerMembersPage('/test/members-pending/former-inviter', ['member', 'viewer'], [
        pendingInvitationFixture(['invitedBy' => null]),
    ]);

    $this->actingAs(User::factory()->create());

    visit('/test/members-pending/former-inviter')
        ->assertSee('dinesh@bream-hall.com')
        ->assertSee('Invited by a former member')
        ->assertNoJavaScriptErrors();
});

it('asks before revoking an invitation', function (): void {
    registerMembersPage('/test/members-revoke', ['member', 'viewer'], [
        pendingInvitationFixture(),
    ]);

    $this->actingAs(User::factory()->create());

    visit('/test/members-revoke')
        ->click('[data-test="revoke-invitation-inv-1"]')
        ->assertSee('Revoke the invitation to dinesh@bream-hall.com?')
        ->assertSee('The link stops working immediately')
        ->assertPresent('[data-test="confirm-revoke-invitation-button"]')
        ->assertNoJavaScriptErrors();
});

it('locks an invitation an actor may not manage', function (): void {
    registerMembersPage('/test/members-pending-locked', ['member', 'viewer'], [
        pendingInvitationFixture([
            'role' => 'owner',
            'capabilities' => ['canRevoke' => false, 'canResend' => false],
        ]),
    ]);

    $this->actingAs(User::factory()->create());

    visit('/test/members-pending-locked')
        ->assertPresent('[data-test="invitation-locked-inv-1"]')
        ->assertMissing('[data-test="resend-invitation-inv-1"]')
        ->assertMissing('[data-test="revoke-invitation-inv-1"]')
        ->assertNoJavaScriptErrors();
});

it('disables resend during cooldown and shows the server-provided retry time', function (): void {
    registerMembersPage('/test/members-resend-cooldown', ['member', 'viewer'], [
        pendingInvitationFixture([
            'resendAvailableAt' => now()->addMinute()->toIso8601String(),
        ]),
    ]);

    $this->actingAs(User::factory()->create());

    visit('/test/members-resend-cooldown')
        ->assertDisabled('[data-test="resend-invitation-inv-1"]')
        ->assertSee('Resend in')
        ->refresh()
        ->assertDisabled('[data-test="resend-invitation-inv-1"]')
        ->assertSee('Resend in')
        ->assertNoJavaScriptErrors();
});
