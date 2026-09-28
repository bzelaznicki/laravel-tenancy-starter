<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Features;

beforeEach(function (): void {
    $this->skipUnlessFortifyHas(Features::emailVerification());
});

test('email verification screen can be rendered', function (): void {
    $user = User::factory()->unverified()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($user)->create();

    $response = $this->actingAs($user)->get(tenantRoute($tenant, 'verification.notice'));

    $response->assertOk();
});

test('email can be verified', function (): void {
    $user = User::factory()->unverified()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($user)->create();

    $tenantHost = tenantHost($tenant);
    URL::forceRootUrl('https://'.$tenantHost);

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $response = $this->actingAs($user)->get($verificationUrl);

    Event::assertDispatched(Verified::class);

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue()
        ->and($user->fresh()->first_verified_at)->not->toBeNull();
    $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
});

test('verifying a changed email keeps the first verification time', function (): void {
    $user = User::factory()->pendingEmailChange()->create();
    $firstVerifiedAt = $user->first_verified_at;

    $user->markEmailAsVerified();

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue()
        ->and($user->fresh()->first_verified_at->equalTo($firstVerifiedAt))->toBeTrue();
});

test('email is not verified with invalid hash', function (): void {
    $user = User::factory()->unverified()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($user)->create();

    $tenantHost = tenantHost($tenant);
    URL::forceRootUrl('https://'.$tenantHost);

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1('wrong-email')],
    );

    $this->actingAs($user)->get($verificationUrl);

    Event::assertNotDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('email is not verified with invalid user id', function (): void {
    $user = User::factory()->unverified()->create();

    $tenant = Tenant::factory()
        ->withDomain()
        ->withMember($user)
        ->create();

    URL::forceRootUrl('https://'.tenantHost($tenant));

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        [
            'id' => 123,
            'hash' => sha1($user->email),
        ],
    );

    $this->actingAs($user)
        ->get($verificationUrl)
        ->assertForbidden();

    Event::assertNotDispatched(Verified::class);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});
test('verified user is redirected to dashboard from verification prompt', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($user)->create();

    $tenantHost = tenantHost($tenant);
    URL::forceRootUrl('https://'.$tenantHost);

    Event::fake();

    $response = $this->actingAs($user)->get(route('verification.notice'));

    Event::assertNotDispatched(Verified::class);
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('already verified user visiting verification link is redirected without firing event again', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($user)->create();

    $tenantHost = tenantHost($tenant);
    URL::forceRootUrl('https://'.$tenantHost);

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $this->actingAs($user)->get($verificationUrl)
        ->assertRedirect(tenantRoute($tenant, 'dashboard').'?verified=1');

    Event::assertNotDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('verifies an email using the URL from the initial registration notification', function (): void {
    Notification::fake();

    $this->post(route('register.store'), [
        'name' => 'Richard Hendricks',
        'email' => 'richard@piedpiper.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'organization' => 'Pied Piper',
        'subdomain' => 'piedpiper',
    ]);

    $user = User::query()->where('email', 'richard@piedpiper.com')->sole();
    $notification = Notification::sent($user, VerifyEmail::class)->sole();

    $verificationUrl = $notification->toMail($user)->actionUrl;

    $response = $this->actingAs($user)->get($verificationUrl);

    $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('uses the current tenant URL when resending a verification email', function (): void {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    Tenant::factory()
        ->withDomain()
        ->withMember($user)
        ->create();

    $currentTenant = Tenant::factory()
        ->withDomain()
        ->withMember($user)
        ->create();

    $this->actingAs($user)
        ->post(tenantRoute($currentTenant, 'verification.send'));

    Notification::assertSentTo(
        $user,
        VerifyEmail::class,
        function (VerifyEmail $notification) use ($user, $currentTenant): bool {
            $url = $notification->toMail($user)->actionUrl;

            return parse_url($url, PHP_URL_HOST) === tenantHost($currentTenant);
        },
    );
});

it('uses a deterministic tenant URL when no current tenant is available', function (): void {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    Tenant::factory()
        ->withDomain('later-tenant')
        ->withMember($user)
        ->create([
            'id' => '01H00000000000000000000002',
            'slug' => 'later-tenant',
        ]);

    $fallbackTenant = Tenant::factory()
        ->withDomain('fallback-tenant')
        ->withMember($user)
        ->create([
            'id' => '01H00000000000000000000001',
            'slug' => 'fallback-tenant',
        ]);

    $user->notify(new VerifyEmail);

    $notification = Notification::sent($user, VerifyEmail::class)->sole();
    $url = $notification->toMail($user)->actionUrl;

    expect(parse_url($url, PHP_URL_HOST))->toBe(tenantHost($fallbackTenant));
});

it('resumes an intended verification URL after tenant login', function (): void {
    $user = User::factory()->unverified()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($user)->create();

    URL::forceRootUrl('https://'.tenantHost($tenant));

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $this->get($verificationUrl)
        ->assertRedirect(tenantRoute($tenant, 'login'));

    $response = $this->post(tenantRoute($tenant, 'login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect($verificationUrl);
    $this->assertAuthenticatedAs($user);

    $this->get($verificationUrl)
        ->assertRedirect(route('dashboard', absolute: false).'?verified=1');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});
