<?php

use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use App\TenantRole;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Token validation, mode selection and acceptance itself are covered by
 * tests/Feature/Tenant/InvitationAcceptanceTest.php. This browser spec only
 * exercises the React page against fixture props — matching the
 * MembersSettingsFrontendTest convention of a throwaway web route, decoupled
 * from tenancy.
 *
 * @param  array{
 *     id?: string,
 *     email?: string,
 *     message?: string|null,
 *     role?: string,
 *     expiresAt?: string,
 *     invitedBy?: array{name: string}|null,
 *     tenant?: array{name: string}
 * }  $overrides
 * @return array{
 *     id: string,
 *     email: string,
 *     message: string|null,
 *     role: string,
 *     expiresAt: string,
 *     invitedBy: array{name: string}|null,
 *     tenant: array{name: string}
 * }
 */
function invitationFixture(array $overrides = []): array
{
    return array_merge([
        'id' => '01K0INVITATION0000000000',
        'email' => 'dinesh@bream-hall.com',
        'message' => null,
        'role' => 'member',
        'expiresAt' => '2026-09-04T00:00:00Z',
        'invitedBy' => ['name' => 'Monica Hall'],
        'tenant' => ['name' => 'bream-hall'],
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $invitation
 */
function registerInvitationPage(string $path, string $mode, array $invitation): void
{
    Route::middleware('web')->get($path, fn (): Response => Inertia::render('invitations/accept', [
        'invitation' => $invitation,
        'token' => 'fixture-token',
        'mode' => $mode,
        'passwordRules' => 'minlength: 8;',
    ]));
}

it('asks an invitee without an account to create one', function (): void {
    registerInvitationPage('/test/invitation-register', 'register', invitationFixture([
        'message' => 'Starting Monday on the enterprise book.',
    ]));

    visit('/test/invitation-register')
        ->assertSee('Monica Hall invited you to bream-hall')
        ->assertSee('Starting Monday on the enterprise book.')
        ->assertSee('Fixed by the invitation.')
        ->assertValue('#email', 'dinesh@bream-hall.com')
        ->assertPresent('[data-test="invitation-name"]')
        ->assertPresent('[data-test="invitation-password"]')
        ->assertPresent('[data-test="invitation-password-confirmation"]')
        ->assertSee('Accept and create account')
        ->assertNoJavaScriptErrors();
});

it('asks a signed-in invitee only to confirm', function (): void {
    registerInvitationPage('/test/invitation-confirm', 'confirm', invitationFixture([
        'role' => 'admin',
    ]));

    visit('/test/invitation-confirm')
        ->assertSee('Join bream-hall as Admin')
        ->assertSee('Signed in as')
        ->assertSee('dinesh@bream-hall.com')
        ->assertSee('Accept invitation')
        ->assertMissing('[data-test="invitation-password"]')
        ->assertNoJavaScriptErrors();
});

it('sends an invitee who already has an account through sign-in first', function (): void {
    registerInvitationPage('/test/invitation-sign-in', 'sign_in', invitationFixture());

    visit('/test/invitation-sign-in')
        ->assertSee('Join bream-hall as Member')
        ->assertSee('Invitation for')
        ->assertSee('Sign in to accept')
        ->assertMissing('[data-test="invitation-password"]')
        ->assertPresent('[data-test="sign-in-to-accept-button"]')
        ->assertNoJavaScriptErrors();
});

it('takes an existing invitee from their invitation through first tenant access', function (): void {
    $inviter = User::factory()->create();
    $invitee = User::factory()->create([
        'email' => 'gilfoyle.browser@piedpiper.com',
    ]);
    $tenant = Tenant::factory()
        ->withDomain('invitation-browser')
        ->withMember($inviter, TenantRole::Owner)
        ->create(['slug' => 'invitation-browser']);
    $token = Str::random(64);
    $invitation = Invitation::factory()
        ->forTenant($tenant, $inviter)
        ->create([
            'email' => $invitee->email,
            'role' => TenantRole::Member,
            'token_hash' => hash('sha256', $token),
        ]);

    $page = visit('/');
    $tenantHost = tenantHost($tenant);

    $page->navigate(browserUrl($page, $tenantHost, "/invitations/{$invitation->id}/accept", ['token' => $token]))
        ->assertHostIs($tenantHost)
        ->assertSee('Sign in to accept')
        ->click('[data-test="sign-in-to-accept-button"]')
        ->assertPathIs('/login')
        ->fill('email', $invitee->email)
        ->fill('password', 'password')
        ->click('@login-button')
        ->assertPathIs("/invitations/{$invitation->id}/accept")
        ->assertSee('Accept invitation')
        ->click('[data-test="accept-invitation-button"]')
        ->assertPathIs('/dashboard')
        ->assertSee('Secure your account')
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseHas('tenant_user', [
        'tenant_id' => $tenant->id,
        'user_id' => $invitee->id,
        'role' => TenantRole::Member->value,
    ]);

    expect($invitation->refresh()->accepted_at)->not->toBeNull();
});

it('invites a new account without naming a former inviter', function (): void {
    registerInvitationPage('/test/invitation-register-former-inviter', 'register', invitationFixture([
        'invitedBy' => null,
    ]));

    visit('/test/invitation-register-former-inviter')
        ->assertSee('You have been invited to join bream-hall')
        ->assertNoJavaScriptErrors();
});

it('asks a signed-in invitee to confirm without naming a former inviter', function (): void {
    registerInvitationPage('/test/invitation-confirm-former-inviter', 'confirm', invitationFixture([
        'invitedBy' => null,
    ]));

    visit('/test/invitation-confirm-former-inviter')
        ->assertSee('dinesh@bream-hall.com was invited to bream-hall')
        ->assertNoJavaScriptErrors();
});
